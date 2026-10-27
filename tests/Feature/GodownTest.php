<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GodownTest extends TestCase
{
    use RefreshDatabase;

    public function test_godown_pages_need_the_permission(): void
    {
        $owner = User::factory()->owner()->create();
        $clerk = User::factory()->create(['role_id' => Role::factory()->create(['permissions' => ['paper.view']])->id]);
        $godown = Godown::factory()->create();

        foreach ([route('godowns.index'), route('godowns.create'), route('godowns.edit', $godown)] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
            $this->actingAs($clerk)->get($url)->assertForbidden();
        }
    }

    public function test_create_update_and_delete_an_unused_godown(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('godowns.store'), ['name' => 'Godown C', 'name_ur' => 'گودام سی', 'is_active' => 1])
            ->assertRedirect(route('godowns.index'));
        $godown = Godown::query()->where('name', 'Godown C')->sole();

        $this->actingAs($owner)->put(route('godowns.update', $godown), ['name' => 'Back store', 'address' => 'Behind press', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('Back store', $godown->refresh()->name);

        $this->actingAs($owner)->delete(route('godowns.destroy', $godown))->assertRedirect(route('godowns.index'));
        $this->assertModelMissing($godown);
    }

    public function test_a_godown_with_receipts_cannot_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $godown = PaperReceipt::factory()->create()->godown;

        $this->actingAs($owner)->delete(route('godowns.destroy', $godown))->assertSessionHasErrors('godown');
        $this->assertModelExists($godown);
    }

    public function test_receiving_a_truck_uses_active_godowns_only(): void
    {
        $manager = User::factory()->create();
        $active = Godown::factory()->create(['name' => 'Godown A']);
        $closed = Godown::factory()->create(['name' => 'Old shed', 'is_active' => false]);
        $payload = [
            'slip_no' => '6001', 'received_at' => now()->format('Y-m-d\TH:i'),
            'party_id' => Party::factory()->create()->id, 'paper_type_id' => PaperType::factory()->create()->id,
            'quantity' => 776, 'received_by' => 'Faisal',
        ];

        $this->actingAs($manager)->get(route('paper-receipts.create'))->assertSee('Godown A')->assertDontSee('Old shed');

        $this->actingAs($manager)->post(route('paper-receipts.store'), $payload + ['godown_id' => $closed->id])
            ->assertSessionHasErrors('godown_id');

        $this->actingAs($manager)->post(route('paper-receipts.store'), $payload + ['godown_id' => $active->id])
            ->assertSessionHasNoErrors();

        $receipt = PaperReceipt::query()->sole();
        $this->assertTrue($receipt->godown->is($active));
        $this->actingAs($manager)->get(route('paper-receipts.show', $receipt))->assertSee('Unloaded in Godown A');
    }

    public function test_renaming_a_godown_updates_old_slips(): void
    {
        $owner = User::factory()->owner()->create();
        $receipt = PaperReceipt::factory()->create();

        $this->actingAs($owner)->put(route('godowns.update', $receipt->godown), ['name' => 'Main godown', 'is_active' => 1]);

        $this->actingAs($owner)->get(route('paper-receipts.show', $receipt))->assertSee('Unloaded in Main godown');
    }
}
