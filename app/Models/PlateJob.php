<?php

namespace App\Models;

use App\Models\Concerns\RecordsCashOut;
use App\Models\Concerns\RecordsDeletion;
use Database\Factories\PlateJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plates made by a plate processor, and whether the bill has been cleared.
 */
#[Fillable(['party_id', 'work_order_id', 'job_on', 'plates', 'returned_plates', 'returned_on', 'rate', 'amount', 'paid_on', 'payment_note', 'note', 'created_by'])]
class PlateJob extends Model
{
    /** @use HasFactory<PlateJobFactory> */
    use HasFactory, RecordsCashOut, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'job_on' => 'date',
            'paid_on' => 'date',
            'plates' => 'integer',
            'returned_plates' => 'integer',
            'returned_on' => 'date',
            'rate' => 'decimal:2',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    /**
     * The processor's bill, once it has been paid. The same payment shows on the Plates and Processors tabs.
     */
    public function cashOut(): ?array
    {
        if (! $this->paid_on) {
            return null;
        }

        return [
            'amount' => (int) $this->amount,
            'date' => $this->paid_on,
            'paid_to' => (string) $this->processor?->name,
            'reason' => trim($this->plates.' plates'.($this->workOrder ? ' for '.$this->workOrder->number : '').($this->payment_note ? ', '.$this->payment_note : '')),
            'head' => 'Plates',
        ];
    }

    public function cashOutUrl(): string
    {
        return route('plate-jobs.index');
    }

    /**
     * Plates still with the processor: given out less those returned.
     */
    public function platesHeld(): int
    {
        return max(0, $this->plates - $this->returned_plates);
    }

    public function deletionLabel(): string
    {
        return trim(($this->processor?->name ?? '').', '.$this->plates.' plates'.($this->workOrder ? ' for '.$this->workOrder->number : '').', Rs '.number_format($this->amount), ', ');
    }
}
