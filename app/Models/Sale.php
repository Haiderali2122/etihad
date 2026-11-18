<?php

namespace App\Models;

use App\Models\Concerns\RecordsDeletion;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One raddi sale slip: its item lines and total, and the money received for it (in parts, each with its date).
 * Money coming in, so it stays on the Raddi tab and the dashboard, never in Cash-out.
 */
#[Fillable(['inventory_category_id', 'sold_on', 'total', 'received_amount', 'attachment', 'created_by'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'sold_on' => 'date',
            'total' => 'integer',
            'received_amount' => 'integer',
        ];
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<InventoryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Each amount received, oldest first.
     *
     * @return HasMany<SaleReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(SaleReceipt::class)->orderBy('received_on')->orderBy('id');
    }

    /**
     * What the buyer still has to pay.
     */
    public function toReceive(): int
    {
        return max(0, (int) $this->total - (int) $this->received_amount);
    }

    public function syncReceived(): void
    {
        $this->forceFill(['received_amount' => (int) $this->receipts()->sum('amount')])->saveQuietly();
    }

    /**
     * Records money received, up to what is still to come.
     */
    public function receive(int $amount, mixed $on, ?int $by = null, ?string $note = null): ?SaleReceipt
    {
        $amount = min($amount, $this->toReceive());

        return $amount > 0 ? $this->receipts()->create(['received_on' => $on, 'amount' => $amount, 'note' => $note, 'created_by' => $by]) : null;
    }

    public function receiptNumber(): string
    {
        return 'SALE-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function deletionLabel(): string
    {
        return $this->receiptNumber().' — Rs '.number_format($this->total).', '.$this->sold_on?->format('j M Y');
    }
}
