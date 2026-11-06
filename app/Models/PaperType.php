<?php

namespace App\Models;

use App\Models\Concerns\RecordsDeletion;
use Database\Factories\PaperTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A paper type and size held in the godown, e.g. "AA printing 19.75 × 29.75, 68 gsm".
 * Counted in rims of 500 sheets, or packets of 100 for bleach card.
 */
#[Fillable(['code', 'name', 'name_ur', 'gsm', 'size', 'unit', 'sheets_per_unit', 'reorder_level', 'is_active'])]
class PaperType extends Model
{
    /** @use HasFactory<PaperTypeFactory> */
    use HasFactory, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'gsm' => 'integer',
            'sheets_per_unit' => 'integer',
            'reorder_level' => 'integer',
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
    public function receipts(): HasMany
    {
        return $this->hasMany(PaperReceipt::class);
    }

    /**
     * @return HasMany<PaperIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(PaperIssue::class);
    }

    public function label(): string
    {
        return trim("{$this->name}, {$this->size}".($this->gsm ? " × {$this->gsm}" : ''));
    }

    public function deletionBlocker(): ?string
    {
        $used = $this->receipts()->exists() || $this->issues()->exists()
            || WorkOrder::query()->where('paper_type_id', $this->id)->exists()
            || BookFormat::query()->where('paper_type_id', $this->id)->exists();

        return $used ? __(':name has stock or orders recorded against it. Mark it inactive instead.', ['name' => $this->code]) : null;
    }

    public function deletionLabel(): string
    {
        return trim($this->code.' '.$this->name);
    }
}
