<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One month's payroll. While a draft it can be recalculated and edited; once finalized,
 * the advances and loan instalments it deducted are marked as settled.
 *
 * @property array{ot_divisor: float, ot_multiplier: float, standard_hours: float, unmarked_as: string} $rules
 */
#[Fillable(['month', 'pay_date', 'status', 'rules', 'created_by'])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'pay_date' => 'date',
            'status' => PayrollStatus::class,
            'rules' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * The oldest month before this one whose payroll is still a draft, if any.
     */
    public function earlierDraft(): ?PayrollRun
    {
        return static::query()->where('status', PayrollStatus::Draft)
            ->whereDate('month', '<', $this->month)->oldest('month')->first();
    }

    public function isDraft(): bool
    {
        return $this->status === PayrollStatus::Draft;
    }

    public function label(): string
    {
        return $this->month->translatedFormat('F Y');
    }
}
