<?php

namespace App\Models;

use Database\Factories\PlateJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plates made by a plate processor, and whether the bill has been cleared.
 */
#[Fillable(['party_id', 'work_order_id', 'job_on', 'plates', 'rate', 'amount', 'paid_on', 'payment_note', 'note', 'created_by'])]
class PlateJob extends Model
{
    /** @use HasFactory<PlateJobFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'job_on' => 'date',
            'paid_on' => 'date',
            'plates' => 'integer',
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
}
