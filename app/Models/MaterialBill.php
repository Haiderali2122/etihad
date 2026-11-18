<?php

namespace App\Models;

use App\Models\Concerns\HasBillPayments;
use App\Models\Concerns\RecordsDeletion;
use Database\Factories\MaterialBillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Diesel, Glue or Ink bill: its items (quantity × price per quantity) and total. It is paid later, in parts;
 * only the payments come off the manager's cash. Ink and glue items go into stock.
 */
#[Fillable(['inventory_category_id', 'bill_on', 'supplier', 'total', 'paid_amount', 'attachment', 'note', 'created_by'])]
class MaterialBill extends Model
{
    /** @use HasFactory<MaterialBillFactory> */
    use HasBillPayments, HasFactory, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'bill_on' => 'date',
            'total' => 'integer',
            'paid_amount' => 'integer',
        ];
    }

    /**
     * @return HasMany<MaterialBillItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaterialBillItem::class)->orderBy('id');
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

    public function billTotal(): int
    {
        return (int) $this->total;
    }

    public function receiptNumber(): string
    {
        return 'BILL-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function deletionLabel(): string
    {
        return $this->category?->name.' '.$this->receiptNumber().' — Rs '.number_format($this->total).($this->supplier ? ', '.$this->supplier : '');
    }
}
