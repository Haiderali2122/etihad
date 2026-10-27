<?php

namespace App\Models;

use App\Enums\ContractorJobKind;
use App\Support\PressMath;
use Database\Factories\ContractorJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Folding or binding work given to a contractor, priced with the client's formulas:
 *
 * - Folding: (forms × copies ÷ 1000) × folding rate
 * - Binding: ((forms × rate per form) + binding rate) × copies
 */
#[Fillable(['contractor_id', 'work_order_id', 'kind', 'job_on', 'forms', 'copies', 'folding_rate', 'form_rate', 'copy_rate', 'amount', 'state', 'note', 'created_by'])]
class ContractorJob extends Model
{
    /** @use HasFactory<ContractorJobFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => ContractorJobKind::class,
            'job_on' => 'date',
            'forms' => 'decimal:2',
            'copies' => 'integer',
            'folding_rate' => 'decimal:2',
            'form_rate' => 'decimal:2',
            'copy_rate' => 'decimal:2',
            'amount' => 'integer',
        ];
    }

    public static function price(ContractorJobKind $kind, float $forms, int $copies, float $foldingRate = 0, float $formRate = 0, float $copyRate = 0): int
    {
        return PressMath::roundToTen(match ($kind) {
            ContractorJobKind::Folding => $forms * $copies / 1000 * $foldingRate,
            ContractorJobKind::Binding => ($forms * $formRate + $copyRate) * $copies,
        });
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function isDone(): bool
    {
        return $this->state === 'done';
    }

    public function formula(): string
    {
        $qty = fn ($v) => InventoryItem::qty($v);

        return $this->kind === ContractorJobKind::Folding
            ? "{$qty($this->forms)} × ".number_format($this->copies)." ÷ 1000 × {$qty($this->folding_rate)}"
            : "({$qty($this->forms)} × {$qty($this->form_rate)} + {$qty($this->copy_rate)}) × ".number_format($this->copies);
    }
}
