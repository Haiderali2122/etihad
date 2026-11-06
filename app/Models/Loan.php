<?php

namespace App\Models;

use App\Models\Concerns\RecordsCashOut;
use App\Models\Concerns\RecordsDeletion;
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
    use HasFactory, RecordsCashOut, RecordsDeletion, RequiresApproval;

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

    /**
     * A loan that already has money paid back against it cannot be sent back.
     */
    public function rejectionBlocker(): ?string
    {
        return $this->repayments()->exists() ? __('Repayments are already recorded against this loan, so it cannot be sent back.') : null;
    }

    public function resendUrl(): string
    {
        return route('loans.create', ['employee' => $this->employee_id]);
    }

    public function resendInput(): array
    {
        return ['first_instalment_month' => $this->first_instalment_month->format('Y-m')] + $this->baseResendInput();
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

    /**
     * The loan handed to the employee.
     */
    public function cashOut(): ?array
    {
        return [
            'amount' => (int) $this->amount,
            'date' => $this->issued_on,
            'paid_to' => (string) $this->employee?->name,
            'reason' => 'Staff loan',
            'head' => 'Staff loans',
        ];
    }

    public function cashOutUrl(): string
    {
        return route('loans.show', $this);
    }

    public function deletionBlocker(): ?string
    {
        return $this->repayments()->whereNotNull('payroll_run_id')->exists()
            ? __('Instalments of this loan were already deducted in a finalized payroll. Reopen that payroll first.')
            : null;
    }

    public function deletionLabel(): string
    {
        return ($this->employee?->name ?? '').' — Rs '.number_format($this->amount).', '.$this->issued_on?->format('j M Y');
    }
}
