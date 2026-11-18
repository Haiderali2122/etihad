<?php

namespace App\Models;

use App\Models\Concerns\RecordsDeletion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received for a raddi sale, on one date. A sale can be received in parts; it is money coming in, so it
 * never touches Cash-out or the manager's cash.
 */
#[Fillable(['sale_id', 'received_on', 'amount', 'note', 'created_by'])]
class SaleReceipt extends Model
{
    use RecordsDeletion;

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $r) => $r->sale?->syncReceived());
        static::deleted(fn (self $r) => $r->sale?->syncReceived());
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function deletionLabel(): string
    {
        return __('Raddi received').' Rs '.number_format($this->amount).', '.$this->received_on?->format('j M Y');
    }
}
