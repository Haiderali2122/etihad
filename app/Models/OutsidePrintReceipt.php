<?php

namespace App\Models;

use App\Models\Concerns\RecordsCashOut;
use App\Models\Concerns\RecordsDeletion;
use Database\Factories\OutsidePrintReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Printed work received back from an outside press, with any unused rims it returned.
 */
#[Fillable(['work_order_id', 'received_on', 'printed_copies', 'leftover_rims', 'paper_receipt_id', 'bill_amount', 'note', 'created_by'])]
class OutsidePrintReceipt extends Model
{
    /** @use HasFactory<OutsidePrintReceiptFactory> */
    use HasFactory, RecordsCashOut, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'printed_copies' => 'integer',
            'leftover_rims' => 'integer',
            'bill_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<PaperReceipt, $this>
     */
    public function paperReceipt(): BelongsTo
    {
        return $this->belongsTo(PaperReceipt::class);
    }

    /**
     * The outside press's bill for the printed work.
     */
    public function cashOut(): ?array
    {
        return [
            'amount' => (int) $this->bill_amount,
            'date' => $this->received_on,
            'paid_to' => (string) $this->workOrder?->press?->name,
            'reason' => 'Outside printing for '.$this->workOrder?->number,
            'head' => 'Outside press',
        ];
    }

    public function cashOutUrl(): string
    {
        return route('work-orders.show', $this->work_order_id);
    }

    /**
     * The leftover rims came back as a paper slip; that slip goes with this receipt, if its rims are still in stock.
     */
    public function deletionBlocker(): ?string
    {
        return $this->paperReceipt?->deletionBlocker();
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $receipt) => $receipt->paperReceipt?->delete());
    }

    public function deletionLabel(): string
    {
        return __('Outside print receipt').' '.($this->workOrder?->number ?? '').' — '.number_format($this->printed_copies).' copies';
    }
}
