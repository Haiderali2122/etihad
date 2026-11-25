<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One employee's attendance for one day, entered by hand or imported from the biometric device.
 */
#[Fillable(['employee_id', 'date', 'status', 'shift', 'check_in', 'check_out', 'overtime_minutes', 'source', 'note'])]
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

    public const DayShift = 'day';

    public const NightShift = 'night';

    /** Overtime allowance for staying until the next shift starts (sleep hours). */
    public const StayOverAllowance = 240;

    /**
     * A shift and its graces, in minutes from the shift day's midnight: [start, end, late grace, early grace,
     * overtime grace]. The night shift's end is the next morning (e.g. 21:00 to 29:00 = 5 AM).
     *
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     */
    public static function shift(string $kind = self::DayShift): array
    {
        return once(function () use ($kind): array {
            $rules = Setting::payrollRules();
            $night = $kind === self::NightShift;
            $start = static::minutesOf($night ? $rules['night_start'] : $rules['shift_start']) ?? ($night ? 1260 : 540);
            $end = static::minutesOf($night ? $rules['night_end'] : $rules['shift_end']) ?? ($night ? 300 : 1020);
            if ($end <= $start) {
                $end += 1440;
            }

            return [$start, $end, max(0, (int) $rules['late_grace']), max(0, (int) $rules['early_grace']), max(0, (int) $rules['overtime_grace'])];
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
     * In and out on the shift's clock: a night-shift time after midnight is the next day (+24h), and an Out not
     * after the In is the next day too (in at 9 AM, out at 9 AM = out the next morning).
     *
     * @return array{0: ?int, 1: ?int}
     */
    public static function window(?string $in, ?string $out, string $kind = self::DayShift): array
    {
        [$start] = static::shift($kind);
        $i = static::minutesOf($in);
        if ($i !== null && $i < $start - 720) {
            $i += 1440;
        }
        $o = static::minutesOf($out);
        if ($o !== null) {
            $ref = $i ?? $start;
            while ($o <= $ref) {
                $o += 1440;
            }
        }

        return [$i, $o];
    }

    /**
     * Within the grace it does not count; past it, the whole time counts (late 9:25 = 25 minutes, not 5).
     */
    public static function beyondGrace(int $minutes, int $grace): int
    {
        return $minutes > $grace ? $minutes : 0;
    }

    /**
     * Overtime for a day. Sunday is the paid holiday, so all of Sunday's work is overtime. Other days: time after
     * the shift ends (past the grace). Staying until the next shift starts adds 4 hours for sleep: in at 9 AM and
     * out at 9 AM the next day = 16 hours + 4 = 20 hours.
     */
    public static function overtimeFor(string $date, ?string $in, ?string $out, string $kind = self::DayShift): int
    {
        [$i, $o] = static::window($in, $out, $kind);
        if ($o === null) {
            return 0;
        }
        [$start, $end, , , $grace] = static::shift($kind);
        $stayedOver = $o >= $start + 1440 ? static::StayOverAllowance : 0;

        if (Carbon::parse($date)->isSunday()) {
            return ($i !== null ? max(0, $o - $i) : 0) + $stayedOver;
        }

        return static::beyondGrace(max(0, $o - $end), $grace) + $stayedOver;
    }

    /**
     * Overtime on a working day shift for a given Out (used where only the Out is known).
     */
    public static function overtimeAfterShift(?string $checkOut): int
    {
        return static::overtimeFor('2001-01-01', null, $checkOut);
    }

    public function isPresent(): bool
    {
        return $this->status === AttendanceStatus::Present;
    }

    public function shiftKind(): string
    {
        return $this->shift === self::NightShift ? self::NightShift : self::DayShift;
    }

    /**
     * Late minutes: up to 20 minutes past the shift start is on time; after that the whole time counts. Not on
     * Sunday, the holiday.
     */
    public function lateMinutes(): int
    {
        if (! $this->isPresent() || $this->date?->isSunday()) {
            return 0;
        }
        [$i] = static::window($this->check_in, null, $this->shiftKind());
        [$start, , $grace] = static::shift($this->shiftKind());

        return $i !== null ? static::beyondGrace(max(0, $i - $start), $grace) : 0;
    }

    /**
     * Early minutes: leaving within 15 minutes of the shift end is not early; before that the whole time counts.
     */
    public function earlyMinutes(): int
    {
        if (! $this->isPresent() || $this->date?->isSunday()) {
            return 0;
        }
        [, $o] = static::window($this->check_in, $this->check_out, $this->shiftKind());
        [, $end, , $grace] = static::shift($this->shiftKind());

        return $o !== null ? static::beyondGrace(max(0, $end - $o), $grace) : 0;
    }

    /**
     * Minutes between In and Out on the shift's clock.
     */
    public function workedMinutes(): int
    {
        [$i, $o] = static::window($this->check_in, $this->check_out, $this->shiftKind());

        return $i !== null && $o !== null ? max(0, $o - $i) : 0;
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
