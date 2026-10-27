<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Works out each employee's pay for a month, following the rules given at scoping:
 *
 * - Daily rate = monthly salary ÷ days in the month.
 * - Each absent day is deducted at the daily rate (a missed day counts as an off day).
 * - Overtime hourly rate = daily rate ÷ divisor × multiplier (default ÷ 6, × 1).
 * - Advances given and not yet deducted, and this month's loan instalments, come off.
 * - Net pay is rounded to the nearest Rs 10.
 */
class PayrollCalculator
{
    /**
     * Create the month's draft, or rebuild its lines from current attendance, advances and loans.
     * Hand edits (loan instalment, bonus, other deduction, note) are kept unless $resetEdits is true.
     */
    public function prepare(Carbon $month, User $by, bool $resetEdits = false): PayrollRun
    {
        $month = $month->copy()->startOfMonth();
        $rules = Setting::payrollRules();

        return DB::transaction(function () use ($month, $by, $rules, $resetEdits): PayrollRun {
            $run = PayrollRun::query()->whereDate('month', $month->toDateString())->first()
                ?? PayrollRun::create([
                    'month' => $month->toDateString(),
                    'status' => PayrollStatus::Draft,
                    'pay_date' => $month->copy()->addMonth()->day(min($rules['pay_day'], 28))->toDateString(),
                    'created_by' => $by->id,
                ]);

            abort_unless($run->isDraft(), 409, __('This payroll is finalized. Reopen it to make changes.'));

            $run->update(['rules' => $rules]);
            if ($resetEdits) {
                $run->lines()->delete();
            }
            $existing = $run->lines()->get()->keyBy('employee_id');
            $keep = [];

            foreach ($this->employeesFor($month) as $employee) {
                $line = $this->calculate($employee, $month, $rules, $existing->get($employee->id));
                $line->payroll_run_id = $run->id;
                $line->save();
                $keep[] = $line->id;
            }

            $run->lines()->whereNotIn('id', $keep)->delete();

            return $run->fresh('lines');
        });
    }

    /**
     * @param  array{ot_divisor: float, ot_multiplier: float, standard_hours: float, unmarked_as: string}  $rules
     */
    public function calculate(Employee $employee, Carbon $month, array $rules, ?PayrollLine $line = null): PayrollLine
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;

        // Only the days this person was on the books count; days before joining or after leaving are unpaid.
        $from = $employee->joined_on && $employee->joined_on->gt($start) ? $employee->joined_on->copy() : $start->copy();
        $to = $employee->left_on && $employee->left_on->lt($end) ? $employee->left_on->copy() : $end->copy();
        $employedDays = $from->lte($to) ? (int) $from->diffInDays($to) + 1 : 0;

        $records = Attendance::query()->where('employee_id', $employee->id)
            ->whereBetween('date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();
        $count = fn (AttendanceStatus $status) => $records->where('status', $status)->count();

        $unmarked = max(0, $employedDays - $records->count());
        $absent = $count(AttendanceStatus::Absent) + ($rules['unmarked_as'] === 'absent' ? $unmarked : 0);
        $notEmployed = $daysInMonth - $employedDays;

        $dailyRate = $employee->monthly_salary / $daysInMonth;
        $overtimeHours = round($records->sum('overtime_minutes') / 60, 2);
        $hourlyOvertime = $rules['ot_divisor'] > 0 ? $dailyRate / $rules['ot_divisor'] * $rules['ot_multiplier'] : 0;

        $line ??= new PayrollLine(['bonus' => 0, 'other_deduction' => 0]);
        $line->fill([
            'employee_id' => $employee->id,
            'monthly_salary' => $employee->monthly_salary,
            'days_in_month' => $daysInMonth,
            'present_days' => $count(AttendanceStatus::Present),
            'absent_days' => $absent,
            'leave_days' => $count(AttendanceStatus::Leave),
            'off_days' => $count(AttendanceStatus::Off),
            'unmarked_days' => $unmarked,
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => PressMath::roundToTen($hourlyOvertime * $overtimeHours),
            'absence_deduction' => PressMath::roundToTen($dailyRate * ($absent + $notEmployed)),
            'advances_deducted' => $this->unsettledAdvances($employee, $end),
        ]);

        if (! $line->exists) {
            $line->loan_deducted = $this->loanInstalments($employee, $end);
        }

        $line->net_pay = $line->computeNet();

        return $line;
    }

