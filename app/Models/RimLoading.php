<?php

namespace App\Models;

use App\Enums\RimMove;
use App\Models\Concerns\RecordsCashOut;
use App\Models\Concerns\RecordsDeletion;
use Database\Factories\RimLoadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in Younus ka khata: rims his labourers moved (godown → our press, our press → folding, godown → outside
 * press, outside press → back) or the outside printing charge he takes to the press. Amount = rims × rate per rim.
 * Once paid it shows in Cash-out.
 */
#[Fillable(['work_order_id', 'paper_receipt_id', 'kind', 'work_type', 'details', 'loaded_on', 'rims', 'unit', 'rate_per_rim', 'amount', 'loaded_by', 'paid_on', 'note', 'created_by'])]
class RimLoading extends Model
{
    /** @use HasFactory<RimLoadingFactory> */
    use HasFactory, RecordsCashOut, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'kind' => RimMove::class,
            'loaded_on' => 'date',
            'paid_on' => 'date',
            'rims' => 'decimal:2',
            'rate_per_rim' => 'decimal:2',
            'amount' => 'integer',
        ];
    }

    public static function price(float $rims, float $ratePerRim): int
    {
        return (int) round($rims * $ratePerRim);
    }

    /**
     * The rate used last time for this kind of line, offered as the starting rate in the entry box.
     */
    public static function lastRate(RimMove $kind): ?float
    {
        $rate = self::query()->where('kind', $kind)->latest('id')->value('rate_per_rim');

        return $rate === null ? null : (float) $rate;
    }

    /**
     * A line the manager entered for an order: rims × rate per rim.
     */
    public static function record(WorkOrder $order, RimMove $kind, float $rims, float $rate, mixed $on = null, ?int $by = null): ?self
    {
        if ($rims <= 0) {
            return null;
        }

        return $order->rimLoadings()->create([
            'kind' => $kind, 'loaded_on' => $on ?? today(), 'rims' => $rims, 'rate_per_rim' => $rate,
            'amount' => self::price($rims, $rate), 'loaded_by' => 'Younus', 'created_by' => $by,
        ]);
    }

    /**
     * What the work was: the typed name, or the kind's name.
     */
    public function workLabel(): string
    {
        return $this->work_type ?: ($this->kind?->label() ?? __('Other work'));
    }

    /**
     * @return BelongsTo<PaperReceipt, $this>
     */
    public function paperReceipt(): BelongsTo
    {
        return $this->belongsTo(PaperReceipt::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    /**
     * Labour paid to Younus for this loading, once it has been paid.
     */
    public function cashOut(): ?array
    {
        if (! $this->paid_on) {
            return null;
        }

        return [
            'amount' => (int) $this->amount,
            'date' => $this->paid_on,
            'paid_to' => $this->loaded_by,
            'reason' => $this->workLabel().($this->workOrder ? ' for '.$this->workOrder->number : '').' ('.rtrim(rtrim(number_format((float) $this->rims, 2), '0'), '.').' '.$this->unit.')',
            'head' => $this->kind?->head() ?? 'Loading labour',
        ];
    }

    public function cashOutUrl(): string
    {
        return route('rim-loadings.index');
    }

    public function deletionBlocker(): ?string
    {
        return $this->isPaid() ? __('A paid loading cannot be deleted.') : null;
    }

    protected static function booted(): void
    {
        // Rims brought into the godown leave stock again when their line is removed.
        static::deleted(fn (self $line) => $line->paperReceipt?->delete());
    }

    public function deletionLabel(): string
    {
        return $this->workLabel().' — '.($this->workOrder?->number ?? '').' ('.$this->rims.' '.$this->unit.', Rs '.number_format($this->amount).')';
    }
}
