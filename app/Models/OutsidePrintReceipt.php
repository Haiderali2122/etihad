<?php

namespace App\Models;

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
    use HasFactory;

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
}
