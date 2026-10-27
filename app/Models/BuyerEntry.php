<?php

namespace App\Models;

use App\Enums\BuyerEntryKind;
use Database\Factories\BuyerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An advance received from a buyer, or part of it adjusted against books supplied.
 */
#[Fillable(['party_id', 'entry_on', 'kind', 'amount', 'dispatch_id', 'note', 'created_by'])]
class BuyerEntry extends Model
{
    /** @use HasFactory<BuyerEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'entry_on' => 'date',
            'kind' => BuyerEntryKind::class,
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * @return BelongsTo<Dispatch, $this>
     */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }
}
