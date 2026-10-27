<?php

namespace App\Models;

use Database\Factories\CashFloatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cash the owner hands to the manager to spend on the press.
 */
#[Fillable(['given_on', 'user_id', 'amount', 'note', 'created_by'])]
class CashFloat extends Model
{
    /** @use HasFactory<CashFloatFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'given_on' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
