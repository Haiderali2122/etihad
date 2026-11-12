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
 * One raddi sale slip: its item lines and total. Money coming in, so it stays on the Raddi tab and not in Cash-out.
 */
#[Fillable(['inventory_category_id', 'sold_on', 'total', 'attachment', 'created_by'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'sold_on' => 'date',
            'total' => 'integer',
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

    public function receiptNumber(): string
    {
        return 'SALE-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function deletionLabel(): string
    {
        return $this->receiptNumber().' — Rs '.number_format($this->total).', '.$this->sold_on?->format('j M Y');
    }
}
