<?php

namespace Tests\Feature;

use App\Http\Controllers\RecordDetailController;
use App\Models\Expense;
use App\Models\MaterialBill;
use App\Models\PaperReceipt;
use App\Models\PlateJob;
use App\Models\PlatePurchase;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Clicking a row opens the record's details: every field, linked records by name, its file, its lines and
 * payments. The page is read only: no edit, delete or pay buttons.
 */
class RecordDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_record_opens_read_only_with_its_fields_payments_and_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();
        $path = UploadedFile::fake()->image('bill.jpg')->store('attachments/plates', 'public');
        $purchase = PlatePurchase::factory()->create(['supplier' => 'Kodak House', 'plate_size' => '20x30', 'plates' => 100, 'amount' => 15000, 'attachment' => $path, 'created_by' => $owner->id]);
        $purchase->pay(5000, today(), $owner->id);

        $page = $this->actingAs($owner)->get(route('records.show', ['plate-purchase', $purchase->id]))->assertOk()
            ->assertSee('Kodak House')->assertSee('20x30')->assertSee('Rs 15,000')->assertSee('Rs 5,000')
            ->assertSee('Attached file')->assertSee(Storage::disk('public')->url($path))
            ->assertSee($owner->name); // "Entered by" shows the name, not a number

        // Read only: nothing on it changes anything.
        $page->assertDontSee('value="DELETE"', false)->assertDontSee('value="PATCH"', false)->assertDontSee('value="PUT"', false)
            ->assertDontSee(route('plate-stock.edit', $purchase), false)->assertDontSee(route('plate-stock.pay', $purchase), false)
            ->assertDontSee(route('records.destroy', ['plate-purchase', $purchase->id]), false);
    }

    public function test_rows_open_their_details_and_every_type_renders(): void
    {
        $owner = User::factory()->owner()->create();
        $records = [
            'expense' => Expense::factory()->create(),
            'material-bill' => MaterialBill::factory()->create(),
            'purchase' => Purchase::factory()->create(),
            'plate-job' => PlateJob::factory()->create(),
            'paper-receipt' => PaperReceipt::factory()->create(),
        ];
        foreach ($records as $type => $record) {
            $this->actingAs($owner)->get(route('records.show', [$type, $record->id]))->assertOk();
        }
        // Every type known to the page renders.
        foreach (RecordDetailController::Types as $type => [$class]) {
            if (method_exists($class, 'factory') && ($model = $class::query()->first() ?? rescue(fn () => $class::factory()->create(), null, false))) {
                $this->actingAs($owner)->get(route('records.show', [$type, $model->id]))->assertOk();
            }
        }

        // The list rows carry the link.
        $this->actingAs($owner)->get(route('plate-jobs.index', ['show' => 'all']))->assertSee('data-href="'.route('records.show', ['plate-job', $records['plate-job']->id]).'"', false);
        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'purchases']))->assertSee(route('records.show', ['purchase', $records['purchase']->id]), false);
    }

    public function test_details_follow_the_same_permissions_as_the_lists(): void
    {
        $purchase = Purchase::factory()->create();
        $nobody = User::factory()->create(['role_id' => Role::factory()->create(['permissions' => []])->id]);
        $this->actingAs($nobody)->get(route('records.show', ['purchase', $purchase->id]))->assertForbidden();
        $this->actingAs(User::factory()->owner()->create())->get(route('records.show', ['nothing', 1]))->assertNotFound();
        $this->actingAs(User::factory()->owner()->create())->get(route('records.show', ['purchase', 999999]))->assertNotFound();
        auth()->logout();
        $this->get(route('records.show', ['purchase', $purchase->id]))->assertRedirect(route('login'));
    }
}
