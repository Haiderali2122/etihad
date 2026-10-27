<?php

namespace App\Models;

use App\Models\Concerns\RequiresApproval;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supplier bill for an inventory item. Bills that aren't sent back add to the item's stock.
 */
#[Fillable(['purchased_on', 'party_id', 'inventory_item_id', 'quantity', 'rate', 'amount', 'bill_no', 'attachment', 'note', 'created_by'])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory, RequiresApproval;

    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Stops the owner sending back a bill whose stock has already been moved out.
     */
    public function rejectionBlocker(): ?string
    {
        return (float) $this->quantity > $this->item->balance()
            ? __('Stock from this bill has already been moved out; reverse the issue first, then send the bill back.')
            : null;
    }

    public function approvalKind(): string
    {
        return __('Purchase');
    }

    public function approvalTitle(): string
    {
        return "{$this->item->name} — {$this->party->name}";
    }

    public function approvalDetail(): string
    {
        return __(':qty :unit at Rs :rate, bill :bill', [
            'qty' => InventoryItem::qty($this->quantity),
            'unit' => $this->item->unit,
            'rate' => number_format((float) $this->rate),
            'bill' => $this->bill_no ?: '—',
        ]);
    }

    public function approvalAmount(): ?int
    {
        return $this->amount;
    }

    public function approvalIcon(): string
    {
        return 'box';
    }

    public function approvalUrl(): string
    {
        return route('inventory.index', ['tab' => 'purchases']);
    }
}
