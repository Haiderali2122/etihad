<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\HasBillPayments;
use App\Models\Concerns\RecordsDeletion;
use App\Models\Concerns\RequiresApproval;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supplier bill for an inventory item. Bills that aren't sent back add to the item's stock.
 */
#[Fillable(['purchased_on', 'party_id', 'inventory_item_id', 'quantity', 'rate', 'amount', 'paid_amount', 'bill_no', 'receipt_no', 'attachment', 'note', 'created_by'])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasBillPayments, HasFactory, RecordsDeletion, RequiresApproval;

    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'integer',
            'paid_amount' => 'integer',
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

    public function resendUrl(): string
    {
        return route('purchases.create');
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

    protected static function booted(): void
    {
        // A payment's Cash-out line waits for the owner while the purchase does, and follows the decision.
        static::saved(function (self $purchase): void {
            if ($purchase->wasChanged('status')) {
                $purchase->payments()->get()->each->syncCashOut();
            }
        });
    }

    public function billTotal(): int
    {
        return (int) $this->amount;
    }

    /**
     * What a payment on this bill puts in Cash-out. Material tab items go under their own head and tab.
     *
     * @return array{amount: int, date: mixed, paid_to: string, reason: string, head: string, category_id: int|null}
     */
    public function paymentCashOut(BillPayment $payment): array
    {
        $category = $this->item?->category;
        $ownTab = $category?->show_as_tab;

        return [
            'amount' => (int) $payment->amount,
            'date' => $payment->paid_on,
            'paid_to' => (string) $this->party?->name,
            'reason' => trim('Purchase: '.InventoryItem::qty($this->quantity).' '.$this->item?->unit.' '.$this->item?->name.($this->bill_no ? ', bill '.$this->bill_no : '').($payment->note ? ', '.$payment->note : '')),
            'head' => $ownTab ? $category->name : 'Purchases',
            'category_id' => $ownTab ? $category->id : null,
        ];
    }

    /**
     * Stock bought and already moved out cannot be removed; the item would go below zero.
     */
    public function deletionBlocker(): ?string
    {
        if ($this->status === ApprovalStatus::Rejected || ! $this->item) {
            return null;
        }
        $balance = $this->item->balance();

        return $balance < (float) $this->quantity
            ? __('Some of this :item has already been moved out (:n left in stock), so the purchase cannot be deleted.', ['item' => $this->item->name, 'n' => $balance + 0])
            : null;
    }

    public function deletionLabel(): string
    {
        return ($this->item?->name ?? '').' × '.($this->quantity + 0).' — Rs '.number_format($this->amount).', '.($this->party?->name ?? '');
    }
}
