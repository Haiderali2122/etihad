<?php

namespace App\Models;

use Database\Factories\InventoryIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Items taken out of inventory: ink to a machine, glue to the binders, plates to the press.
 */
#[Fillable(['issued_on', 'inventory_item_id', 'quantity', 'issued_to', 'machine_id', 'reference', 'note', 'created_by'])]
class InventoryIssue extends Model
{
    /** @use HasFactory<InventoryIssueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'quantity' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
