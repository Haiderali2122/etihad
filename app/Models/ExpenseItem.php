<?php

namespace App\Models;

use Database\Factories\ExpenseItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment on an expense receipt: its own head, who was paid, what for, and the amount.
 */
#[Fillable(['expense_id', 'expense_head_id', 'inventory_item_id', 'paid_to', 'description', 'quantity', 'rate', 'amount'])]
class ExpenseItem extends Model
{
    /** @use HasFactory<ExpenseItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * The stock item this line added to (Ink and Glue lines), if any.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<ExpenseHead, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(ExpenseHead::class, 'expense_head_id');
    }
}
