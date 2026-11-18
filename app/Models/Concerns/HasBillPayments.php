<?php

namespace App\Models\Concerns;

use App\Models\BillPayment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Something paid for in parts (a material bill or a plate job): its payments, the paid total kept on the record
 * as paid_amount, and what is still unpaid. Deleting the record removes its payments one by one so each also
 * leaves Cash-out.
 */
trait HasBillPayments
{
    public static function bootHasBillPayments(): void
    {
        static::deleting(fn (self $model) => $model->payments()->get()->each(function (BillPayment $payment): void {
            $payment->removedWithBill = true;
            $payment->delete();
        }));
    }

    /**
     * @return MorphMany<BillPayment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(BillPayment::class, 'payable')->orderBy('paid_on')->orderBy('id');
    }

    /**
     * The amount the record is for.
     */
    abstract public function billTotal(): int;

    public function unpaid(): int
    {
        return max(0, $this->billTotal() - (int) $this->paid_amount);
    }

    public function isFullyPaid(): bool
    {
        return $this->billTotal() > 0 && $this->unpaid() === 0;
    }

    public function syncPaid(): void
    {
        $this->forceFill(['paid_amount' => (int) $this->payments()->sum('amount')])->saveQuietly();
    }

    /**
     * Pay part or all of what is unpaid.
     */
    public function pay(int $amount, mixed $on, ?int $by = null, ?string $note = null): ?BillPayment
    {
        $amount = min($amount, $this->unpaid());
        if ($amount <= 0) {
            return null;
        }

        return $this->payments()->create(['paid_on' => $on, 'amount' => $amount, 'note' => $note, 'created_by' => $by]);
    }
}
