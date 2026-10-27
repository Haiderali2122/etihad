<?php

namespace App\Models;

use App\Models\Concerns\RequiresApproval;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A staff loan repaid in monthly instalments taken from salary.
 * The instalment can be changed or skipped in any month's payroll.
 */
#[Fillable(['employee_id', 'issued_on', 'amount', 'monthly_instalment', 'first_instalment_month', 'note', 'created_by'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory, RequiresApproval;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'first_instalment_month' => 'date',
            'amount' => 'integer',
            'monthly_instalment' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<LoanRepayment, $this>
     */
    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function repaid(): int
    {
        return (int) ($this->repayments_sum_amount ?? $this->repayments()->sum('amount'));
    }

    public function balance(): int
    {
        return max(0, $this->amount - $this->repaid());
    }

    public function isCleared(): bool
    {
        return $this->balance() === 0;
    }

    public function approvalKind(): string
    {
        return __('Staff loan');
    }

    public function approvalTitle(): string
    {
        return __('Loan for :name', ['name' => $this->employee->name]);
    }

    public function approvalDetail(): string
    {
        return __('Rs :n a month from :month', ['n' => number_format($this->monthly_instalment), 'month' => $this->first_instalment_month->translatedFormat('F Y')]);
    }

    public function approvalAmount(): ?int
    {
        return $this->amount;
    }

    public function approvalIcon(): string
    {
        return 'users';
    }

    public function approvalUrl(): string
    {
        return route('loans.show', $this);
    }
}
