<?php

namespace App\Models;

use App\Enums\ContractorJobKind;
use App\Models\Concerns\RecordsDeletion;
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
 * - Binding: ((forms × rate per form) + binding rate) × copies in thousands (20,000 books count as 20)
 *
 * Extra work ticked on the job (sticker pasting, numbering, pinning) adds its rate × copies ÷ 1000 on top.
 */
#[Fillable(['contractor_id', 'work_order_id', 'kind', 'job_on', 'forms', 'copies', 'folding_rate', 'form_rate', 'copy_rate', 'sticker_rate', 'numbering_rate', 'pinning_rate', 'amount', 'state', 'done_on', 'note', 'created_by'])]
class ContractorJob extends Model
{
    /** @use HasFactory<ContractorJobFactory> */
    use HasFactory, RecordsDeletion;

    /**
     * Extra work that can be ticked on a job, each charged per 1,000 books, with the rate filled in to start from.
     * Pinning has no fixed rate; it is typed in for each job.
     *
     * @var array<string, array{label: string, default: int|null}>
     */
    public const Extras = [
        'sticker' => ['label' => 'Sticker pasting', 'default' => 200],
        'numbering' => ['label' => 'Numbering', 'default' => 65],
        'pinning' => ['label' => 'Pinning', 'default' => null],
    ];

    /**
     * The ticked extra work's rates added together (per 1,000 books).
     */
    public function extrasRate(): float
    {
        return collect(array_keys(self::Extras))->sum(fn (string $extra) => (float) $this->{$extra.'_rate'});
    }

    protected function casts(): array
    {
        return [
            'kind' => ContractorJobKind::class,
            'done_on' => 'date',
            'job_on' => 'date',
            'forms' => 'decimal:2',
            'copies' => 'integer',
            'folding_rate' => 'decimal:2',
            'form_rate' => 'decimal:2',
            'copy_rate' => 'decimal:2',
            'sticker_rate' => 'decimal:2',
            'numbering_rate' => 'decimal:2',
            'pinning_rate' => 'decimal:2',
            'amount' => 'integer',
        ];
    }

    public static function price(ContractorJobKind $kind, float $forms, int $copies, float $foldingRate = 0, float $formRate = 0, float $copyRate = 0, float $extrasRate = 0): int
    {
        return PressMath::roundToTen(match ($kind) {
            ContractorJobKind::Folding => $forms * $copies / 1000 * $foldingRate,
            ContractorJobKind::Binding => ($forms * $formRate + $copyRate) * $copies / 1000,
        } + $extrasRate * $copies / 1000);
    }

    /**
     * This job's amount from its own forms, copies and rates.
     */
    public function workOutAmount(): int
    {
        return self::price($this->kind, (float) $this->forms, (int) $this->copies,
            (float) $this->folding_rate, (float) $this->form_rate, (float) $this->copy_rate, $this->extrasRate());
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

        $work = $this->kind === ContractorJobKind::Folding
            ? "{$qty($this->forms)} × ".number_format($this->copies)." ÷ 1000 × {$qty($this->folding_rate)}"
            : "({$qty($this->forms)} × {$qty($this->form_rate)} + {$qty($this->copy_rate)}) × ".number_format($this->copies).' ÷ 1000';

        $extras = collect(array_keys(self::Extras))->filter(fn (string $extra) => $this->{$extra.'_rate'} !== null)
            ->map(fn (string $extra) => $qty($this->{$extra.'_rate'}));

        return $extras->isEmpty() ? $work : $work.' + ('.$extras->join(' + ').') × '.number_format($this->copies).' ÷ 1000';
    }

    /**
     * The extra work ticked on this job, e.g. ["Sticker pasting: Rs 200", "Numbering: Rs 65"] (per 1,000 books).
     *
     * @return list<string>
     */
    public function extrasDone(): array
    {
        return collect(self::Extras)->filter(fn (array $extra, string $key) => $this->{$key.'_rate'} !== null)
            ->map(fn (array $extra, string $key) => __($extra['label']).': Rs '.InventoryItem::qty($this->{$key.'_rate'}))
            ->values()->all();
    }

    public function deletionLabel(): string
    {
        return $this->kind->label().' — '.($this->contractor?->name ?? '').($this->workOrder ? ' ('.$this->workOrder->number.')' : '');
    }
}
