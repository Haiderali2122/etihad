<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a Diesel, Glue or Ink bill. Ink and glue lines point at the stock item they add to.
 */
#[Fillable(['material_bill_id', 'inventory_item_id', 'description', 'quantity', 'rate', 'amount'])]
class MaterialBillItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MaterialBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(MaterialBill::class, 'material_bill_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