    /**
     * Advances given by the end of this month that no finalized payroll has deducted yet.
     */
    public function unsettledAdvances(Employee $employee, Carbon $monthEnd): int
    {
        return (int) $employee->advances()->notRejected()->whereNull('payroll_run_id')
            ->where('given_on', '<=', $monthEnd->copy()->endOfDay())->sum('amount');
    }

    /**
     * The usual instalment for every loan due by this month, capped at what is still owed.
     */
    public function loanInstalments(Employee $employee, Carbon $monthEnd): int
    {
        return (int) $employee->loans()->notRejected()->withSum('repayments', 'amount')
            ->where('first_instalment_month', '<=', $monthEnd->copy()->endOfDay())
            ->get()
            ->sum(fn (Loan $loan) => min($loan->monthly_instalment, $loan->balance()));
    }

    /**
     * Lock the month: mark its advances as deducted and record each loan instalment against the oldest loans first.
     * Advances and the loan instalment are re-read from what is still outstanding, so a draft prepared before an
     * earlier month was finalized never deducts the same money twice. Bonus and other deductions are kept as edited.
     */
    public function finalize(PayrollRun $run, User $by): void
    {
        DB::transaction(function () use ($run, $by): void {
            $monthEnd = $run->month->copy()->endOfMonth()->endOfDay();

            foreach ($run->lines()->with('employee')->get() as $line) {
                $line->advances_deducted = $this->unsettledAdvances($line->employee, $monthEnd);
                $line->loan_deducted = min($line->loan_deducted, $line->employee->loanBalance());
                $line->net_pay = $line->computeNet();
                $line->save();

                SalaryAdvance::query()->where('employee_id', $line->employee_id)->notRejected()
                    ->whereNull('payroll_run_id')->where('given_on', '<=', $monthEnd)
                    ->update(['payroll_run_id' => $run->id]);

                $left = $line->loan_deducted;
                $loans = $line->employee->loans()->notRejected()->withSum('repayments', 'amount')->orderBy('issued_on')->orderBy('id')->get();
                foreach ($loans as $loan) {
                    $take = min($left, $loan->balance());
                    if ($take > 0) {
                        LoanRepayment::create([
                            'loan_id' => $loan->id, 'paid_on' => $run->pay_date ?? now(), 'amount' => $take,
                            'payroll_run_id' => $run->id, 'note' => __('Salary :month', ['month' => $run->label()]), 'created_by' => $by->id,
                        ]);
                        $left -= $take;
                    }
                }
            }

            $run->forceFill(['status' => PayrollStatus::Finalized, 'finalized_by' => $by->id, 'finalized_at' => now()])->save();
        });
    }

    /**
     * Undo a finalize: release its advances and remove the loan instalments it recorded.
     */
    public function reopen(PayrollRun $run): void
    {
        DB::transaction(function () use ($run): void {
            SalaryAdvance::query()->where('payroll_run_id', $run->id)->update(['payroll_run_id' => null]);
            LoanRepayment::query()->where('payroll_run_id', $run->id)->delete();
            $run->forceFill(['status' => PayrollStatus::Draft, 'finalized_by' => null, 'finalized_at' => null])->save();
        });
    }

    /**
     * Everyone on the books for at least one day of the month.
     *
     * @return Collection<int, Employee>
     */
    private function employeesFor(Carbon $month)
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth()->endOfDay();

        return Employee::query()
            ->where(fn ($q) => $q->whereNull('joined_on')->orWhere('joined_on', '<=', $end))
            ->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>=', $start))
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNotNull('left_on'))
            ->orderBy('name')
            ->get();
    }
}
