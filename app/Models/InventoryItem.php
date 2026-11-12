<?php

namespace App\Models;

use App\Models\Concerns\RecordsDeletion;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Something the press keeps in stock, e.g. "Black ink" counted in kg.
 * Stock in = purchases that weren't sent back; stock out = items moved out and not sent back, less what
 * came back at the end of the shift.
 *
 * @property float|null $stock_in
 * @property float|null $stock_out
 */
#[Fillable(['inventory_category_id', 'name', 'name_ur', 'unit', 'reorder_level', 'is_active'])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory, RecordsDeletion;

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
            ->withSum(['expenseLines as stock_in_expenses' => fn ($q) => $q->whereHas('expense', fn ($e) => $e->notRejected())], 'quantity')
            ->withSum(['issues as stock_out' => fn ($q) => $q->notRejected()], DB::raw('quantity - returned_quantity'));
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

    /**
     * Ink and glue lines bought through their tab's expense form.
     *
     * @return HasMany<ExpenseItem, $this>
     */
    public function expenseLines(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    /**
     * Everything that came in: purchases plus ink and glue bought through their tab (not sent back).
     */
    public function stockIn(): float
    {
        $this->loadStock();

        return round((float) $this->stock_in + (float) $this->stock_in_expenses, 2);
    }

    public function balance(): float
    {
        return round($this->stockIn() - (float) $this->stock_out, 2);
    }

    private function loadStock(): void
    {
        if (! array_key_exists('stock_in', $this->attributes)) {
            $this->loadSum(['purchases as stock_in' => fn ($q) => $q->notRejected()], 'quantity');
        }
        if (! array_key_exists('stock_in_expenses', $this->attributes)) {
            $this->loadSum(['expenseLines as stock_in_expenses' => fn ($q) => $q->whereHas('expense', fn ($e) => $e->notRejected())], 'quantity');
        }
        if (! array_key_exists('stock_out', $this->attributes)) {
            $this->loadSum(['issues as stock_out' => fn ($q) => $q->notRejected()], DB::raw('quantity - returned_quantity'));
        }
    }

    /**
     * "none" (never stocked), "ok", "low" (below reorder level) or "out" (nothing left).
     */
    public function level(): string
    {
        $balance = $this->balance();

        return match (true) {
            $this->stockIn() <= 0 && (float) $this->stock_out <= 0 => 'none',
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
