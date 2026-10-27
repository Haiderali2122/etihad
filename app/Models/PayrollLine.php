<?php

namespace App\Models;

use App\Support\PressMath;
use Database\Factories\PayrollLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's salary for a payroll month.
 */
#[Fillable(['payroll_run_id', 'employee_id', 'monthly_salary', 'days_in_month', 'present_days', 'absent_days', 'leave_days', 'off_days', 'unmarked_days', 'overtime_hours', 'overtime_amount', 'absence_deduction', 'bonus', 'advances_deducted', 'loan_deducted', 'other_deduction', 'net_pay', 'note'])]
class PayrollLine extends Model
{
    /** @use HasFactory<PayrollLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'present_days' => 'float',
            'absent_days' => 'float',
            'leave_days' => 'float',
            'off_days' => 'float',
            'overtime_hours' => 'float',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function dailyRate(): float
    {
        return $this->days_in_month ? $this->monthly_salary / $this->days_in_month : 0;
    }

    public function gross(): int
    {
        return $this->monthly_salary - $this->absence_deduction + $this->overtime_amount + $this->bonus;
    }

    public function deductions(): int
    {
        return $this->advances_deducted + $this->loan_deducted + $this->other_deduction;
    }

    /**
     * Net pay: gross less deductions, rounded to the nearest Rs 10.
     */
    public function computeNet(): int
    {
        return PressMath::roundToTen($this->gross() - $this->deductions());
    }
}
