<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member of press staff. Employees are records, not logins.
 * The code matches the employee's number on the biometric device.
 */
#[Fillable(['code', 'name', 'name_ur', 'father_name', 'cnic', 'phone', 'designation', 'foreman_id', 'is_foreman', 'monthly_salary', 'joined_on', 'left_on', 'is_active', 'notes'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_foreman' => 'boolean',
            'is_active' => 'boolean',
            'monthly_salary' => 'integer',
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function foreman(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'foreman_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function team(): HasMany
    {
        return $this->hasMany(Employee::class, 'foreman_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<SalaryAdvance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    /**
     * Whether any attendance, advance, loan or payroll line has ever been recorded for this person.
     */
    public function hasHistory(): bool
    {
        return $this->attendances()->exists() || $this->advances()->exists()
            || $this->loans()->exists() || $this->payrollLines()->exists();
    }

    /**
     * Advances handed out that no payroll has deducted yet.
     */
    public function outstandingAdvances(): int
    {
        return (int) $this->advances()->notRejected()->whereNull('payroll_run_id')->sum('amount');
    }

    /**
     * What is still owed across all loans.
     */
    public function loanBalance(): int
    {
        return (int) $this->loans()->notRejected()->get()->sum(fn (Loan $loan) => $loan->balance());
    }

    public function localName(): string
    {
        return app()->getLocale() === 'ur' && $this->name_ur ? $this->name_ur : $this->name;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($words[0] ?? '', 0, 1);
        $last = count($words) > 1 ? mb_substr(end($words), 0, 1) : mb_substr($words[0] ?? '', 1, 1);

        return mb_strtoupper($first.$last);
    }
}
