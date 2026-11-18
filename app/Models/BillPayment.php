<?php

namespace App\Models;

use App\Models\Concerns\RecordsCashOut;
use App\Models\Concerns\RecordsDeletion;
use Database\Factories\BillPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One payment, on one date, toward a Diesel / Glue / Ink bill or a plate job. A bill can be paid in parts; what
 * is not paid yet stays on it as unpaid. Each payment comes off the manager's cash in Cash-out.
 */
#[Fillable(['payable_type', 'payable_id', 'paid_on', 'amount', 'note', 'created_by'])]
class BillPayment extends Model
{
    /** @use HasFactory<BillPaymentFactory> */
    use HasFactory, RecordsCashOut, RecordsDeletion;

    /**
     * Set when the bill itself is deleted: the payment goes with it and only the bill is logged.
     */
    public bool $removedWithBill = false;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The bill's paid total (and whether it is fully paid) follows its payments.
        static::saved(fn (self $p) => $p->payable?->syncPaid());
        static::deleted(fn (self $p) => $p->removedWithBill ? null : $p->payable?->syncPaid());
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function cashOut(): ?array
    {
        $payable = $this->payable;
        if (! $payable) {
            return null;
        }

        if ($payable instanceof PlateJob) {
            return [
                'amount' => (int) $this->amount,
                'date' => $this->paid_on,
                'paid_to' => (string) $payable->processor?->name,
                'reason' => trim('Plates: '.$payable->plates.($payable->book_name ? ' for '.$payable->book_name : '').($this->note ? ', '.$this->note : '')),
                'head' => 'Plates',
            ];
        }

        return [
            'amount' => (int) $this->amount,
            'date' => $this->paid_on,
            'paid_to' => (string) ($payable->supplier ?: '—'),
            'reason' => trim($payable->category?->name.' bill '.$payable->receiptNumber().($this->note ? ', '.$this->note : '')),
            'head' => (string) $payable->category?->name,
            'category_id' => $payable->inventory_category_id,
        ];
    }

    public function cashOutUrl(): string
    {
        return $this->payable instanceof PlateJob
            ? route('plate-jobs.index', ['show' => 'all'])
            : route('inventory.index', ['tab' => 'material-'.($this->payable?->inventory_category_id ?? 0)]);
    }

    /**
     * Payments come off the manager's cash, whoever enters them.
     */
    protected function cashOutPayer(): ?int
    {
        return $this->managerWithCash();
    }

    public function cashOutSource(): string
    {
        return $this->payable instanceof PlateJob ? __('Plate bill') : __(':name bill', ['name' => $this->payable?->category?->name]);
    }

    public function logsDeletion(): bool
    {
        return ! $this->removedWithBill;
    }

    public function deletionLabel(): string
    {
        return __('Payment').' Rs '.number_format($this->amount).', '.$this->paid_on?->format('j M Y');
    }
}
