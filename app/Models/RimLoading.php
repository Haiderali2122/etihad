<?php

namespace App\Models;

use App\Models\Concerns\RecordsCashOut;
use Database\Factories\RimLoadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Printed rims carried from the press to folding by Younus's labourers ("Younus ka khata").
 * Amount = rims × rate per rim. Once paid it shows in Cash-out under Loading labour.
 */
#[Fillable(['work_order_id', 'loaded_on', 'rims', 'rate_per_rim', 'amount', 'loaded_by', 'paid_on', 'note', 'created_by'])]
class RimLoading extends Model
{
    /** @use HasFactory<RimLoadingFactory> */
    use HasFactory, RecordsCashOut;

    protected function casts(): array
    {
        return [
            'loaded_on' => 'date',
            'paid_on' => 'date',
            'rims' => 'decimal:2',
            'rate_per_rim' => 'decimal:2',
            'amount' => 'integer',
        ];
    }

    public static function price(float $rims, float $ratePerRim): int
    {
        return (int) round($rims * $ratePerRim);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    /**
     * Labour paid to Younus for this loading, once it has been paid.
     */
    public function cashOut(): ?array
    {
        if (! $this->paid_on) {
            return null;
        }

        return [
            'amount' => (int) $this->amount,
            'date' => $this->paid_on,
            'paid_to' => $this->loaded_by,
            'reason' => 'Rim loading to folding for '.$this->workOrder?->number,
            'head' => 'Loading labour',
        ];
    }

    public function cashOutUrl(): string
    {
        return route('rim-loadings.index');
    }
}
