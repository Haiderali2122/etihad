<?php

namespace App\Models;

use Database\Factories\AttendanceDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A ZKTeco attendance machine that sends punches to the site. A machine that calls in for the first time waits
 * until it is approved on the Attendance machine page; until then whatever it sends is ignored.
 */
#[Hidden(['sync_key'])]
#[Fillable(['serial_number', 'name', 'is_approved', 'last_seen_at', 'last_ip', 'attlog_stamp', 'operlog_stamp', 'users_requested_at', 'pending_commands', 'next_command_id', 'last_reply', 'info', 'sync_key', 'last_synced_at', 'last_sync_result'])]
class AttendanceDevice extends Model
{
    /** @use HasFactory<AttendanceDeviceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'last_seen_at' => 'datetime',
            'users_requested_at' => 'datetime',
            'pending_commands' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every machine gets the key its reader program sends with what it reads.
        static::creating(fn (self $device) => $device->sync_key ??= Str::random(40));
    }

    /**
     * Asks the machine for everything it holds: it re-reads its settings and uploads what is new (CHECK), then
     * all punches in a date range and everyone registered on it. One command goes out each time it checks in.
     */
    public function queueFullUpload(): void
    {
        $this->queue([
            'CHECK',
            "DATA QUERY ATTLOG StartTime=2000-01-01 00:00:00\tEndTime=".now()->addDay()->format('Y-m-d').' 23:59:59',
            'DATA QUERY USERINFO',
        ]);
    }

    /**
     * @param  list<string>  $commands
     */
    public function queue(array $commands): void
    {
        $pending = $this->pending_commands ?? [];
        $id = (int) $this->next_command_id;
        foreach ($commands as $command) {
            $pending[] = 'C:'.$id++.':'.$command;
        }
        $this->forceFill(['pending_commands' => $pending, 'next_command_id' => $id])->save();
    }

    /**
     * The next command for the machine, taken off the queue, or null.
     */
    public function takeCommand(): ?string
    {
        $pending = $this->pending_commands ?? [];
        $next = array_shift($pending);
        if ($next !== null) {
            $this->forceFill(['pending_commands' => $pending ?: null])->save();
        }

        return $next;
    }

    /**
     * @return HasMany<DeviceUser, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(DeviceUser::class);
    }

    /**
     * @return HasMany<DevicePunch, $this>
     */
    public function punches(): HasMany
    {
        return $this->hasMany(DevicePunch::class);
    }

    /**
     * Seen in the last 10 minutes: the machine checks in every few seconds while it is online.
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(10));
    }
}
