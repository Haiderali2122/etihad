<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the press keeps in stock, e.g. "Black ink" counted in kg.
 * Stock in = purchases that weren't sent back; stock out = items moved out.
 *
 * @property float|null $stock_in
 * @property float|null $stock_out
 */
#[Fillable(['inventory_category_id', 'name', 'name_ur', 'unit', 'reorder_level', 'is_active'])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Adds stock_in and stock_out sums so balance() needs no extra queries.
     */
    public function scopeWithStock(Builder $query): void
    {
        $query->withSum(['purchases as stock_in' => fn ($q) => $q->notRejected()], 'quantity')
            ->withSum('issues as stock_out', 'quantity');
    }

    /**
     * @return BelongsTo<InventoryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @return HasMany<InventoryIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(InventoryIssue::class);
    }

    public function balance(): float
    {
        if (! array_key_exists('stock_in', $this->attributes)) {
            $this->loadSum(['purchases as stock_in' => fn ($q) => $q->notRejected()], 'quantity')
                ->loadSum('issues as stock_out', 'quantity');
        }

        return round((float) $this->stock_in - (float) $this->stock_out, 2);
    }

    /**
     * "none" (never stocked), "ok", "low" (below reorder level) or "out" (nothing left).
     */
    public function level(): string
    {
        $balance = $this->balance();

        return match (true) {
            (float) $this->stock_in <= 0 && (float) $this->stock_out <= 0 => 'none',
            $balance <= 0 => 'out',
            $balance < (float) $this->reorder_level => 'low',
            default => 'ok',
        };
    }

    public function localName(): string
    {
        return app()->getLocale() === 'ur' && $this->name_ur ? $this->name_ur : $this->name;
    }

    /**
     * Format a quantity without trailing zeros: 25.00 → "25", 2.50 → "2.5".
     */
    public static function qty(float|string|null $value): string
    {
        $formatted = number_format((float) $value, 2);

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
