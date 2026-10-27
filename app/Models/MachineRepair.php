<?php

namespace App\Models;

use App\Enums\RepairKind;
use App\Models\Concerns\RequiresApproval;
use Database\Factories\MachineRepairFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repair or new-part bill against a machine.
 */
#[Fillable(['repaired_on', 'machine_id', 'part_id', 'party_id', 'kind', 'description', 'bill_amount', 'discount', 'amount_paid', 'invoice_no', 'attachment', 'created_by'])]
class MachineRepair extends Model
{
    /** @use HasFactory<MachineRepairFactory> */
    use HasFactory, RequiresApproval;

    protected function casts(): array
    {
        return [
            'repaired_on' => 'date',
            'kind' => RepairKind::class,
            'bill_amount' => 'integer',
            'discount' => 'integer',
            'amount_paid' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function approvalKind(): string
    {
        return $this->kind === RepairKind::NewPart ? __('Part purchase') : __('Repair bill');
    }

    public function approvalTitle(): string
    {
        return "{$this->machine->code}: {$this->description}";
    }

    public function approvalDetail(): string
    {
        return __(':vendor, bill Rs :bill less Rs :disc discount', [
            'vendor' => $this->vendor->name,
            'bill' => number_format($this->bill_amount),
            'disc' => number_format($this->discount),
        ]);
    }

    public function approvalAmount(): ?int
    {
        return $this->amount_paid;
    }

    public function approvalIcon(): string
    {
        return 'wrench';
    }

    public function approvalUrl(): string
    {
        return route('machines.show', $this->machine_id);
    }
}
