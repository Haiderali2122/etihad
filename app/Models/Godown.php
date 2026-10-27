<?php

namespace App\Models;

use Database\Factories\GodownFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place paper is unloaded into, e.g. Godown A.
 */
#[Fillable(['name', 'name_ur', 'address', 'notes', 'is_active'])]
class Godown extends Model
{
    /** @use HasFactory<GodownFactory> */
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
     * @return HasMany<PaperReceipt, $this>
     */
    public function paperReceipts(): HasMany
    {
        return $this->hasMany(PaperReceipt::class);
    }

    public function localName(): string
    {
        return app()->getLocale() === 'ur' && $this->name_ur ? $this->name_ur : $this->name;
    }
}
