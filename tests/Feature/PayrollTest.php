<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ForemanSheet;
use App\Models\Loan;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Models\Setting;
use App\Models\User;
use App\Support\AttendanceImporter;
use App\Support\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->month = Carbon::parse('2026-09-01');
    }

    private function mark(Employee $employee, int $day, AttendanceStatus $status, int $overtimeMinutes = 0): void
    {
        Attendance::factory()->create([
            'employee_id' => $employee->id, 'date' => $this->month->copy()->day($day)->toDateString(),
            'status' => $status, 'overtime_minutes' => $overtimeMinutes,
        ]);
    }

    public function test_absence_overtime_advance_and_loan_follow_the_client_rules(): void
    {
        $owner = User::factory()->owner()->create();
        // September has 30 days: Rs 45,000 ÷ 30 = Rs 1,500 a day; overtime = 1,500 ÷ 6 = Rs 250 an hour.
        $employee = Employee::factory()->create(['monthly_salary' => 45000]);
        $this->mark($employee, 1, AttendanceStatus::Absent);
        $this->mark($employee, 2, AttendanceStatus::Present, 12 * 60);
        $this->mark($employee, 3, AttendanceStatus::Leave);
        SalaryAdvance::factory()->create(['employee_id' => $employee->id, 'given_on' => '2026-09-22', 'amount' => 15000, 'created_by' => $owner->id]);
        Loan::factory()->create(['employee_id' => $employee->id, 'amount' => 30000, 'monthly_instalment' => 5000, 'first_instalment_month' => '2026-09-01', 'created_by' => $owner->id]);

        $line = app(PayrollCalculator::class)->prepare($this->month, $owner)->lines->sole();

        $this->assertSame(1500, $line->absence_deduction);
        $this->assertSame(12.0, $line->overtime_hours);
        $this->assertSame(3000, $line->overtime_amount);
        $this->assertSame(15000, $line->advances_deducted);
        $this->assertSame(5000, $line->loan_deducted);
        // 45,000 − 1,500 + 3,000 − 15,000 − 5,000
        $this->assertSame(26500, $line->net_pay);
    }

    public function test_net_pay_rounds_to_the_nearest_ten(): void
    {
        $owner = User::factory()->owner()->create();
        // Rs 31,000 ÷ 30 days = 1,033.33/day; one absence leaves 29,966.67 → 29,970.
        $employee = Employee::factory()->create(['monthly_salary' => 31000]);
        $this->mark($employee, 5, AttendanceStatus::Absent);

        $line = app(PayrollCalculator::class)->prepare($this->month, $owner)->lines->sole();

        $this->assertSame(29970, $line->net_pay);
        $this->assertSame(0, $line->net_pay % 10);
    }

    public function test_overtime_formula_and_unmarked_days_come_from_settings(): void
    {
        $owner = User::factory()->owner()->create();
        Setting::put(Setting::OvertimeDivisor, 12);
        Setting::put(Setting::OvertimeMultiplier, 1.5);
        Setting::put(Setting::UnmarkedDaysAs, 'absent');
        $employee = Employee::factory()->create(['monthly_salary' => 36000]);
        foreach (range(1, 28) as $day) {
            $this->mark($employee, $day, AttendanceStatus::Present, $day === 1 ? 120 : 0);
        }

        $line = app(PayrollCalculator::class)->prepare($this->month, $owner)->lines->sole();

        // 1,200/day ÷ 12 × 1.5 = 150/hour × 2 hours; days 29 and 30 unmarked = absent.
        $this->assertSame(300, $line->overtime_amount);
        $this->assertSame(2, $line->unmarked_days);
        $this->assertSame(2400, $line->absence_deduction);
    }

    public function test_someone_who_joins_mid_month_is_paid_only_for_their_days(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = Employee::factory()->create(['monthly_salary' => 30000, 'joined_on' => '2026-09-21']);

        $line = app(PayrollCalculator::class)->prepare($this->month, $owner)->lines->sole();

        // Paid for 21–30 September (10 of 30 days).
        $this->assertSame(10000, $line->net_pay);
    }

    public function test_finalizing_marks_advances_and_loan_instalments_as_deducted_and_reopen_undoes_it(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = Employee::factory()->create();
        $advance = SalaryAdvance::factory()->create(['employee_id' => $employee->id, 'given_on' => '2026-09-22', 'created_by' => $owner->id]);
        $loan = Loan::factory()->create(['employee_id' => $employee->id, 'amount' => 6000, 'monthly_instalment' => 2500, 'first_instalment_month' => '2026-09-01', 'created_by' => $owner->id]);

        $run = app(PayrollCalculator::class)->prepare($this->month, $owner);
        $this->actingAs($owner)->post(route('payroll.finalize', $run))->assertRedirect(route('payroll.show', $run));

        $this->assertSame(PayrollStatus::Finalized, $run->refresh()->status);
        $this->assertSame($run->id, $advance->refresh()->payroll_run_id);
        $this->assertSame(3500, $loan->refresh()->balance());
        $this->assertSame(0, $employee->outstandingAdvances());

        $this->actingAs($owner)->post(route('payroll.reopen', $run));

        $this->assertSame(PayrollStatus::Draft, $run->refresh()->status);
        $this->assertNull($advance->refresh()->payroll_run_id);
        $this->assertSame(6000, $loan->refresh()->balance());
    }

    public function test_loan_instalment_can_be_changed_for_a_month_but_not_beyond_what_is_owed(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        Loan::factory()->create(['employee_id' => $employee->id, 'amount' => 4000, 'monthly_instalment' => 2500, 'first_instalment_month' => '2026-09-01', 'created_by' => $owner->id]);
        $run = app(PayrollCalculator::class)->prepare($this->month, $owner);
        $line = $run->lines->sole();
        $payload = fn (int $loan) => ['lines' => [$line->id => ['loan_deducted' => $loan, 'bonus' => 500, 'other_deduction' => 0, 'note' => 'Eid bonus']]];

        $this->actingAs($owner)->put(route('payroll.lines.update', $run), $payload(5000))->assertSessionHasErrors("lines.{$line->id}.loan_deducted");

        $this->actingAs($owner)->put(route('payroll.lines.update', $run), $payload(0))->assertSessionHasNoErrors();
        $line->refresh();
        $this->assertSame(0, $line->loan_deducted);
        $this->assertSame(30500, $line->net_pay);

        // Recalculating keeps the hand edits.
        app(PayrollCalculator::class)->prepare($this->month, $owner);
        $this->assertSame(0, $line->refresh()->loan_deducted);
        $this->assertSame(500, $line->bonus);
    }

    public function test_manager_prepares_but_only_the_owner_finalizes(): void
    {
        $manager = User::factory()->create();
        Employee::factory()->create();

        $this->actingAs($manager)->post(route('payroll.store'), ['month' => '2026-09'])->assertRedirect();
        $run = PayrollRun::query()->sole();
        $this->actingAs($manager)->get(route('payroll.show', $run))->assertOk();
        $this->actingAs($manager)->get(route('payroll.slips', $run))->assertOk()->assertSee('Salary slip');
        $this->actingAs($manager)->post(route('payroll.finalize', $run))->assertForbidden();
    }

    public function test_foreman_sheet_splits_cash_into_advances_and_follows_approval(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();
        $foreman = Employee::factory()->foreman()->create();
        [$a, $b] = Employee::factory()->count(2)->create(['foreman_id' => $foreman->id]);

        $this->actingAs($manager)->post(route('foreman-sheets.store'), [
            'foreman_id' => $foreman->id, 'given_on' => today()->toDateString(), 'amount' => 10000,
            'lines' => [['employee_id' => $a->id, 'amount' => 3000], ['employee_id' => $b->id, 'amount' => 2000]],
        ])->assertSessionHasNoErrors();

        $sheet = ForemanSheet::query()->sole();
        $this->assertSame(3000, $a->outstandingAdvances());
        $this->assertSame(5000, $foreman->outstandingAdvances());
        $this->assertTrue($sheet->advances->every(fn ($adv) => $adv->status === ApprovalStatus::Pending));

        $this->actingAs($owner)->get(route('approvals.index'))->assertSee($foreman->name);
        $this->actingAs($owner)->patch(route('approvals.update', ['foreman-sheet', $sheet->id]), ['decision' => 'reject', 'note' => 'Wrong amounts']);

        $this->assertSame(0, $a->outstandingAdvances());
        $this->assertSame(0, $foreman->outstandingAdvances());
    }

    public function test_foreman_cannot_hand_out_more_than_he_was_given(): void
    {
        $foreman = Employee::factory()->foreman()->create();
        $worker = Employee::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('foreman-sheets.store'), [
            'foreman_id' => $foreman->id, 'given_on' => today()->toDateString(), 'amount' => 1000,
            'lines' => [['employee_id' => $worker->id, 'amount' => 1500]],
        ])->assertSessionHasErrors('amount');
    }

    public function test_mid_month_advance_is_not_given_twice_in_a_month(): void
    {
        $manager = User::factory()->create();
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        $post = fn () => $this->actingAs($manager)->post(route('salary-advances.mid-month.store'), ['given_on' => '2026-10-22', 'amounts' => [$employee->id => 15000]]);

        $post();
        $post();

        $this->assertSame(15000, $employee->outstandingAdvances());
    }

    public function test_attendance_day_sheet_saves_status_and_overtime(): void
    {
        $manager = User::factory()->create();
        [$a, $b] = Employee::factory()->count(2)->create();

        $this->actingAs($manager)->put(route('attendance.save-day'), [
            'date' => '2026-10-12',
            'rows' => [
                $a->id => ['status' => 'present', 'check_in' => '09:00', 'check_out' => '21:00', 'overtime_hours' => 4],
                $b->id => ['status' => 'absent', 'overtime_hours' => 3],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(240, $a->attendances()->sole()->overtime_minutes);
        $this->assertSame(0, $b->attendances()->sole()->overtime_minutes);
        $this->assertSame(AttendanceStatus::Absent, $b->attendances()->sole()->status);
    }

    public function test_device_import_reads_punches_and_works_out_overtime(): void
    {
        $aslam = Employee::factory()->create(['code' => 'E-001']);
        $tariq = Employee::factory()->create(['code' => 'E-002']);
        Attendance::factory()->create(['employee_id' => $tariq->id, 'date' => '2026-10-12', 'status' => AttendanceStatus::Leave, 'source' => 'manual']);

        $path = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($path, "AC-No.,Name,Time\nE-001,Aslam,2026-10-12 08:58\nE-001,Aslam,2026-10-12 13:02\nE-001,Aslam,2026-10-12 21:04\nE-002,Tariq,2026-10-12 09:10\nE-099,Nobody,2026-10-12 09:00\n");

        $result = app(AttendanceImporter::class)->import($path, 8);

        $this->assertSame(1, $result['saved']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(['E-099'], $result['unknown']);
        $day = $aslam->attendances()->sole();
        $this->assertSame('08:58', substr($day->check_in, 0, 5));
        $this->assertSame('21:04', substr($day->check_out, 0, 5));
        $this->assertSame(246, $day->overtime_minutes);
        $this->assertSame(AttendanceStatus::Leave, $tariq->attendances()->sole()->status);
    }

    public function test_an_employee_with_history_cannot_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $used = Attendance::factory()->create()->employee;
        $fresh = Employee::factory()->create();

        $this->actingAs($owner)->delete(route('employees.destroy', $used))->assertSessionHasErrors('employee');
        $this->actingAs($owner)->delete(route('employees.destroy', $fresh))->assertRedirect(route('employees.index'));
        $this->assertModelMissing($fresh);
    }

    public function test_the_last_day_of_the_month_counts(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = Employee::factory()->create(['monthly_salary' => 30000, 'joined_on' => '2026-09-30']);
        $this->mark($employee, 30, AttendanceStatus::Present, 60);
        SalaryAdvance::factory()->create(['employee_id' => $employee->id, 'given_on' => '2026-09-30', 'amount' => 500, 'created_by' => $owner->id]);

        $line = app(PayrollCalculator::class)->prepare($this->month, $owner)->lines->sole();

        $this->assertSame(0, $line->unmarked_days);
        $this->assertSame(1.0, $line->present_days);
        $this->assertSame(1.0, $line->overtime_hours);
        $this->assertSame(500, $line->advances_deducted);
    }
}
