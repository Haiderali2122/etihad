<?php

namespace App\Models;

use Database\Factories\ExpenseHeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An expense head from the owner's list: petrol, diesel, machine repair, takiyan, mitti ka tel (earthen oil) and so on.
 */
#[Fillable(['name', 'name_ur', 'color', 'is_active'])]
class ExpenseHead extends Model
{
    /** @use HasFactory<ExpenseHeadFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Payment lines under this head, from any receipt (a receipt can hold several heads).
     *
     * @return HasMany<ExpenseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function localName(): string
    {
        return app()->getLocale() === 'ur' && $this->name_ur ? $this->name_ur : $this->name;
    }
}
