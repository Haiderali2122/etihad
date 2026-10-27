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
     * The employee's record for a day, or a new unsaved one. Dates are stored with a time part, so match on the day.
     */
    public static function forDay(int $employeeId, string $date): self
    {
        return static::query()->where('employee_id', $employeeId)->whereDate('date', $date)->first()
            ?? new static(['employee_id' => $employeeId, 'date' => $date]);
    }
}
