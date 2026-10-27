<?php

namespace App\Models;

use App\Enums\AdvanceKind;
use App\Models\Concerns\RequiresApproval;
use Database\Factories\SalaryAdvanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cash paid to an employee ahead of payday; the next payroll deducts it.
 * Advances that belong to a foreman sheet follow the sheet's approval.
 */
#[Fillable(['employee_id', 'given_on', 'amount', 'kind', 'foreman_sheet_id', 'note', 'created_by', 'status'])]
class SalaryAdvance extends Model
{
    /** @use HasFactory<SalaryAdvanceFactory> */
    use HasFactory, RequiresApproval;

    protected function casts(): array
    {
        return [
            'given_on' => 'date',
            'amount' => 'integer',
            'kind' => AdvanceKind::class,
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
     * @return BelongsTo<ForemanSheet, $this>
     */
    public function foremanSheet(): BelongsTo
    {
        return $this->belongsTo(ForemanSheet::class);
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function isDeducted(): bool
    {
        return $this->payroll_run_id !== null;
    }

    public function approvalKind(): string
    {
        return $this->kind->label();
    }

    public function approvalTitle(): string
    {
        return __('Advance for :name', ['name' => $this->employee->name]);
    }

    public function approvalDetail(): string
    {
        return __('Given on :date, deducted from the next salary', ['date' => $this->given_on->format('j M Y')]);
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
        return route('salary-advances.index');
    }

    /**
     * Keep a foreman sheet's advances in step with the sheet's approval.
     */
    public static function syncWithSheet(ForemanSheet $sheet): void
    {
        $sheet->advances()->update([
            'status' => $sheet->status,
            'reviewed_by' => $sheet->reviewed_by,
            'reviewed_at' => $sheet->reviewed_at,
        ]);
    }
}
