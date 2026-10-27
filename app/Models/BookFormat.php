<?php

namespace App\Models;

use App\Support\PressMath;
use Database\Factories\BookFormatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One way a book is printed: its size, number of forms (فارمے) per copy, default paper and contractor rates.
 */
#[Fillable(['name', 'size', 'forms', 'pages', 'paper_type_id', 'folding_rate', 'binding_rate'])]
class BookFormat extends Model
{
    /** @use HasFactory<BookFormatFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'forms' => 'decimal:2',
            'pages' => 'integer',
            'folding_rate' => 'decimal:2',
            'binding_rate' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return BelongsTo<PaperType, $this>
     */
    public function paperType(): BelongsTo
    {
        return $this->belongsTo(PaperType::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Folding amount: (forms × copies ÷ 1000) × folding rate, rounded to the nearest 10.
     */
    public function foldingAmount(int $copies): ?int
    {
        return $this->folding_rate === null ? null : PressMath::roundToTen((float) $this->forms * $copies / 1000 * (float) $this->folding_rate);
    }

    public function formsLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->forms, 2), '0'), '.');
    }
}
