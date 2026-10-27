<?php

namespace App\Models;

use App\Enums\ContractorWork;
use Database\Factories\ContractorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folding or binding contractor, with the rates usually paid.
 */
#[Fillable(['name', 'name_ur', 'phone', 'work', 'folding_rate', 'form_rate', 'copy_rate', 'is_active', 'notes'])]
class Contractor extends Model
{
    /** @use HasFactory<ContractorFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work' => ContractorWork::class,
            'folding_rate' => 'decimal:2',
            'form_rate' => 'decimal:2',
            'copy_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<ContractorJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(ContractorJob::class);
    }

    /**
     * @return HasMany<ContractorPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ContractorPayment::class);
    }

    /**
     * Earned on jobs less advances and payments made: what the press still owes (negative = overpaid).
     */
    public function balance(): int
    {
        return (int) $this->jobs()->sum('amount') - (int) $this->payments()->notRejected()->sum('amount');
    }

    public function localName(): string
    {
        return app()->getLocale() === 'ur' && $this->name_ur ? $this->name_ur : $this->name;
    }
}
