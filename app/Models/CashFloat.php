<?php

namespace App\Models;

use App\Models\Concerns\RecordsDeletion;
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
    use HasFactory, RecordsDeletion;

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

    public function deletionLabel(): string
    {
        return __('Cash given to :name', ['name' => $this->user?->name]).' — Rs '.number_format($this->amount).', '.$this->given_on?->format('j M Y');
    }
}
