<?php

namespace Tests\Feature;

use App\Enums\AdvanceKind;
use App\Models\CashFloat;
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
        foreach (['Ali', 'Ahmed', 'Asad', 'Haseeb'] as $i => $name) {
            $this->team[$name] = Employee::factory()->create(['name' => $name, 'code' => (string) ($i + 1), 'foreman_id' => $this->foreman->id, 'monthly_salary' => 30000, 'joined_on' => '2025-01-01']);
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
        ForemanCash::query()->get()->each->delete();
        if ($cash > 0) {
            // Given in two parts between the 11th and the 21st.
            $this->giveForeman('2026-11-12', intdiv($cash, 2));
            $this->giveForeman('2026-11-19', $cash - intdiv($cash, 2));
        }

        return $this->actingAs($this->owner)->post(route('salary-advances.mid-month.store'), [
            'month' => '2026-11', 'given_on' => '2026-11-22',
            'as' => collect($as)->mapWithKeys(fn ($v, $n) => [$byName[$n]->id => $v])->all(),
            'taken' => collect($taken)->mapWithKeys(fn ($v, $n) => [$byName[$n]->id => $v])->all(),
        ]);
    }

    private function giveForeman(string $on, int $amount): void
    {
        $this->actingAs($this->owner)->post(route('foreman-cash.store'), ['foreman_id' => $this->foreman->id, 'given_on' => $on, 'amount' => $amount])->assertSessionHasNoErrors();
    }

    public function test_the_22nd_takes_off_what_each_person_already_took_and_what_the_foreman_kept(): void
    {
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['month' => '2026-11']))->assertOk()
            ->assertSee('Advance salary')->assertSee('Already taken')->assertSee('Give now')
            ->assertSeeInOrder(['Ghulam Rasool', 'Ali', 'Ahmed', 'Asad', 'Haseeb', 'Bilal Khan']) // foreman, then his team by number
            ->assertSee('value="15000"', false); // half of a 30,000 salary to start with

        $this->save22nd(
            ['Ali' => 20000, 'Ahmed' => 15000, 'Asad' => 15000, 'Haseeb' => 15000, 'Ghulam Rasool' => 25000, 'Bilal Khan' => 13500],
            ['Ali' => 10000, 'Ahmed' => 15000, 'Asad' => 15000, 'Haseeb' => 5000],
        )->assertSessionHasNoErrors();

        $advance = fn (Employee $e) => SalaryAdvance::query()->where('employee_id', $e->id)->where('kind', AdvanceKind::MidMonth)->sole();
        $this->assertSame([20000, 10000], [$advance($this->team['Ali'])->amount, $advance($this->team['Ali'])->taken_from_foreman]);
        // 50,000 given − 45,000 handed out = 5,000 kept, taken off the foreman's 25,000.
        $this->assertSame(5000, $advance($this->foreman)->taken_from_foreman);

        // Cash-out: each handout to the foreman, plus only what was handed over on the 22nd.
        $this->assertSame(50000, (int) ForemanCash::query()->get()->sum(fn (ForemanCash $c) => $c->cashOutExpense->amount));
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

        // Second round: the foreman is given 5,000 on 25 Nov and 3,000 on 5 Dec; Ali took 4,000 of it.
        $this->giveForeman('2026-11-25', 5000);
        $this->giveForeman('2026-12-05', 3000);
        $this->actingAs($this->owner)->get(route('payroll.show', $run))->assertSee('Rs 8,000');
        $lines = $run->lines()->get()->mapWithKeys(fn ($l) => [$l->id => ['loan_deducted' => 0, 'bonus' => 0, 'other_deduction' => 0, 'note' => '']])->all();
        $lines[$line($this->team['Ali'])->id]['taken_from_foreman'] = 4000;
        $this->actingAs($this->owner)->put(route('payroll.lines.update', $run), ['lines' => $lines])->assertSessionHasNoErrors();

        $this->assertSame(6000, $line($this->team['Ali'])->giveNow());
        // The foreman kept 8,000 − 4,000 = 4,000, taken off what he is paid now.
        $this->assertSame(4000, $line($this->foreman)->taken_from_foreman);
        $this->actingAs($this->owner)->get(route('payroll.show', $run))->assertOk()->assertSee('Give now')->assertSee('Rs 6,000');

        // Ali took more than is left to pay: refused, the extra is given as a loan instead.
        $lines[$line($this->team['Ali'])->id]['taken_from_foreman'] = 12000;
        $this->giveForeman('2026-12-06', 12000);
        $this->actingAs($this->owner)->put(route('payroll.lines.update', $run), ['lines' => $lines])->assertSessionHasErrors('lines.'.$line($this->team['Ali'])->id.'.taken_from_foreman');

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

    public function test_foreman_cash_is_given_in_parts_split_by_period_and_comes_off_the_managers_cash(): void
    {
        Carbon::setTestNow('2026-12-10 10:00:00');
        $manager = User::factory()->create(['name' => 'Faisal']);
        CashFloat::factory()->create(['user_id' => $manager->id, 'amount' => 200000]);

        $this->giveForeman('2026-11-10', 10000);  // counts for the 22nd
        $this->giveForeman('2026-11-21', 15000);  // counts for the 22nd
        $this->giveForeman('2026-11-22', 7000);   // counts for the 10th
        $this->giveForeman('2026-12-09', 3000);   // counts for the 10th (of November's salaries)

        $this->assertSame([$this->foreman->id => 25000], ForemanCash::totals(Carbon::parse('2026-11-01'), ForemanCash::Advance));
        $this->assertSame([$this->foreman->id => 10000], ForemanCash::totals(Carbon::parse('2026-11-01'), ForemanCash::Final));
        $this->actingAs($this->owner)->get(route('foreman-cash.index', ['month' => '2026-11']))->assertOk()
            ->assertSeeInOrder(['For the 22nd', 'Rs 10,000', 'Rs 15,000', 'Total to Ghulam Rasool', 'Rs 25,000', 'For the 10th', 'Rs 7,000', 'Rs 3,000', 'Rs 10,000']);
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['month' => '2026-11']))->assertSee('Rs 25,000');

        // Entered by the owner, the handouts still come off the manager's cash, as do the 22nd's advance salaries.
        $this->assertSame(200000 - 35000, $manager->cashInHand());
        $this->save22nd(['Bilal Khan' => 13500], [], 0)->assertSessionHasNoErrors();
        $this->assertSame(200000 - 13500, $manager->fresh()->cashInHand() + 0);
    }

    public function test_give_advance_and_foreman_sheet_buttons_are_gone(): void
    {
        $this->actingAs($this->owner)->get(route('salary-advances.index'))->assertOk()
            ->assertDontSee('Give advance')->assertDontSee('Foreman sheet')->assertSee('Advance salary (22nd)')->assertSee('Give loan');
        $this->actingAs($this->owner)->get(route('employees.show', $this->team['Ali']))->assertOk()->assertDontSee('Give advance');
    }

    public function test_one_person_is_saved_early_with_save_and_slip_and_gets_a_slip(): void
    {
        // The 18th: Ali asks for his advance salary early; he already took 3,000 from the foreman.
        Carbon::setTestNow('2026-11-18 11:00:00');
        $ali = $this->team['Ali'];
        $this->giveForeman('2026-11-12', 20000);

        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['month' => '2026-11']))->assertOk()->assertSee('Save & slip');
        $this->actingAs($this->owner)->post(route('salary-advances.mid-month.store'), [
            'month' => '2026-11', 'given_on' => '2026-11-22', 'only' => $ali->id,
            // The rest of the sheet still shows its pre-filled amounts; only Ali is saved.
            'as' => [$ali->id => 15000, $this->team['Ahmed']->id => 15000, $this->bilal->id => 13500],
            'taken' => [$ali->id => 3000],
        ])->assertRedirect(route('salary-advances.mid-month.slip', ['month' => '2026-11', 'employee' => $ali->id]))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Rs 12,000 handed over comes off the cash of'));

        $this->assertSame(1, SalaryAdvance::query()->count());
        $advance = SalaryAdvance::query()->sole();
        $this->assertSame([$ali->id, 15000, 3000, '2026-11-18'], [$advance->employee_id, $advance->amount, $advance->taken_from_foreman, $advance->given_on->toDateString()]);

        // His slip: advance salary, already taken, given now (12,000).
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month.slip', ['month' => '2026-11', 'employee' => $ali->id]))->assertOk()
            ->assertSee('ADVANCE SALARY')->assertSee('Ali')->assertSeeInOrder(['Rs 15,000', '− Rs 3,000', 'Rs 12,000'])->assertSee('18 Nov 2026');
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month.slip', ['month' => '2026-11', 'employee' => $this->bilal->id]))->assertNotFound();
        // His 3,000 comes off the foreman's 20,000; the sheet shows it, his slip does not.
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month.slip', ['month' => '2026-11', 'employee' => $ali->id]))->assertDontSee('Cash left with')->assertDontSee('Rs 17,000');
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['month' => '2026-11']))->assertSee('data-left="'.$this->foreman->id.'">Rs 17,000', false);

        // The foreman is saved next (keeps 17,000); then Ahmed takes 2,000 more: the foreman's saved record drops to 15,000.
        $post = fn (array $as, array $taken, int $only) => $this->actingAs($this->owner)->post(route('salary-advances.mid-month.store'), ['month' => '2026-11', 'given_on' => '2026-11-22', 'only' => $only, 'as' => $as, 'taken' => $taken]);
        $post([$this->foreman->id => 25000], [], $this->foreman->id)->assertSessionHasNoErrors();
        $foremanAdvance = fn () => SalaryAdvance::query()->where('employee_id', $this->foreman->id)->sole()->taken_from_foreman;
        $this->assertSame(17000, $foremanAdvance());
        $post([$this->team['Ahmed']->id => 15000], [$this->team['Ahmed']->id => 2000], $this->team['Ahmed']->id)->assertSessionHasNoErrors();
        $this->assertSame(15000, $foremanAdvance());
        $this->assertSame(3000, $advance->refresh()->taken_from_foreman); // Ali untouched

        // More than the foreman has left is refused.
        $post([$this->team['Asad']->id => 30000], [$this->team['Asad']->id => 16000], $this->team['Asad']->id)->assertSessionHasErrors('cash.'.$this->foreman->id);
    }

    public function test_the_10th_save_and_slip_saves_what_was_typed_and_opens_that_persons_slip(): void
    {
        $this->save22nd(['Ali' => 15000], ['Ali' => 0], 0)->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-12-09 10:00:00');
        $this->actingAs($this->owner)->post(route('payroll.store'), ['month' => '2026-11'])->assertSessionHasNoErrors();
        $run = PayrollRun::query()->sole();
        $line = $run->lines()->where('employee_id', $this->bilal->id)->sole();

        $this->actingAs($this->owner)->get(route('payroll.show', $run))->assertOk()->assertSee('Save & slip');
        $rows = $run->lines()->get()->mapWithKeys(fn ($l) => [$l->id => [
            'bonus' => $l->bonus, 'loan_deducted' => $l->loan_deducted, 'other_deduction' => $l->other_deduction, 'note' => $l->note,
            'taken_from_foreman' => $l->id === $line->id ? 2000 : 0,
        ]])->all();
        $this->actingAs($this->owner)->put(route('payroll.lines.update', $run), ['lines' => $rows, 'slip' => $line->id])
            ->assertRedirect(route('payroll.slips', [$run, 'line' => $line->id]));
        $this->assertSame(2000, $line->refresh()->taken_from_foreman);
    }

    public function test_a_row_saves_itself_as_it_is_typed_and_says_why_when_it_cannot(): void
    {
        $ali = $this->team['Ali'];
        $this->giveForeman('2026-11-12', 20000);
        $this->actingAs($this->owner)->get(route('salary-advances.mid-month', ['month' => '2026-11']))->assertOk()->assertSee('data-saved', false);

        $post = fn (array $as, array $taken) => $this->actingAs($this->owner)->postJson(route('salary-advances.mid-month.store'), ['month' => '2026-11', 'given_on' => '2026-11-22', 'only' => $ali->id, 'as' => $as, 'taken' => $taken]);
        $post([$ali->id => 15000], [$ali->id => 4000])->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'Rs 11,000'));
        $this->assertSame([15000, 4000], [SalaryAdvance::query()->sole()->amount, SalaryAdvance::query()->sole()->taken_from_foreman]);

        // Typed again: the same record changes; a mistake is explained, not saved.
        $post([$ali->id => 16000], [$ali->id => 4000])->assertOk();
        $this->assertSame(16000, SalaryAdvance::query()->sole()->amount);
        $post([$ali->id => 3000], [$ali->id => 4000])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'already took Rs 4,000'));
        $this->assertSame(16000, SalaryAdvance::query()->sole()->amount);
    }

    public function test_the_payroll_sheet_saves_itself_as_it_is_typed(): void
    {
        $this->save22nd(['Ali' => 15000], ['Ali' => 0], 0)->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-12-09 10:00:00');
        $this->actingAs($this->owner)->post(route('payroll.store'), ['month' => '2026-11'])->assertSessionHasNoErrors();
        $run = PayrollRun::query()->sole();
        $line = $run->lines()->where('employee_id', $this->bilal->id)->sole();
        $rows = $run->lines()->get()->mapWithKeys(fn ($l) => [$l->id => [
            'bonus' => $l->bonus, 'loan_deducted' => $l->loan_deducted, 'other_deduction' => $l->other_deduction, 'note' => $l->note,
            'taken_from_foreman' => $l->id === $line->id ? 1500 : 0,
        ]])->all();

        $this->actingAs($this->owner)->putJson(route('payroll.lines.update', $run), ['lines' => $rows])->assertOk()->assertJsonPath('message', 'Saved');
        $this->assertSame(1500, $line->refresh()->taken_from_foreman);
    }
}
