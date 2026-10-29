<?php

namespace App\Models;

use App\Models\Concerns\RequiresApproval;
use Database\Factories\InventoryIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Items taken out of inventory: ink to a machine, glue to the binders, plates to the press.
 *
 * The full quantity leaves stock when it is handed over (also while it waits for the owner). At the end of the
 * shift whatever came back is entered as the returned quantity and goes back into stock, so only what was used
 * stays deducted. An entry the owner sends back drops out of stock altogether.
 */
#[Fillable(['issued_on', 'inventory_item_id', 'quantity', 'issued_to', 'employee_id', 'machine_id', 'reference', 'note', 'created_by'])]
class InventoryIssue extends Model
{
    /** @use HasFactory<InventoryIssueFactory> */
    use HasFactory, RequiresApproval;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'quantity' => 'decimal:2',
            'returned_quantity' => 'decimal:2',
            'settled_at' => 'datetime',
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
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    /**
     * What stays out of stock: the quantity handed over less what came back at the end of the shift.
     */
    public function used(): float
    {
        return round((float) $this->quantity - (float) $this->returned_quantity, 2);
    }

    public function resendUrl(): string
    {
        return route('inventory-issues.create', ['item' => $this->inventory_item_id]);
    }

    public function approvalKind(): string
    {
        return __('Material moved out');
    }

    public function approvalTitle(): string
    {
        return __(':qty :unit :item to :to', ['qty' => InventoryItem::qty($this->quantity), 'unit' => $this->item->unit, 'item' => $this->item->name, 'to' => $this->issued_to]);
    }

    public function approvalDetail(): string
    {
        return collect([
            $this->issued_on->format('j M Y'),
            $this->machine ? __('Machine :code', ['code' => $this->machine->code]) : null,
            $this->reference,
        ])->filter()->implode(' · ');
    }

    public function approvalAmount(): ?int
    {
        return null;
    }

    public function approvalIcon(): string
    {
        return 'send';
    }

    public function approvalUrl(): string
    {
        return route('inventory.index', ['tab' => 'issues']);
    }
}
