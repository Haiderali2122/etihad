<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([MasterDataSeeder::class, DemoDataSeeder::class]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pages(): array
    {
        return collect([
            'dashboard', 'paper.index', 'paper-receipts.create', 'paper-issues.create', 'paper-types.index', 'paper-types.create',
            'parties.index', 'parties.create', 'inventory.index', 'purchases.create', 'inventory-issues.create', 'inventory-items.create', 'inventory-categories.index',
            'employees.index', 'employees.create', 'attendance.index', 'attendance.day', 'attendance.import',
            'salary-advances.index', 'salary-advances.create', 'salary-advances.mid-month', 'foreman-sheets.create',
            'loans.index', 'loans.create', 'payroll.index',
            'work-orders.index', 'work-orders.create', 'contractors.index', 'contractors.create', 'contractor-jobs.create',
            'plate-jobs.index', 'plate-jobs.create', 'dispatches.index', 'dispatches.create', 'buyers.index', 'waste.index', 'waste.create', 'expenses.index', 'expenses.create',
            'machines.index', 'machines.create', 'machine-repairs.create', 'reports.index',
        ])->mapWithKeys(fn (string $route) => [$route => [$route]])->all();
    }

    #[DataProvider('pages')]
    public function test_page_renders_for_owner_and_manager(string $route): void
    {
        foreach (['owner@etihad.test', 'faisal@etihad.test'] as $email) {
            $this->actingAs(User::query()->where('email', $email)->sole())->get(route($route))->assertOk();
        }
    }

    public function test_detail_pages_render(): void
    {
        $owner = User::query()->where('email', 'owner@etihad.test')->sole();

        $this->actingAs($owner)->get(route('paper-receipts.show', PaperReceipt::query()->first()))->assertOk()->assertSee('ETIHAD');
        $this->actingAs($owner)->get(route('machines.show', Machine::query()->first()))->assertOk();
        $this->actingAs($owner)->get(route('machines.edit', Machine::query()->first()))->assertOk();
        $this->actingAs($owner)->get(route('paper-types.edit', PaperType::query()->first()))->assertOk();
        $this->actingAs($owner)->get(route('parties.edit', Party::query()->first()))->assertOk();
        $this->actingAs($owner)->get(route('approvals.index'))->assertOk();
        $this->actingAs($owner)->get(route('settings.edit'))->assertOk();
        $this->actingAs($owner)->get(route('paper.index', ['tab' => 'receipts']))->assertOk();
        $this->actingAs($owner)->get(route('paper.index', ['tab' => 'issues']))->assertOk();
    }

    public function test_reports_export_csv(): void
    {
        $owner = User::query()->where('email', 'owner@etihad.test')->sole();

        $this->actingAs($owner)->get(route('reports.index', ['export' => 'expenses_by_head', 'from' => today()->startOfMonth()->toDateString()]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
    }

    public function test_login_and_urdu_switch(): void
    {
        $this->post(route('login'), ['email' => 'faisal@etihad.test', 'password' => 'password'])->assertRedirect(route('dashboard'));

        $this->get(route('locale', 'ur'))->assertRedirect();
        $this->get(route('dashboard'))->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_manager_cannot_open_settings(): void
    {
        $this->actingAs(User::query()->where('email', 'faisal@etihad.test')->sole())->get(route('settings.edit'))->assertForbidden();
    }
}
