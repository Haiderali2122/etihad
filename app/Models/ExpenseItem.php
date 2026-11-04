<?php

namespace App\Models;

use Database\Factories\ExpenseItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on an expense receipt: what was bought or paid for, and its amount.
 */
#[Fillable(['expense_id', 'description', 'amount'])]
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
}
