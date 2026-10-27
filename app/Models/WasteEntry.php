<?php

namespace App\Models;

use App\Enums\WasteDisposal;
use App\Enums\WasteStage;
use Database\Factories\WasteEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Waste from a stage of production, who was responsible, and how it was disposed of.
 */
#[Fillable(['work_order_id', 'recorded_on', 'stage', 'quantity', 'unit', 'responsible', 'disposal', 'sale_amount', 'sold_to', 'note', 'created_by'])]
class WasteEntry extends Model
{
    /** @use HasFactory<WasteEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'stage' => WasteStage::class,
            'disposal' => WasteDisposal::class,
            'quantity' => 'decimal:2',
            'sale_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
