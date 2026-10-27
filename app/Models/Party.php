<?php

namespace App\Models;

use App\Enums\PartyKind;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier, paper party, mechanic or outside press.
 */
#[Fillable(['name', 'kind', 'phone', 'address', 'notes', 'is_active'])]
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => PartyKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @return HasMany<PaperReceipt, $this>
     */
    public function paperReceipts(): HasMany
    {
        return $this->hasMany(PaperReceipt::class);
    }

    /**
     * @return HasMany<PaperIssue, $this>
     */
    public function paperIssues(): HasMany
    {
        return $this->hasMany(PaperIssue::class);
    }

    /**
     * @return HasMany<MachineRepair, $this>
     */
    public function machineRepairs(): HasMany
    {
        return $this->hasMany(MachineRepair::class);
    }

    /**
     * @return HasMany<PlateJob, $this>
     */
    public function plateJobs(): HasMany
    {
        return $this->hasMany(PlateJob::class);
    }

    /**
     * @return HasMany<Dispatch, $this>
     */
    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class, 'buyer_id');
    }

    /**
     * @return HasMany<BuyerEntry, $this>
     */
    public function buyerEntries(): HasMany
    {
        return $this->hasMany(BuyerEntry::class);
    }

    /**
     * Whether any record already files this party under its kind, so the kind must not change.
     */
    public function hasHistory(): bool
    {
        return $this->purchases()->exists()
            || $this->paperReceipts()->exists()
            || $this->paperIssues()->exists()
            || $this->machineRepairs()->exists()
            || $this->plateJobs()->exists()
            || $this->dispatches()->exists()
            || $this->buyerEntries()->exists()
            || WorkOrder::query()->where(fn (Builder $q) => $q->where('party_id', $this->id)->orWhere('press_id', $this->id)->orWhere('buyer_id', $this->id))->exists();
    }
}
