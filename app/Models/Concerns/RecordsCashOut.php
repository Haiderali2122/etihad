<?php

namespace App\Models\Concerns;

use App\Enums\ApprovalStatus;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\User;
use App\Support\OtherOption;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * A payment made on its own tab (a purchase bill, repair, contractor or plate payment, loading labour, an
 * advance, loan or salaries) that also shows in Cash-out. All money goes out through the manager, so each
 * payment keeps one linked expense: same amount, date and approval status, under a matching expense head.
 * The expense is changed only from here; editing or deleting the payment updates or removes it.
 */
trait RecordsCashOut
{
    public static function bootRecordsCashOut(): void
    {
        static::saved(fn (self $model) => $model->syncCashOut());
        static::deleted(fn (self $model) => $model->cashOutExpense()->first()?->delete());
    }

    /**
     * @return MorphOne<Expense, $this>
     */
    public function cashOutExpense(): MorphOne
    {
        return $this->morphOne(Expense::class, 'source');
    }

    /**
     * What this payment puts in Cash-out, or null when nothing has been paid yet.
     *
     * @return array{amount: int, date: Carbon|string, paid_to: string, reason: string, head: string, category_id?: int|null}|null
     */
    abstract public function cashOut(): ?array;

    /**
     * Where the payment is seen and edited, shown as a link from Cash-out.
     */
    abstract public function cashOutUrl(): string;

    public function syncCashOut(): void
    {
        $data = $this->cashOut();
        $expense = $this->cashOutExpense()->first();

        if ($data === null || $data['amount'] <= 0) {
            $expense?->delete();

            return;
        }

        $userId = $this->created_by ?? User::query()->orderBy('id')->value('id');
        if ($userId === null) {
            return;
        }

        // Entries that wait for the owner pass their status on; the rest are paid when recorded.
        $status = in_array(RequiresApproval::class, class_uses_recursive($this), true) ? $this->status : ApprovalStatus::Approved;

        ($expense ?? new Expense)->forceFill([
            'source_type' => $this->getMorphClass(),
            'source_id' => $this->getKey(),
            'spent_on' => Carbon::parse($data['date'])->toDateString(),
            'user_id' => $userId,
            'created_by' => $userId,
            'expense_head_id' => OtherOption::findOrCreate(ExpenseHead::class, $data['head'])->getKey(),
            'inventory_category_id' => $data['category_id'] ?? null,
            'machine_id' => null,
            'paid_to' => mb_substr($data['paid_to'] ?: '—', 0, 255),
            'amount' => $data['amount'],
            'reason' => mb_substr($data['reason'], 0, 255),
            'status' => $status,
            'reviewed_by' => $this->reviewed_by ?? null,
            'reviewed_at' => $this->reviewed_at ?? null,
            'review_note' => $this->review_note ?? null,
        ])->saveQuietly();
    }
}
