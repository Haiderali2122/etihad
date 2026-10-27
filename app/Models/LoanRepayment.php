<?php

namespace App\Models;

use Database\Factories\LoanRepaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An instalment taken from salary by payroll, or paid back in cash.
 */
#[Fillable(['loan_id', 'paid_on', 'amount', 'payroll_run_id', 'note', 'created_by'])]
class LoanRepayment extends Model
{
    /** @use HasFactory<LoanRepaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
