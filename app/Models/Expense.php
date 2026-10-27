<?php

namespace App\Models;

use App\Models\Concerns\RequiresApproval;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One spend from the cash the owner gave out — the digital roznamcha line.
 */
#[Fillable(['spent_on', 'user_id', 'expense_head_id', 'machine_id', 'paid_to', 'amount', 'reason', 'attachment', 'created_by', 'status'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, RequiresApproval;

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ExpenseHead, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(ExpenseHead::class, 'expense_head_id');
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function approvalKind(): string
    {
        return __('Expense');
    }

    public function approvalTitle(): string
    {
        return "{$this->head->localName()}: {$this->reason}";
    }

    public function approvalDetail(): string
    {
        return __('Paid to :to, over the daily limit', ['to' => $this->paid_to]);
    }

    public function approvalAmount(): ?int
    {
        return $this->amount;
    }

    public function approvalIcon(): string
    {
        return 'wallet';
    }

    public function approvalUrl(): string
    {
        return route('expenses.index');
    }
}
