<?php

namespace App\Models;

use App\Models\Concerns\HasBillPayments;
use App\Models\Concerns\RecordsDeletion;
use Database\Factories\PlateJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plates made by a plate processor and its bill, paid in parts. Each payment (BillPayment) is dated and comes off
 * the manager's cash; paid_on is set once the bill is fully paid.
 */
#[Fillable(['party_id', 'work_order_id', 'book_name', 'plate_size', 'job_on', 'plates', 'returned_plates', 'returned_on', 'rate', 'amount', 'paid_amount', 'paid_on', 'payment_note', 'note', 'created_by'])]
class PlateJob extends Model
{
    /** @use HasFactory<PlateJobFactory> */
    use HasBillPayments, HasFactory, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'job_on' => 'date',
            'paid_on' => 'date',
            'plates' => 'integer',
            'returned_plates' => 'integer',
            'returned_on' => 'date',
            'rate' => 'decimal:2',
            'amount' => 'integer',
            'paid_amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // A changed amount can make the bill fully paid or unpaid again.
        // A job saved as already paid gets one payment for the whole amount.
        static::created(function (self $job) {
            if ($job->paid_on && (int) $job->amount > 0) {
                $job->pay((int) $job->amount, $job->paid_on, $job->created_by, $job->payment_note);
            }
        });
        static::saving(function (self $job) {
            if ($job->isDirty('amount') && $job->exists) {
                $job->paid_on = $job->fullyPaidOn();
            }
        });
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    public function billTotal(): int
    {
        return (int) $this->amount;
    }

    /**
     * The paid total follows the payments; the bill counts as paid on the date of the payment that cleared it.
     */
    public function syncPaid(): void
    {
        $this->paid_amount = (int) $this->payments()->sum('amount');
        $this->paid_on = $this->fullyPaidOn();
        $this->saveQuietly();
    }

    private function fullyPaidOn(): ?string
    {
        if ((int) $this->amount <= 0 || (int) $this->paid_amount < (int) $this->amount) {
            return null;
        }

        return $this->payments()->max('paid_on');
    }

    /**
     * Each time plates came back, with its date.
     *
     * @return HasMany<PlateReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(PlateReturn::class)->orderBy('returned_on')->orderBy('id');
    }

    /**
     * The returned total and last return date follow the individual returns.
     */
    public function syncReturned(): void
    {
        $this->forceFill([
            'returned_plates' => (int) $this->returns()->sum('plates'),
            'returned_on' => $this->returns()->max('returned_on'),
        ])->saveQuietly();
    }

    /**
     * Plates still with the processor: given out less those returned.
     */
    public function platesHeld(): int
    {
        return max(0, $this->plates - $this->returned_plates);
    }

    public function deletionLabel(): string
    {
        return trim(($this->processor?->name ?? '').', '.$this->plates.' plates'.($this->book_name ? ' for '.$this->book_name : '').', Rs '.number_format($this->amount), ', ');
    }
}
