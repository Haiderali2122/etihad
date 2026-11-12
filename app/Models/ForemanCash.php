<?php

namespace App\Models;

use App\Models\Concerns\RecordsCashOut;
use Database\Factories\ForemanCashFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cash the manager gave a foreman before a payday, to hand out to his team as kharcha. It comes off the
 * manager's cash; what the team took is then subtracted from their pay, and what is left stays with the foreman.
 */
#[Fillable(['foreman_id', 'month', 'payday', 'given_on', 'amount', 'created_by'])]
class ForemanCash extends Model
{
    /** @use HasFactory<ForemanCashFactory> */
    use HasFactory, RecordsCashOut;

    public const Advance = 'advance';

    public const Final = 'final';

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'given_on' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function foreman(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'foreman_id');
    }

    public function cashOut(): ?array
    {
        return [
            'amount' => (int) $this->amount,
            'date' => $this->given_on,
            'paid_to' => (string) $this->foreman?->name,
            'reason' => 'Cash to foreman for '.($this->payday === self::Advance ? 'advance salaries' : 'salaries').' '.$this->month->format('F Y'),
            'head' => $this->payday === self::Advance ? 'Salary advances' : 'Salaries',
        ];
    }

    public function cashOutUrl(): string
    {
        return $this->payday === self::Advance
            ? route('salary-advances.mid-month', ['month' => $this->month->format('Y-m')])
            : route('payroll.index');
    }
}
