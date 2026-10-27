<?php

namespace App\Models;

use App\Enums\WorkOrderStage;
use Database\Factories\DispatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Books loaded out to a buyer, with the loading labour paid per bundle and per rim.
 */
#[Fillable(['number', 'dispatched_on', 'buyer_id', 'destination', 'work_order_id', 'book_format_id', 'bundles', 'copies_per_bundle', 'loose_copies', 'copies', 'rims', 'labour_per_bundle', 'labour_per_rim', 'labour_amount', 'labour_paid_to', 'truck_no', 'note', 'created_by'])]
class Dispatch extends Model
{
    /** @use HasFactory<DispatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'dispatched_on' => 'date',
            'bundles' => 'integer',
            'copies_per_bundle' => 'integer',
            'loose_copies' => 'integer',
            'copies' => 'integer',
            'rims' => 'decimal:2',
            'labour_per_bundle' => 'decimal:2',
            'labour_per_rim' => 'decimal:2',
            'labour_amount' => 'integer',
        ];
    }

    /**
     * Work order stages whose books can be loaded out.
     *
     * @return list<WorkOrderStage>
     */
    public static function orderStages(): array
    {
        return [WorkOrderStage::Binding, WorkOrderStage::Ready, WorkOrderStage::Dispatched];
    }

    public static function nextNumber(): string
    {
        $last = static::query()->where('number', 'like', 'DSP-%')->orderByDesc('id')->value('number');

        return 'DSP-'.($last ? (int) substr($last, 4) + 1 : 101);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<BookFormat, $this>
     */
    public function bookFormat(): BelongsTo
    {
        return $this->belongsTo(BookFormat::class);
    }

    public function bookName(): string
    {
        return $this->bookFormat ? $this->bookFormat->book->name.($this->bookFormat->book->formats()->count() > 1 ? " — {$this->bookFormat->name}" : '') : '—';
    }
}
