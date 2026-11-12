<?php

namespace Tests\Feature;

use App\Enums\AdvanceKind;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ForemanCash;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The two paydays as the press runs them: the advance salary on the 22nd and the rest on the 10th of next month.
 * Before each, the foreman is given cash and hands out kharcha; on payday what each person already took is
 * subtracted, and what the foreman kept comes off his own pay.
 */
class PaydayTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Employee $foreman;

    /** @var array<string, Employee> */
    private array $team = [];

    private Employee $bilal;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-22 10:00:00');
        $this->owner = User::factory()->owner()->create();
        $this->foreman = Employee::factory()->create(['name' => 'Ghulam Rasool', 'is_foreman' => true, 'monthly_salary' => 50000, 'joined_on' => '2025-01-01']);
        foreach (['Ali', 'Ahmed', 'Asad', 'Haseeb'] as $name) {
            $this->team[$name] = Employee::factory()->create(['name' => $name, 'foreman_id' => $this->foreman->id, 'monthly_salary' => 30000, 'joined_on' => '2025-01-01']);
        }
        $this->bilal = Employee::factory()->create(['name' => 'Bilal Khan', 'monthly_salary' => 27000, 'joined_on' => '2025-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, int>  $as  name => advance salary
     * @param  array<string, int>  $taken  name => kharcha already taken
     */
    private function save22nd(array $as, array $taken, int $cash = 50000): TestResponse
    {
        $byName = collect($this->team)->put('Ghulam Rasool', $this->foreman)->put('Bilal Khan', $this->bilal);

        return $this->actingAs($this->owner)->post(route('salary-advances.mid-month.store'), [
            'month' => '2026-11', 'given_on' => '2026-11-22',
            'cash' => [$this->foreman->id => $cash],
            'as' => collect($as)->mapWithKeys(fn ($v, $n) => [$byName[$n]->id => $v])->all(),
            'taken' => collect($taken)->mapWithKeys(fn ($v, $n) => [$byName[$n]->id => $v])->all(),
        ]);
    }

    public function test_the_22nd_takes_off_what_each_person_already_took_and_what_the_foreman_kept(): void
    {
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['month' => '2026-11']))->assertOk()
            ->assertSee('Advance salary')->assertSee('Already taken')->assertSee('Give now')
            ->assertSeeInOrder(['Ghulam Rasool', 'Ahmed', 'Ali', 'Asad', 'Haseeb', 'Bilal Khan'])
            ->assertSee('value="15000"', false); // half of a 30,000 salary to start with

        $this->save22nd(
            ['Ali' => 20000, 'Ahmed' => 15000, 'Asad' => 15000, 'Haseeb' => 15000, 'Ghulam Rasool' => 25000, 'Bilal Khan' => 13500],
            ['Ali' => 10000, 'Ahmed' => 15000, 'Asad' => 15000, 'Haseeb' => 5000],
        )->assertSessionHasNoErrors();

        $advance = fn (Employee $e) => SalaryAdvance::query()->where('employee_id', $e->id)->where('kind', AdvanceKind::MidMonth)->sole();
        $this->assertSame([20000, 10000], [$advance($this->team['Ali'])->amount, $advance($this->team['Ali'])->taken_from_foreman]);
        // 50,000 given − 45,000 handed out = 5,000 kept, taken off the foreman's 25,000.
        $this->assertSame(5000, $advance($this->foreman)->taken_from_foreman);

        // Cash-out: the foreman's cash, plus only what was handed over on the 22nd.
        $this->assertSame(50000, ForemanCash::query()->sole()->cashOutExpense->amount);
        $this->assertSame(10000, $advance($this->team['Ali'])->cashOutExpense->amount);
        $this->assertNull($advance($this->team['Asad'])->cashOutExpense);
        $this->assertSame(50000 + 10000 + 0 + 0 + 10000 + 20000 + 13500, (int) Expense::query()->sum('amount'));

        // Amounts stay editable: changing Ali's advance salary updates his record, it does not add another.
        $this->save22nd(
            ['Ali' => 22000, 'Ahmed' => 15000, 'Asad' => 15000, 'Haseeb' => 15000, 'Ghulam Rasool' => 25000, 'Bilal Khan' => 13500],
            ['Ali' => 10000, 'Ahmed' => 15000, 'Asad' => 15000, 'Haseeb' => 5000],
        )->assertSessionHasNoErrors();
        $this->assertSame(22000, $advance($this->team['Ali'])->amount);
        $this->assertSame(12000, $advance($this->team['Ali'])->cashOutExpense->amount);
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['month' => '2026-11']))->assertSee('value="22000"', false);
    }

    public function test_the_22nd_refuses_kharcha_above_the_advance_and_advance_above_the_salary(): void
    {
        $this->save22nd(['Asad' => 15000], ['Asad' => 18000])->assertSessionHasErrors('as.'.$this->team['Asad']->id);
        $this->save22nd(['Asad' => 35000], [])->assertSessionHasErrors('as.'.$this->team['Asad']->id);
        // The team cannot take more than the foreman was given.
        $this->save22nd(['Ali' => 30000, 'Ahmed' => 30000], ['Ali' => 30000, 'Ahmed' => 30000])->assertSessionHasErrors('cash.'.$this->foreman->id);
        $this->assertSame(0, SalaryAdvance::query()->count());

        // Raising Asad's advance salary to cover what he took is accepted.
        $this->save22nd(['Asad' => 20000], ['Asad' => 18000], 18000)->assertSessionHasNoErrors();
    }

    public function test_the_10th_pays_the_rest_less_the_second_round_of_kharcha(): void
    {
        $this->save22nd(['Ali' => 20000, 'Ghulam Rasool' => 25000], ['Ali' => 10000], 10000)->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-12-10 10:00:00');
        $this->actingAs($this->owner)->post(route('payroll.store'), ['month' => '2026-11'])->assertRedirect();
        $run = PayrollRun::query()->sole();
        $line = fn (Employee $e) => $run->lines()->where('employee_id', $e->id)->sole();
        // 30,000 for the full month − 20,000 advance salary.
        $this->assertSame(10000, $line($this->team['Ali'])->net_pay);

        // Second round: the foreman is given 8,000 and Ali took 4,000 of it between the 23rd and the 9th.
        $lines = $run->lines()->get()->mapWithKeys(fn ($l) => [$l->id => ['loan_deducted' => 0, 'bonus' => 0, 'other_deduction' => 0, 'note' => '']])->all();
        $lines[$line($this->team['Ali'])->id]['taken_from_foreman'] = 4000;
        $this->actingAs($this->owner)->put(route('payroll.lines.update', $run), ['lines' => $lines, 'cash' => [$this->foreman->id => 8000]])->assertSessionHasNoErrors();

        $this->assertSame(6000, $line($this->team['Ali'])->giveNow());
        // The foreman kept 8,000 − 4,000 = 4,000, taken off what he is paid now.
        $this->assertSame(4000, $line($this->foreman)->taken_from_foreman);
        $this->actingAs($this->owner)->get(route('payroll.show', $run))->assertOk()->assertSee('Give now')->assertSee('Rs 6,000');

        // Ali took more than is left to pay: refused, the extra is given as a loan instead.
        $lines[$line($this->team['Ali'])->id]['taken_from_foreman'] = 12000;
        $this->actingAs($this->owner)->put(route('payroll.lines.update', $run), ['lines' => $lines, 'cash' => [$this->foreman->id => 20000]])->assertSessionHasErrors('lines.'.$line($this->team['Ali'])->id.'.taken_from_foreman');

        // Finalizing: Cash-out counts what is handed over on the 10th, and the 22nd's amounts lock.
        $this->actingAs($this->owner)->post(route('payroll.finalize', $run))->assertRedirect();
        $run->refresh();
        $this->assertSame((int) $run->lines()->sum('net_pay') - (int) $run->lines()->sum('taken_from_foreman'), $run->cashOutExpense->amount);
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['month' => '2026-11']))->assertSee('locked');
        $this->save22nd(['Ali' => 25000], [])->assertStatus(409);
    }

    public function test_the_salaries_10th_button_opens_the_month_being_paid(): void
    {
        // On 22 November the coming 10th pays November.
        $this->actingAs($this->owner)->get(route('payroll.tenth'))->assertRedirect(route('payroll.show', PayrollRun::query()->sole()));
        $this->assertSame('2026-11', PayrollRun::query()->sole()->month->format('Y-m'));
        $this->actingAs($this->owner)->get(route('payroll.show', PayrollRun::query()->sole()))->assertOk()
            ->assertSee('Salaries (10th) — November 2026')->assertSee('Already taken')->assertSee('Give now');

        // On 5 December it is still November's salary being paid.
        Carbon::setTestNow('2026-12-05 10:00:00');
        $this->actingAs($this->owner)->get(route('payroll.tenth'))->assertRedirect(route('payroll.show', PayrollRun::query()->sole()));
    }

    public function test_give_advance_and_foreman_sheet_buttons_are_gone(): void
    {
        $this->actingAs($this->owner)->get(route('salary-advances.index'))->assertOk()
            ->assertDontSee('Give advance')->assertDontSee('Foreman sheet')->assertSee('Advance salary (22nd)')->assertSee('Give loan');
        $this->actingAs($this->owner)->get(route('employees.show', $this->team['Ali']))->assertOk()->assertDontSee('Give advance');
    }
}
