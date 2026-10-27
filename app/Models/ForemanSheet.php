<?php

namespace App\Models;

use App\Models\Concerns\RequiresApproval;
use Database\Factories\ForemanSheetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cash given to a foreman. What he hands to named workers becomes their advances;
 * the rest stays as the foreman's own advance.
 */
#[Fillable(['given_on', 'foreman_id', 'amount', 'note', 'created_by'])]
class ForemanSheet extends Model
{
    /** @use HasFactory<ForemanSheetFactory> */
    use HasFactory, RequiresApproval;

    /**
     * When the owner approves or sends back the sheet, its advances follow.
     */
    protected static function booted(): void
    {
        static::updated(function (self $sheet): void {
            if ($sheet->wasChanged('status')) {
                SalaryAdvance::syncWithSheet($sheet);
            }
        });
    }

    protected function casts(): array
    {
        return [
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

    /**
     * @return HasMany<SalaryAdvance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    public function approvalKind(): string
    {
        return __('Foreman advance sheet');
    }

    public function approvalTitle(): string
    {
        return __(':name, :count people', ['name' => $this->foreman->name, 'count' => $this->advances()->count()]);
    }

    public function approvalDetail(): string
    {
        return __('Cash given to the foreman on :date', ['date' => $this->given_on->format('j M Y')]);
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
        return route('foreman-sheets.show', $this);
    }
}
