<?php

namespace App\Models;

use App\Enums\ContractorPaymentKind;
use App\Models\Concerns\RecordsCashOut;
use App\Models\Concerns\RecordsDeletion;
use App\Models\Concerns\RequiresApproval;
use Database\Factories\ContractorPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money paid to a contractor: an advance before the work, or a payment to settle the hisab.
 */
#[Fillable(['contractor_id', 'work_order_id', 'paid_on', 'amount', 'kind', 'note', 'created_by'])]
class ContractorPayment extends Model
{
    /** @use HasFactory<ContractorPaymentFactory> */
    use HasFactory, RecordsCashOut, RecordsDeletion, RequiresApproval;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'integer',
            'kind' => ContractorPaymentKind::class,
        ];
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function resendUrl(): string
    {
        return route('contractors.show', $this->contractor_id);
    }

    public function approvalKind(): string
    {
        return __('Contractor :kind', ['kind' => mb_strtolower($this->kind->label())]);
    }

    public function approvalTitle(): string
    {
        return $this->contractor->name.($this->workOrder ? " — {$this->workOrder->number}" : '');
    }

    public function approvalDetail(): string
    {
        return __('Paid on :date', ['date' => $this->paid_on->format('j M Y')]).($this->note ? " · {$this->note}" : '');
    }

    public function approvalAmount(): ?int
    {
        return $this->amount;
    }

    public function approvalIcon(): string
    {
        return 'fold';
    }

    public function approvalUrl(): string
    {
        return route('contractors.show', $this->contractor_id);
    }

    /**
     * An advance or payment handed to a folding or binding contractor.
     */
    public function cashOut(): ?array
    {
        return [
            'amount' => (int) $this->amount,
            'date' => $this->paid_on,
            'paid_to' => (string) $this->contractor?->name,
            'reason' => trim('Contractor '.mb_strtolower($this->kind->label()).($this->workOrder ? ' for '.$this->workOrder->number : '')),
            'head' => 'Contractors',
        ];
    }

    public function cashOutUrl(): string
    {
        return route('contractors.show', $this->contractor_id);
    }

    public function deletionLabel(): string
    {
        return $this->kind->label().' Rs '.number_format($this->amount).' — '.($this->contractor?->name ?? '');
    }
}
