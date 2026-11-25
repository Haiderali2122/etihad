<?php

namespace App\Models;

use App\Models\Concerns\RecordsCashOut;
use App\Models\Concerns\RecordsDeletion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Money given to a plate processor. It clears their oldest unpaid plate bills first; anything more is kept as an
 * advance and the next plate bills use it up. It comes off the manager's cash once, for the whole amount.
 */
#[Fillable(['party_id', 'paid_on', 'amount', 'note', 'created_by'])]
class ProcessorPayment extends Model
{
    use RecordsCashOut, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Removed by mistake: the bills it paid are unpaid again (or use another advance the processor has).
        static::deleting(fn (self $payment) => $payment->billPayments()->get()->each->delete());
        static::deleted(fn (self $payment) => static::applyFor((int) $payment->party_id));
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * The parts of it used on plate bills.
     *
     * @return HasMany<BillPayment, $this>
     */
    public function billPayments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    public function used(): int
    {
        return (int) $this->billPayments()->sum('amount');
    }

    /**
     * What is still kept as an advance.
     */
    public function left(): int
    {
        return max(0, (int) $this->amount - $this->used());
    }

    /**
     * Uses the processor's money (oldest first) on their unpaid plate bills (oldest first). Run after money is given,
     * a plate bill is added or changed, or either is deleted.
     */
    public static function applyFor(int $partyId): void
    {
        DB::transaction(function () use ($partyId): void {
            $payments = static::query()->where('party_id', $partyId)->orderBy('paid_on')->orderBy('id')->get()
                ->filter(fn (self $p) => $p->left() > 0);
            if ($payments->isEmpty()) {
                return;
            }
            $jobs = PlateJob::query()->where('party_id', $partyId)->whereColumn('paid_amount', '<', 'amount')->orderBy('job_on')->orderBy('id')->get();

            foreach ($payments as $payment) {
                $left = $payment->left();
                foreach ($jobs as $job) {
                    if ($left <= 0) {
                        break;
                    }
                    $part = min($left, $job->unpaid());
                    if ($part <= 0) {
                        continue;
                    }
                    // Dated when both the money and the bill were there.
                    $on = $job->job_on->greaterThan($payment->paid_on) ? $job->job_on : $payment->paid_on;
                    $job->payments()->create([
                        'processor_payment_id' => $payment->id, 'paid_on' => $on->toDateString(), 'amount' => $part,
                        'note' => $job->job_on->greaterThan($payment->paid_on) ? __('From advance of :date', ['date' => $payment->paid_on->format('j M')]) : null,
                        'created_by' => $payment->created_by,
                    ]);
                    $job->refresh();
                    $left -= $part;
                }
            }
        });
    }

    public function cashOut(): ?array
    {
        return [
            'amount' => (int) $this->amount,
            'date' => $this->paid_on,
            'paid_to' => (string) $this->processor?->name,
            'reason' => trim('Plate processor payment'.($this->note ? ', '.$this->note : '')),
            'head' => 'Plates',
        ];
    }

    public function cashOutUrl(): string
    {
        return route('plate-jobs.index', ['processor' => $this->party_id]);
    }

    public function cashOutSource(): string
    {
        return __('Plate processor payment');
    }

    /**
     * Comes off the manager's cash, whoever enters it.
     */
    protected function cashOutPayer(): ?int
    {
        return $this->managerWithCash();
    }

    public function deletionKind(): string
    {
        return __('Plate processor payment');
    }

    public function deletionLabel(): string
    {
        return ($this->processor?->name ?? '').' Rs '.number_format($this->amount).', '.$this->paid_on?->format('j M Y');
    }
}
