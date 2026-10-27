<?php

namespace Tests\Feature\E2E;

use App\Enums\AdvanceKind;
use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ForemanSheet;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\SalaryAdvance;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * End-to-end tests for salary advances, loans, payroll runs and salary slips, driven over HTTP.
 */
class PayrollAdvancesLoansE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 10:00:00');
        $this->owner = User::factory()->owner()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions, string $locale = 'en'): User
    {
        $role = Role::factory()->create(['permissions' => $permissions]);

        return User::factory()->create(['role_id' => $role->id, 'locale' => $locale]);
    }

    private function prepare(string $month, ?User $as = null): PayrollRun
    {
        $this->actingAs($as ?? $this->owner)->post(route('payroll.store'), ['month' => $month])->assertRedirect()->assertSessionHasNoErrors();

        return PayrollRun::query()->whereDate('month', $month.'-01')->sole();
    }

    private function lineFor(PayrollRun $run, Employee $employee): PayrollLine
    {
        return $run->lines()->where('employee_id', $employee->id)->sole();
    }

    private function mark(Employee $employee, string $date, AttendanceStatus $status, int $overtimeMinutes = 0): void
    {
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => $date, 'status' => $status, 'overtime_minutes' => $overtimeMinutes]);
    }

    public function test_every_screen_in_the_module_renders_for_the_owner(): void
    {
        $foreman = Employee::factory()->foreman()->create(['name' => 'Foreman Fazal']);
        $worker = Employee::factory()->create(['name' => 'Worker Waqas', 'foreman_id' => $foreman->id]);
        $advance = SalaryAdvance::factory()->create(['employee_id' => $worker->id, 'given_on' => '2026-09-10', 'created_by' => $this->owner->id]);
        $loan = Loan::factory()->create(['employee_id' => $worker->id, 'amount' => 6000, 'monthly_instalment' => 2000, 'first_instalment_month' => '2026-09-01', 'created_by' => $this->owner->id]);
        $this->actingAs($this->owner)->post(route('foreman-sheets.store'), [
            'foreman_id' => $foreman->id, 'given_on' => '2026-09-15', 'amount' => 5000,
            'lines' => [['employee_id' => $worker->id, 'amount' => 2000]],
        ])->assertSessionHasNoErrors();
        $sheet = ForemanSheet::query()->sole();
        $run = $this->prepare('2026-09');

        $pages = [
            route('salary-advances.index'), route('salary-advances.index', ['show' => 'deducted']),
            route('salary-advances.index', ['show' => 'all', 'employee' => $worker->id]),
            route('salary-advances.create'), route('salary-advances.create', ['employee' => $worker->id]),
            route('salary-advances.show', $advance), route('salary-advances.mid-month'),
            route('salary-advances.mid-month', ['date' => '2026-02-22']),
            route('loans.index'), route('loans.index', ['show' => 'cleared']), route('loans.index', ['show' => 'all']),
            route('loans.create'), route('loans.create', ['employee' => $worker->id]), route('loans.show', $loan),
            route('foreman-sheets.create'), route('foreman-sheets.show', $sheet),
            route('attendance.index'), route('attendance.index', ['month' => '2026-09']), route('attendance.day', ['date' => '2026-09-10']),
            route('payroll.index'), route('payroll.show', $run), route('payroll.slips', $run),
            route('payroll.slips', [$run, 'line' => $this->lineFor($run, $worker)->id]),
        ];

        foreach ($pages as $url) {
            $this->actingAs($this->owner)->get($url)->assertOk();
        }

        $this->actingAs($this->owner)->get(route('payroll.show', $run))->assertSee('Worker Waqas')->assertSee('Foreman Fazal')->assertSee('Finalize');
    }

    public function test_guests_are_sent_to_login_and_users_without_staff_view_get_403(): void
    {
        $run = PayrollRun::factory()->create(['month' => '2026-09-01']);
        $loan = Loan::factory()->create(['created_by' => $this->owner->id]);

        $this->get(route('payroll.index'))->assertRedirect(route('login'));
        $this->post(route('payroll.store'), ['month' => '2026-09'])->assertRedirect(route('login'));

        $nobody = $this->userWith([]);
        foreach ([route('payroll.index'), route('payroll.show', $run), route('payroll.slips', $run), route('salary-advances.index'), route('loans.index'), route('loans.show', $loan)] as $url) {
            $this->actingAs($nobody)->get($url)->assertForbidden();
        }
    }

    public function test_view_only_role_can_see_but_not_change_anything(): void
    {
        $employee = Employee::factory()->create();
        $loan = Loan::factory()->create(['employee_id' => $employee->id, 'created_by' => $this->owner->id]);
        $run = $this->prepare('2026-09');
        $line = $this->lineFor($run, $employee);
        $viewer = $this->userWith(['staff.view']);

        $this->actingAs($viewer)->get(route('payroll.index'))->assertOk()->assertDontSee('Prepare payroll');
        $this->actingAs($viewer)->get(route('payroll.show', $run))->assertOk()
            ->assertDontSee(route('payroll.finalize', $run))->assertDontSee(route('payroll.recalculate', $run))->assertDontSee('Save changes');
        $this->actingAs($viewer)->get(route('payroll.slips', $run))->assertOk();
        $this->actingAs($viewer)->get(route('salary-advances.index'))->assertOk();
        $this->actingAs($viewer)->get(route('loans.show', $loan))->assertOk();

        $this->actingAs($viewer)->get(route('salary-advances.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('salary-advances.store'), ['employee_id' => $employee->id, 'given_on' => '2026-10-01', 'amount' => 100])->assertForbidden();
        $this->actingAs($viewer)->get(route('salary-advances.mid-month'))->assertForbidden();
        $this->actingAs($viewer)->post(route('salary-advances.mid-month.store'), ['given_on' => '2026-10-22', 'amounts' => [$employee->id => 100]])->assertForbidden();
        $this->actingAs($viewer)->get(route('loans.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('loans.store'), [])->assertForbidden();
        $this->actingAs($viewer)->post(route('loans.repay', $loan), ['paid_on' => '2026-10-01', 'amount' => 100])->assertForbidden();
        $this->actingAs($viewer)->post(route('payroll.store'), ['month' => '2026-08'])->assertForbidden();
        $this->actingAs($viewer)->post(route('payroll.recalculate', $run))->assertForbidden();
        $this->actingAs($viewer)->put(route('payroll.lines.update', $run), ['lines' => [$line->id => ['loan_deducted' => 0, 'bonus' => 0, 'other_deduction' => 0]]])->assertForbidden();
        $this->actingAs($viewer)->post(route('payroll.finalize', $run))->assertForbidden();
        $this->actingAs($viewer)->post(route('payroll.reopen', $run))->assertForbidden();

        $this->assertSame(0, SalaryAdvance::query()->count());
        $this->assertSame(1, PayrollRun::query()->count());
    }

    public function test_preparer_without_finalize_and_finalizer_without_prepare(): void
    {
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        $preparer = $this->userWith(['staff.view', 'payroll.prepare']);
        $finalizer = $this->userWith(['staff.view', 'payroll.finalize']);

        $this->actingAs($finalizer)->post(route('payroll.store'), ['month' => '2026-09'])->assertForbidden();
        $run = $this->prepare('2026-09', $preparer);
        $line = $this->lineFor($run, $employee);

        $this->actingAs($preparer)->get(route('payroll.show', $run))->assertOk()
            ->assertSee(route('payroll.recalculate', $run))->assertDontSee(route('payroll.finalize', $run));
        $this->actingAs($preparer)->put(route('payroll.lines.update', $run), ['lines' => [$line->id => ['loan_deducted' => 0, 'bonus' => 1000, 'other_deduction' => 0]]])
            ->assertSessionHasNoErrors();
        $this->actingAs($preparer)->post(route('payroll.recalculate', $run))->assertRedirect(route('payroll.show', $run));
        $this->assertSame(31000, $line->refresh()->net_pay);
        $this->actingAs($preparer)->post(route('payroll.finalize', $run))->assertForbidden();

        $this->actingAs($finalizer)->get(route('payroll.show', $run))->assertOk()
            ->assertSee(route('payroll.finalize', $run))->assertDontSee(route('payroll.recalculate', $run))->assertDontSee('Save changes');
        $this->actingAs($finalizer)->post(route('payroll.finalize', $run))->assertRedirect(route('payroll.show', $run));
        $this->assertSame(PayrollStatus::Finalized, $run->refresh()->status);
        $this->assertSame($finalizer->id, $run->finalized_by);

        $this->actingAs($preparer)->post(route('payroll.reopen', $run))->assertForbidden();
        $this->actingAs($finalizer)->get(route('payroll.show', $run))->assertSee(route('payroll.reopen', $run))->assertSee($finalizer->name);
        $this->actingAs($finalizer)->post(route('payroll.reopen', $run))->assertRedirect(route('payroll.show', $run));
        $this->assertSame(PayrollStatus::Draft, $run->refresh()->status);
        $this->assertNull($run->finalized_by);
    }

    public function test_full_month_cycle_from_attendance_to_slips_then_next_month_and_reopen(): void
    {
        // September 2026 has 30 days. Aslam: 45,000 ÷ 30 = 1,500/day, OT 1,500 ÷ 6 = 250/hour.
        $aslam = Employee::factory()->create(['name' => 'Aslam Khan', 'monthly_salary' => 45000]);
        // Bilal: 31,000 ÷ 30 = 1,033.33/day, OT 172.22/hour.
        $bilal = Employee::factory()->create(['name' => 'Bilal Ahmed', 'monthly_salary' => 31000]);

        $day = fn (string $date, array $rows) => $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => $date, 'rows' => $rows])->assertSessionHasNoErrors();
        $day('2026-09-01', [$aslam->id => ['status' => 'absent'], $bilal->id => ['status' => 'present', 'overtime_hours' => 3]]);
        $day('2026-09-02', [$aslam->id => ['status' => 'present', 'overtime_hours' => 4], $bilal->id => ['status' => 'absent']]);
        $day('2026-09-03', [$aslam->id => ['status' => 'leave']]);
        $day('2026-09-04', [$aslam->id => ['status' => 'present', 'overtime_hours' => 2.5]]);

        $this->actingAs($this->owner)->post(route('salary-advances.store'), ['employee_id' => $aslam->id, 'given_on' => '2026-09-10', 'amount' => 3000, 'note' => 'Medicine'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $adHoc = SalaryAdvance::query()->sole();
        $this->assertSame(AdvanceKind::AdHoc, $adHoc->kind);
        $this->assertSame(ApprovalStatus::Approved, $adHoc->status);
        $this->actingAs($this->owner)->get(route('salary-advances.show', $adHoc))->assertOk()->assertSee('Aslam Khan')->assertSee('3,000');

        // Mid-month advance on the 22nd at 50%.
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['date' => '2026-09-22']))->assertOk()
            ->assertSee('value="22500"', false)->assertSee('value="15500"', false)->assertSee('value="2026-09-22"', false);
        $this->actingAs($this->owner)->post(route('salary-advances.mid-month.store'), ['given_on' => '2026-09-22', 'amounts' => [$aslam->id => 22500, $bilal->id => 10000]])
            ->assertRedirect(route('salary-advances.index'));
        $this->assertSame(25500, $aslam->outstandingAdvances());
        $this->assertSame(10000, $bilal->outstandingAdvances());
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['date' => '2026-09-22']))->assertSee('Already given Rs 22,500');

        $this->actingAs($this->owner)->post(route('loans.store'), [
            'employee_id' => $aslam->id, 'issued_on' => '2026-09-05', 'amount' => 20000, 'monthly_instalment' => 5000, 'first_instalment_month' => '2026-09',
        ])->assertSessionHasNoErrors();
        $loan = Loan::query()->sole();
        $this->assertSame('2026-09-01', $loan->first_instalment_month->toDateString());

        $this->actingAs($this->owner)->post(route('payroll.store'), ['month' => '2026-09'])->assertSessionHas('status');
        $sep = PayrollRun::query()->sole();
        $this->assertSame('2026-10-10', $sep->pay_date->toDateString());
        $this->assertEquals(6, $sep->rules['ot_divisor']);

        $a = $this->lineFor($sep, $aslam);
        $this->assertSame(30, $a->days_in_month);
        $this->assertSame(2.0, $a->present_days);
        $this->assertSame(1.0, $a->absent_days);
        $this->assertSame(1.0, $a->leave_days);
        $this->assertSame(26, $a->unmarked_days);
        $this->assertSame(1500, $a->absence_deduction);
        $this->assertSame(6.5, $a->overtime_hours);
        $this->assertSame(1630, $a->overtime_amount); // 6.5 × 250 = 1,625 → 1,630
        $this->assertSame(25500, $a->advances_deducted);
        $this->assertSame(5000, $a->loan_deducted);
        $this->assertSame(14630, $a->net_pay); // 45,000 − 1,500 + 1,630 − 25,500 − 5,000

        $b = $this->lineFor($sep, $bilal);
        $this->assertSame(1030, $b->absence_deduction);
        $this->assertSame(520, $b->overtime_amount); // 3 × 172.22 = 516.67 → 520
        $this->assertSame(20490, $b->net_pay); // 31,000 − 1,030 + 520 − 10,000

        $this->actingAs($this->owner)->get(route('payroll.show', $sep))->assertOk()->assertSee('14,630')->assertSee('20,490')->assertSee('35,120')
            ->assertSee('54 days have no attendance.');

        // Hand edits: lower loan instalment this month, bonus, other deduction.
        $this->actingAs($this->owner)->put(route('payroll.lines.update', $sep), ['lines' => [
            $a->id => ['loan_deducted' => 3000, 'bonus' => 500, 'other_deduction' => 120, 'note' => 'Broken plate'],
        ]])->assertSessionHasNoErrors()->assertRedirect(route('payroll.show', $sep));
        $this->assertSame(17010, $a->refresh()->net_pay); // 45,000 − 1,500 + 1,630 + 500 − 25,500 − 3,000 − 120

        // Recalculate keeps hand edits; reset wipes them.
        $this->actingAs($this->owner)->post(route('payroll.recalculate', $sep));
        $this->assertSame(17010, $a->refresh()->net_pay);

        $this->actingAs($this->owner)->post(route('payroll.finalize', $sep))->assertRedirect(route('payroll.show', $sep));
        $this->assertSame(PayrollStatus::Finalized, $sep->refresh()->status);
        $this->assertSame(17000, $loan->refresh()->balance());
        $this->assertSame(0, $aslam->outstandingAdvances());
        $this->assertSame(0, $bilal->outstandingAdvances());
        $this->assertSame(3, SalaryAdvance::query()->where('payroll_run_id', $sep->id)->count());
        $repayment = LoanRepayment::query()->sole();
        $this->assertSame('2026-10-10', $repayment->paid_on->toDateString());
        $this->assertSame($sep->id, $repayment->payroll_run_id);

        $this->actingAs($this->owner)->get(route('payroll.show', $sep))->assertOk()->assertDontSee('Save changes')->assertSee('Broken plate');
        $this->actingAs($this->owner)->get(route('payroll.slips', $sep))->assertOk()->assertSee('Aslam Khan')->assertSee('Bilal Ahmed')
            ->assertSee('Rs 17,010')->assertSee('balance after: Rs 17,000')->assertDontSee('still a draft');
        $this->actingAs($this->owner)->get(route('payroll.slips', [$sep, 'line' => $a->id]))->assertOk()->assertSee('Aslam Khan')->assertDontSee('Bilal Ahmed');
        $this->actingAs($this->owner)->get(route('loans.show', $loan))->assertOk()->assertSee('17,000');
        $this->actingAs($this->owner)->get(route('salary-advances.index', ['show' => 'deducted']))->assertOk()->assertSee('Aslam Khan');

        // October (31 days, nothing marked = all present): loan carries its balance.
        $oct = $this->prepare('2026-10');
        $ao = $this->lineFor($oct, $aslam);
        $this->assertSame(31, $ao->days_in_month);
        $this->assertSame(31, $ao->unmarked_days);
        $this->assertSame(0, $ao->absence_deduction);
        $this->assertSame(0, $ao->advances_deducted);
        $this->assertSame(5000, $ao->loan_deducted);
        $this->assertSame(40000, $ao->net_pay);
        $this->assertSame(31000, $this->lineFor($oct, $bilal)->net_pay);

        // Reopen September: advances and the loan instalment are released.
        $this->actingAs($this->owner)->post(route('payroll.reopen', $sep))->assertRedirect(route('payroll.show', $sep));
        $this->assertSame(PayrollStatus::Draft, $sep->refresh()->status);
        $this->assertSame(20000, $loan->refresh()->balance());
        $this->assertSame(25500, $aslam->outstandingAdvances());
        $this->assertSame(0, LoanRepayment::query()->count());
        $this->assertSame(17010, $a->refresh()->net_pay, 'Hand edits survive a reopen.');

        $this->actingAs($this->owner)->post(route('payroll.recalculate', $sep), ['reset' => 1]);
        $this->assertSame(14630, $this->lineFor($sep, $aslam)->net_pay, 'Reset recalculation drops hand edits.');
    }

    public function test_foreman_sheet_advances_reach_the_payroll(): void
    {
        $foreman = Employee::factory()->foreman()->create(['monthly_salary' => 30000]);
        $worker = Employee::factory()->create(['monthly_salary' => 30000, 'foreman_id' => $foreman->id]);

        $this->actingAs($this->owner)->post(route('foreman-sheets.store'), [
            'foreman_id' => $foreman->id, 'given_on' => '2026-09-12', 'amount' => 8000,
            'lines' => [['employee_id' => $worker->id, 'amount' => 5000]],
        ])->assertSessionHasNoErrors();

        $run = $this->prepare('2026-09');

        $this->assertSame(5000, $this->lineFor($run, $worker)->advances_deducted);
        $this->assertSame(3000, $this->lineFor($run, $foreman)->advances_deducted);
        $this->assertSame(27000, $this->lineFor($run, $foreman)->net_pay);
    }

    public function test_advance_larger_than_salary_gives_negative_net_pay(): void
    {
        $employee = Employee::factory()->create(['monthly_salary' => 20000]);
        $this->actingAs($this->owner)->post(route('salary-advances.store'), ['employee_id' => $employee->id, 'given_on' => '2026-09-05', 'amount' => 25000])
            ->assertSessionHasNoErrors();

        $run = $this->prepare('2026-09');
        $line = $this->lineFor($run, $employee);

        // Nothing stops the advance exceeding salary; the whole advance is taken and net goes negative.
        $this->assertSame(-5000, $line->net_pay);
        $this->actingAs($this->owner)->get(route('payroll.show', $run))->assertOk()->assertSee('Rs -5,000')->assertSee('color:var(--bad)', false);
        $this->actingAs($this->owner)->get(route('payroll.slips', $run))->assertOk()->assertSee('Rs -5,000');

        $this->actingAs($this->owner)->post(route('payroll.finalize', $run));
        // The full 25,000 is marked deducted; the 5,000 shortfall is not carried to October.
        $this->assertSame(0, $employee->outstandingAdvances());
        $this->assertSame(20000, $this->lineFor($this->prepare('2026-10'), $employee)->net_pay);
    }

    public function test_loan_is_repaid_over_months_and_cleared(): void
    {
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        $this->actingAs($this->owner)->post(route('loans.store'), [
            'employee_id' => $employee->id, 'issued_on' => '2026-05-20', 'amount' => 6000, 'monthly_instalment' => 2500, 'first_instalment_month' => '2026-06',
        ])->assertSessionHasNoErrors();
        $loan = Loan::query()->sole();

        // May is before the first instalment month.
        $may = $this->prepare('2026-05');
        $this->assertSame(0, $this->lineFor($may, $employee)->loan_deducted);
        $this->actingAs($this->owner)->post(route('payroll.finalize', $may))->assertRedirect();

        $expected = ['2026-06' => 2500, '2026-07' => 2500, '2026-08' => 1000, '2026-09' => 0];
        foreach ($expected as $month => $instalment) {
            $run = $this->prepare($month);
            $line = $this->lineFor($run, $employee);
            $this->assertSame($instalment, $line->loan_deducted, $month);
            $this->assertSame(30000 - $instalment, $line->net_pay, $month);
            $this->actingAs($this->owner)->post(route('payroll.finalize', $run))->assertRedirect();
        }

        $this->assertSame(0, $loan->refresh()->balance());
        $this->assertTrue($loan->isCleared());
        $this->assertSame(3, $loan->repayments()->count());
        $this->actingAs($this->owner)->get(route('loans.index', ['show' => 'cleared']))->assertOk()->assertSee($employee->name);
        $this->actingAs($this->owner)->get(route('loans.index'))->assertOk()->assertDontSee($employee->name);
        $this->actingAs($this->owner)->get(route('loans.show', $loan))->assertOk();

        // No more cash repayments once cleared.
        $this->actingAs($this->owner)->post(route('loans.repay', $loan), ['paid_on' => '2026-10-01', 'amount' => 1])->assertSessionHasErrors('amount');
    }

    public function test_cash_repayment_reduces_next_instalment_and_override_is_capped_at_balance(): void
    {
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        $loan = Loan::factory()->create(['employee_id' => $employee->id, 'amount' => 5000, 'monthly_instalment' => 2000, 'first_instalment_month' => '2026-09-01', 'created_by' => $this->owner->id]);

        $this->actingAs($this->owner)->post(route('loans.repay', $loan), ['paid_on' => '2026-08-15', 'amount' => 5001])->assertSessionHasErrors('amount');
        $this->actingAs($this->owner)->post(route('loans.repay', $loan), ['paid_on' => '2026-08-15', 'amount' => 0])->assertSessionHasErrors('amount');
        $this->actingAs($this->owner)->post(route('loans.repay', $loan), ['amount' => 100])->assertSessionHasErrors('paid_on');
        $this->actingAs($this->owner)->post(route('loans.repay', $loan), ['paid_on' => '2026-08-15', 'amount' => 3500, 'note' => 'Cash'])
            ->assertRedirect(route('loans.show', $loan))->assertSessionHas('status');
        $this->assertSame(1500, $loan->refresh()->balance());

        $run = $this->prepare('2026-09');
        $line = $this->lineFor($run, $employee);
        $this->assertSame(1500, $line->loan_deducted);

        $this->actingAs($this->owner)->put(route('payroll.lines.update', $run), ['lines' => [$line->id => ['loan_deducted' => 1510, 'bonus' => 0, 'other_deduction' => 0]]])
            ->assertSessionHasErrors("lines.{$line->id}.loan_deducted");
        $this->assertSame(1500, $line->refresh()->loan_deducted);

        $this->actingAs($this->owner)->post(route('payroll.finalize', $run));
        $this->assertTrue($loan->refresh()->isCleared());
    }

    public function test_preparing_the_same_month_twice_keeps_one_run(): void
    {
        Employee::factory()->create();
        $run = $this->prepare('2026-09');

        $this->actingAs($this->owner)->post(route('payroll.store'), ['month' => '2026-09'])->assertRedirect(route('payroll.show', $run));
        $this->actingAs($this->owner)->post(route('payroll.finalize', $run));
        $this->actingAs($this->owner)->post(route('payroll.store'), ['month' => '2026-09'])->assertRedirect(route('payroll.show', $run));

        $this->assertSame(1, PayrollRun::query()->count());
        $this->assertSame(1, PayrollLine::query()->count());
    }

    public function test_state_guards_on_draft_and_finalized_runs(): void
    {
        $employee = Employee::factory()->create();
        $run = $this->prepare('2026-09');
        $line = $this->lineFor($run, $employee);

        $this->actingAs($this->owner)->post(route('payroll.reopen', $run))->assertStatus(409);
        $this->actingAs($this->owner)->post(route('payroll.finalize', $run))->assertRedirect();
        $this->actingAs($this->owner)->post(route('payroll.finalize', $run))->assertStatus(409);
        $this->actingAs($this->owner)->post(route('payroll.recalculate', $run))->assertStatus(409);
        $this->actingAs($this->owner)->put(route('payroll.lines.update', $run), ['lines' => [$line->id => ['loan_deducted' => 0, 'bonus' => 999, 'other_deduction' => 0]]])
            ->assertStatus(409);
        $this->assertSame(0, $line->refresh()->bonus);
    }

    public function test_finalizing_an_empty_run(): void
    {
        $this->actingAs($this->owner)->post(route('payroll.store'), ['month' => '2026-09'])->assertRedirect();
        $run = PayrollRun::query()->sole();
        $this->assertSame(0, $run->lines()->count());

        $this->actingAs($this->owner)->get(route('payroll.show', $run))->assertOk()->assertSee('No staff on the books this month');
        $this->actingAs($this->owner)->post(route('payroll.finalize', $run))->assertRedirect(route('payroll.show', $run));
        $this->assertSame(PayrollStatus::Finalized, $run->refresh()->status);
        $this->actingAs($this->owner)->get(route('payroll.slips', $run))->assertOk()->assertSee('0 slips');
        $this->actingAs($this->owner)->get(route('payroll.index'))->assertOk()->assertSee('Rs 0');
    }

    public function test_advance_given_after_finalize_goes_to_next_month(): void
    {
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        $sep = $this->prepare('2026-09');
        $this->actingAs($this->owner)->post(route('payroll.finalize', $sep));

        // Late entry dated in the already-finalized September.
        $this->actingAs($this->owner)->post(route('salary-advances.store'), ['employee_id' => $employee->id, 'given_on' => '2026-09-28', 'amount' => 4000])
            ->assertSessionHasNoErrors();

        $this->assertSame(30000, $this->lineFor($sep, $employee)->refresh()->net_pay);
        $this->actingAs($this->owner)->get(route('salary-advances.index'))->assertOk()->assertSee('4,000');

        $oct = $this->prepare('2026-10');
        $this->assertSame(4000, $this->lineFor($oct, $employee)->advances_deducted);
        $this->assertSame(26000, $this->lineFor($oct, $employee)->net_pay);

        // An advance dated next month is not deducted early.
        SalaryAdvance::factory()->create(['employee_id' => $employee->id, 'given_on' => '2026-11-01', 'amount' => 1000, 'created_by' => $this->owner->id]);
        $this->actingAs($this->owner)->post(route('payroll.recalculate', $oct));
        $this->assertSame(4000, $this->lineFor($oct, $employee)->advances_deducted);
        $this->actingAs($this->owner)->post(route('payroll.finalize', $oct));
        $this->assertSame(1000, $employee->outstandingAdvances());
    }

    public function test_february_and_31_day_months_joiners_and_leavers(): void
    {
        // February 2026: 28 days. 28,000 ÷ 28 = 1,000/day; OT 1,000 ÷ 6 per hour.
        $regular = Employee::factory()->create(['monthly_salary' => 28000]);
        $joiner = Employee::factory()->create(['monthly_salary' => 28000, 'joined_on' => '2026-02-28']);
        $leaver = Employee::factory()->create(['monthly_salary' => 31000, 'joined_on' => '2025-01-01', 'left_on' => '2026-01-15', 'is_active' => false]);
        $future = Employee::factory()->create(['monthly_salary' => 28000, 'joined_on' => '2026-03-01']);

        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-02-27', 'rows' => [$regular->id => ['status' => 'absent']]])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-02-28', 'rows' => [
            $regular->id => ['status' => 'present', 'overtime_hours' => 6], $joiner->id => ['status' => 'present', 'overtime_hours' => 3],
        ]])->assertSessionHasNoErrors();
        // Attendance in the next month must not leak into February.
        $this->mark($regular, '2026-03-01', AttendanceStatus::Absent);
        $this->mark($regular, '2026-01-31', AttendanceStatus::Absent);

        $feb = $this->prepare('2026-02');
        $this->assertSame(2, $feb->lines()->count(), 'Only the regular and the joiner are on the books in February.');
        $r = $this->lineFor($feb, $regular);
        $this->assertSame(28, $r->days_in_month);
        $this->assertSame(1.0, $r->absent_days);
        $this->assertSame(1000, $r->absence_deduction);
        $this->assertSame(1000, $r->overtime_amount);
        $this->assertSame(26, $r->unmarked_days);
        $this->assertSame(28000, $r->net_pay);

        // Joined on the last day: paid 1 of 28 days + 3h OT at 166.67 = 500.
        $j = $this->lineFor($feb, $joiner);
        $this->assertSame(0, $j->unmarked_days);
        $this->assertSame(27000, $j->absence_deduction);
        $this->assertSame(1500, $j->net_pay);

        // Left on 15 January: paid 15 of 31 days in January, absent from February.
        $this->assertSame(0, $feb->lines()->where('employee_id', $future->id)->count());
        $this->assertSame(0, $feb->lines()->where('employee_id', $leaver->id)->count());
        $jan = $this->prepare('2026-01');
        $l = $this->lineFor($jan, $leaver);
        $this->assertSame(31, $l->days_in_month);
        $this->assertSame(15, $l->unmarked_days);
        $this->assertSame(16000, $l->absence_deduction);
        $this->assertSame(15000, $l->net_pay);
        $this->assertSame(27100, $this->lineFor($jan, $regular)->net_pay); // 28,000 − 903.23 (one absence in a 31-day month)

        $this->actingAs($this->owner)->get(route('payroll.show', $feb))->assertOk();
        $this->actingAs($this->owner)->get(route('payroll.slips', $feb))->assertOk()->assertSee('28 days');
    }

    public function test_mid_month_advance_defaults_and_february_clamp(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');
        $employee = Employee::factory()->create(['monthly_salary' => 31010]);
        Employee::factory()->create(['is_active' => false, 'name' => 'Gone Ghulam']);

        // 50% of 31,010 = 15,505 → rounded to 15,510; default date is the 22nd of this month.
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month'))->assertOk()
            ->assertSee('value="15510"', false)->assertSee('value="2026-10-22"', false)->assertDontSee('Gone Ghulam');

        Carbon::setTestNow('2026-02-10 09:00:00');
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month'))->assertOk()->assertSee('value="2026-02-22"', false);
    }

    public function test_mid_month_store_skips_blank_zero_inactive_and_already_paid(): void
    {
        $manager = User::factory()->create();
        [$a, $b, $c] = Employee::factory()->count(3)->create(['monthly_salary' => 30000]);
        $inactive = Employee::factory()->create(['is_active' => false]);

        $this->actingAs($manager)->post(route('salary-advances.mid-month.store'), ['given_on' => '2026-09-22', 'amounts' => [
            $a->id => 15000, $b->id => 0, $c->id => '', $inactive->id => 5000,
        ]])->assertRedirect(route('salary-advances.index'))->assertSessionHas('status', 'Mid-month advance recorded for 1 people, Rs 15,000 in all.');

        $this->assertSame(1, SalaryAdvance::query()->count());
        $advance = SalaryAdvance::query()->sole();
        $this->assertSame(AdvanceKind::MidMonth, $advance->kind);
        // Manager cannot approve, so the advance waits.
        $this->assertSame(ApprovalStatus::Pending, $advance->status);

        // Same month again (different day) is ignored; next month is allowed.
        $this->actingAs($manager)->post(route('salary-advances.mid-month.store'), ['given_on' => '2026-09-30', 'amounts' => [$a->id => 1000]]);
        $this->actingAs($manager)->post(route('salary-advances.mid-month.store'), ['given_on' => '2026-10-01', 'amounts' => [$a->id => 1000]]);
        $this->assertSame(16000, $a->outstandingAdvances());

        $this->actingAs($manager)->post(route('salary-advances.mid-month.store'), ['given_on' => '2026-09-22', 'amounts' => [$b->id => -5]])->assertSessionHasErrors('amounts.'.$b->id);
        $this->actingAs($manager)->post(route('salary-advances.mid-month.store'), ['amounts' => [$b->id => 5]])->assertSessionHasErrors('given_on');
        $this->actingAs($manager)->post(route('salary-advances.mid-month.store'), ['given_on' => '2026-09-22'])->assertSessionHasErrors('amounts');
    }

    public function test_rejected_advances_and_loans_are_ignored_by_payroll(): void
    {
        $manager = User::factory()->create();
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        $this->actingAs($manager)->post(route('salary-advances.store'), ['employee_id' => $employee->id, 'given_on' => '2026-09-05', 'amount' => 7000])
            ->assertSessionHas('status', 'Advance saved and sent for approval.');
        $this->actingAs($manager)->post(route('loans.store'), [
            'employee_id' => $employee->id, 'issued_on' => '2026-09-05', 'amount' => 10000, 'monthly_instalment' => 2000, 'first_instalment_month' => '2026-09',
        ])->assertSessionHas('status', 'Loan saved and sent for approval.');

        $advance = SalaryAdvance::query()->sole();
        $loan = Loan::query()->sole();
        $advance->reject($this->owner, 'No');
        $loan->reject($this->owner, 'No');

        $line = $this->lineFor($this->prepare('2026-09'), $employee);
        $this->assertSame(0, $line->advances_deducted);
        $this->assertSame(0, $line->loan_deducted);
        $this->assertSame(30000, $line->net_pay);
    }

    public function test_unmarked_days_as_absent_setting(): void
    {
        Setting::put(Setting::UnmarkedDaysAs, 'absent');
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        foreach (range(1, 20) as $d) {
            $this->mark($employee, sprintf('2026-09-%02d', $d), AttendanceStatus::Present);
        }
        $this->mark($employee, '2026-09-21', AttendanceStatus::Off);
        $this->mark($employee, '2026-09-22', AttendanceStatus::Leave);

        $run = $this->prepare('2026-09');
        $line = $this->lineFor($run, $employee);

        $this->assertSame(8, $line->unmarked_days);
        $this->assertSame(8.0, $line->absent_days);
        $this->assertSame(8000, $line->absence_deduction);
        $this->assertSame(22000, $line->net_pay);
        $this->actingAs($this->owner)->get(route('payroll.show', $run))->assertSee('They are counted as absent and deducted.');
    }

    public function test_validation_on_advances_loans_and_payroll(): void
    {
        $employee = Employee::factory()->create();
        $inactive = Employee::factory()->create(['is_active' => false]);
        $as = $this->actingAs($this->owner);

        $as->post(route('salary-advances.store'), ['employee_id' => $employee->id, 'given_on' => '2026-10-01', 'amount' => 0])->assertSessionHasErrors('amount');
        $as->post(route('salary-advances.store'), ['employee_id' => $employee->id, 'given_on' => '2026-10-01', 'amount' => 'abc'])->assertSessionHasErrors('amount');
        $as->post(route('salary-advances.store'), ['employee_id' => $inactive->id, 'given_on' => '2026-10-01', 'amount' => 100])->assertSessionHasErrors('employee_id');
        $as->post(route('salary-advances.store'), ['employee_id' => 9999, 'given_on' => '2026-10-01', 'amount' => 100])->assertSessionHasErrors('employee_id');
        $as->post(route('salary-advances.store'), ['employee_id' => $employee->id, 'given_on' => 'not-a-date', 'amount' => 100])->assertSessionHasErrors('given_on');
        $as->post(route('salary-advances.store'), ['employee_id' => $employee->id, 'given_on' => '2026-10-01', 'amount' => 100, 'note' => str_repeat('x', 256)])->assertSessionHasErrors('note');

        $loan = ['employee_id' => $employee->id, 'issued_on' => '2026-10-01', 'amount' => 5000, 'monthly_instalment' => 1000, 'first_instalment_month' => '2026-10'];
        $as->post(route('loans.store'), ['monthly_instalment' => 6000] + $loan)->assertSessionHasErrors('monthly_instalment');
        $as->post(route('loans.store'), ['monthly_instalment' => 0] + $loan)->assertSessionHasErrors('monthly_instalment');
        $as->post(route('loans.store'), ['first_instalment_month' => '2026-13'] + $loan)->assertSessionHasErrors('first_instalment_month');
        $as->post(route('loans.store'), ['first_instalment_month' => '10-2026'] + $loan)->assertSessionHasErrors('first_instalment_month');
        $as->post(route('loans.store'), ['first_instalment_month' => '2026-10-01'] + $loan)->assertSessionHasErrors('first_instalment_month');
        $as->post(route('loans.store'), ['employee_id' => $inactive->id] + $loan)->assertSessionHasErrors('employee_id');
        $as->post(route('loans.store'), ['amount' => -1] + $loan)->assertSessionHasErrors('amount');

        foreach (['', 'abc', '2026-9', '2026-13', '2026-09-01', '09-2026'] as $bad) {
            $as->post(route('payroll.store'), ['month' => $bad])->assertSessionHasErrors('month');
        }

        $this->assertSame(0, SalaryAdvance::query()->count());
        $this->assertSame(0, Loan::query()->count());
        $this->assertSame(0, PayrollRun::query()->count());

        $run = $this->prepare('2026-09');
        $line = $this->lineFor($run, $employee);
        $as->put(route('payroll.lines.update', $run), ['lines' => [$line->id => ['loan_deducted' => 0, 'bonus' => -10, 'other_deduction' => 0]]])
            ->assertSessionHasErrors("lines.{$line->id}.bonus");
        $as->put(route('payroll.lines.update', $run), ['lines' => [$line->id => ['bonus' => 0, 'other_deduction' => 0]]])
            ->assertSessionHasErrors("lines.{$line->id}.loan_deducted");
        $as->put(route('payroll.lines.update', $run), ['lines' => [$line->id => ['loan_deducted' => 0, 'bonus' => 0, 'other_deduction' => 1.5]]])
            ->assertSessionHasErrors("lines.{$line->id}.other_deduction");
        $as->put(route('payroll.lines.update', $run), [])->assertSessionHasErrors('lines');
    }

    public function test_line_edits_cannot_touch_another_runs_lines(): void
    {
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        $sep = $this->prepare('2026-09');
        $oct = $this->prepare('2026-10');
        $sepLine = $this->lineFor($sep, $employee);

        $this->actingAs($this->owner)->put(route('payroll.lines.update', $oct), ['lines' => [$sepLine->id => ['loan_deducted' => 0, 'bonus' => 5000, 'other_deduction' => 0]]])
            ->assertRedirect();
        $this->assertSame(0, $sepLine->refresh()->bonus);
    }

    public function test_slips_and_pages_render_in_urdu(): void
    {
        $urduUser = $this->userWith(['staff.view', 'payroll.prepare', 'advances.create'], 'ur');
        $employee = Employee::factory()->create(['name' => 'Aslam', 'name_ur' => 'اسلم', 'monthly_salary' => 30000]);
        $loan = Loan::factory()->create(['employee_id' => $employee->id, 'amount' => 6000, 'monthly_instalment' => 2000, 'first_instalment_month' => '2026-09-01', 'created_by' => $this->owner->id]);
        $advance = SalaryAdvance::factory()->create(['employee_id' => $employee->id, 'given_on' => '2026-09-10', 'created_by' => $this->owner->id]);
        $run = $this->prepare('2026-09');

        $this->actingAs($urduUser)->get(route('payroll.slips', $run))->assertOk()->assertSee('تنخواہ سلپس')->assertSee('خالص تنخواہ')->assertSee('Rs 23,000');
        $this->actingAs($urduUser)->get(route('payroll.show', $run))->assertOk()->assertSee('اسلم');
        $this->actingAs($urduUser)->get(route('payroll.index'))->assertOk();
        $this->actingAs($urduUser)->get(route('salary-advances.index'))->assertOk()->assertSee('اسلم');
        $this->actingAs($urduUser)->get(route('salary-advances.show', $advance))->assertOk();
        $this->actingAs($urduUser)->get(route('salary-advances.mid-month'))->assertOk();
        $this->actingAs($urduUser)->get(route('loans.index'))->assertOk();
        $this->actingAs($urduUser)->get(route('loans.show', $loan))->assertOk();
        $this->actingAs($urduUser)->get(route('loans.create'))->assertOk();
    }

    /**
     * Preparing last month on the 31st prepares that month, not the current one.
     */
    public function test_preparing_last_month_on_the_31st_prepares_that_month(): void
    {
        Carbon::setTestNow('2026-10-31 10:00:00');
        Employee::factory()->create();

        $this->actingAs($this->owner)->post(route('payroll.store'), ['month' => '2026-09'])->assertRedirect();

        $this->assertSame('2026-09-01', PayrollRun::query()->sole()->month->toDateString());
    }

    /**
     * A loan entered at month end keeps the chosen first instalment month.
     */
    public function test_loan_first_instalment_month_is_kept_at_month_end(): void
    {
        Carbon::setTestNow('2026-01-30 10:00:00');
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner)->post(route('loans.store'), [
            'employee_id' => $employee->id, 'issued_on' => '2026-01-30', 'amount' => 5000, 'monthly_instalment' => 1000, 'first_instalment_month' => '2026-02',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-02-01', Loan::query()->sole()->first_instalment_month->toDateString());
    }

    /**
     * Saving a day's attendance again updates it instead of failing.
     */
    public function test_saving_the_same_attendance_day_twice_updates_it(): void
    {
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        $save = fn (string $status) => $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-09-10', 'rows' => [$employee->id => ['status' => $status]]]);

        $save('present')->assertRedirect();
        $save('absent')->assertRedirect();

        $this->assertSame(AttendanceStatus::Absent, $employee->attendances()->sole()->status);
        $this->assertSame(1000, $this->lineFor($this->prepare('2026-09'), $employee)->absence_deduction);
    }

    /**
     * Finalizing a draft prepared early re-reads what is still outstanding, so advances and loans are deducted once.
     */
    public function test_finalizing_a_stale_draft_does_not_deduct_advances_and_loan_twice(): void
    {
        $employee = Employee::factory()->create(['monthly_salary' => 30000]);
        SalaryAdvance::factory()->create(['employee_id' => $employee->id, 'given_on' => '2026-09-22', 'amount' => 5000, 'created_by' => $this->owner->id]);
        $loan = Loan::factory()->create(['employee_id' => $employee->id, 'amount' => 3000, 'monthly_instalment' => 2500, 'first_instalment_month' => '2026-09-01', 'created_by' => $this->owner->id]);

        $sep = $this->prepare('2026-09');
        $oct = $this->prepare('2026-10'); // prepared early, while September is still a draft
        $this->actingAs($this->owner)->post(route('payroll.finalize', $sep));
        $this->actingAs($this->owner)->post(route('payroll.finalize', $oct));

        $this->assertSame(0, $loan->refresh()->balance());
        $deductedAdvances = PayrollLine::query()->where('employee_id', $employee->id)->sum('advances_deducted');
        $deductedLoan = PayrollLine::query()->where('employee_id', $employee->id)->sum('loan_deducted');
        $this->assertSame(5000, (int) $deductedAdvances, 'The 5,000 advance was taken out of both September and October pay.');
        $this->assertSame(3000, (int) $deductedLoan, 'October line deducts 2,500 but only 500 was owed and recorded.');
    }

    /**
     * A month cannot be finalized while an earlier month is still a draft.
     */
    public function test_a_later_month_waits_for_the_earlier_draft_to_be_finalized(): void
    {
        Employee::factory()->create(['monthly_salary' => 30000]);
        $sep = $this->prepare('2026-09');
        $oct = $this->prepare('2026-10');

        $this->actingAs($this->owner)->post(route('payroll.finalize', $oct))
            ->assertRedirect(route('payroll.show', $oct))->assertSessionHasErrors('payroll');
        $this->assertTrue($oct->refresh()->isDraft());

        $this->actingAs($this->owner)->post(route('payroll.finalize', $sep))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('payroll.finalize', $oct))->assertSessionHasNoErrors();
        $this->assertFalse($oct->refresh()->isDraft());
    }

    /**
     * A bad ?date= on the mid-month page falls back to the default date.
     */
    public function test_mid_month_page_with_bad_date_falls_back_to_the_default(): void
    {
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['date' => 'not-a-date']))->assertOk();
    }
}
