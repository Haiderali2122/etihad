<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's attendance for one day, entered by hand or imported from the biometric device.
 */
#[Fillable(['employee_id', 'date', 'status', 'check_in', 'check_out', 'overtime_minutes', 'source', 'note'])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
            'overtime_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The shift and its graces, in minutes: [start, end, late grace, early grace, overtime grace].
     *
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     */
    public static function shift(): array
    {
        return once(function (): array {
            $rules = Setting::payrollRules();

            return [
                static::minutesOf($rules['shift_start']) ?? 540, static::minutesOf($rules['shift_end']) ?? 1020,
                max(0, (int) $rules['late_grace']), max(0, (int) $rules['early_grace']), max(0, (int) $rules['overtime_grace']),
            ];
        });
    }

    public static function minutesOf(?string $time): ?int
    {
        if (! $time || ! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    /**
     * Within the grace it does not count; past it, the whole time counts (late 9:25 = 25 minutes, not 5).
     */
    public static function beyondGrace(int $minutes, int $grace): int
    {
        return $minutes > $grace ? $minutes : 0;
    }

    /**
     * Overtime: time after the shift ends, once past the overtime grace (with 15: up to 5:15 is none, 5:40 = 40m).
     */
    public static function overtimeAfterShift(?string $checkOut): int
    {
        $out = static::minutesOf($checkOut);
        [, $end, , , $grace] = static::shift();

        return $out === null ? 0 : static::beyondGrace(max(0, $out - $end), $grace);
    }

    public function isPresent(): bool
    {
        return $this->status === AttendanceStatus::Present;
    }

    /**
     * Late minutes: with the usual 20 minutes' grace, up to 9:20 is on time and after that the whole time from 9:00
     * counts (9:25 = 25).
     */
    public function lateMinutes(): int
    {
        $in = static::minutesOf($this->check_in);
        [$start, , $grace] = static::shift();

        return $this->isPresent() && $in !== null ? static::beyondGrace(max(0, $in - $start), $grace) : 0;
    }

    /**
     * Early minutes: with the usual 15 minutes' grace, leaving from 4:45 is not early; before that the whole time
     * before 5:00 counts (4:30 = 30).
     */
    public function earlyMinutes(): int
    {
        $out = static::minutesOf($this->check_out);
        $in = static::minutesOf($this->check_in);
        [, $end, , $grace] = static::shift();

        return $this->isPresent() && $out !== null && ($in === null || $out > $in) ? static::beyondGrace(max(0, $end - $out), $grace) : 0;
    }

    /**
     * "1h 05m", "45m", or "—" for none.
     */
    public static function hm(int $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }

        return $minutes >= 60 ? intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m' : $minutes.'m';
    }

    /**
     * The colour the day shows in: absent, late, overtime or present.
     */
    public function tone(): ?string
    {
        return match (true) {
            $this->status === AttendanceStatus::Absent => 'absent',
            ! $this->isPresent() => null,
            $this->lateMinutes() > 0 => 'late',
            $this->overtime_minutes > 0 => 'overtime',
            default => 'present',
        };
    }

    /**
     * The employee's record for a day, or a new unsaved one. Dates are stored with a time part, so match on the day.
     */
    public static function forDay(int $employeeId, string $date): self
    {
        return static::query()->where('employee_id', $employeeId)->whereDate('date', $date)->first()
            ?? new static(['employee_id' => $employeeId, 'date' => $date]);
    }
}
