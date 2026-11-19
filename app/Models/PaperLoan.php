<?php

namespace App\Models;

use App\Models\Concerns\RecordsDeletion;
use Database\Factories\PaperLoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rims an order party owes because its paper fell short: they were taken from another stock (the lender).
 * Settled with rims (moved from the borrower's stock back to the lender's) or with money.
 */
#[Fillable(['borrowed_on', 'paper_type_id', 'borrower_party_id', 'lender_party_id', 'rims', 'paper_issue_id', 'cover_issue_id', 'settled_on', 'settled_how', 'return_party_id', 'amount', 'note', 'created_by', 'settled_by'])]
class PaperLoan extends Model
{
    /** @use HasFactory<PaperLoanFactory> */
    use HasFactory, RecordsDeletion;

    public const SettledWithRims = 'rims';

    public const SettledWithMoney = 'money';

    protected function casts(): array
    {
        return [
            'borrowed_on' => 'date',
            'settled_on' => 'date',
            'rims' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('settled_on');
    }

    public function isOpen(): bool
    {
        return $this->settled_on === null;
    }

    /**
     * @return BelongsTo<PaperType, $this>
     */
    public function paperType(): BelongsTo
    {
        return $this->belongsTo(PaperType::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'borrower_party_id');
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function lender(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'lender_party_id');
    }

    /**
     * Where returned rims went: the stock they came from unless another was picked.
     *
     * @return BelongsTo<Party, $this>
     */
    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'return_party_id');
    }

    public function returnPartyId(): int
    {
        return (int) ($this->return_party_id ?: $this->lender_party_id);
    }

    /**
     * @return BelongsTo<PaperIssue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(PaperIssue::class, 'paper_issue_id');
    }

    public function deletionLabel(): string
    {
        return $this->borrower?->name.' '.__('owes').' '.$this->rims.' '.__('rims').' '.$this->paperType?->label().' '.__('to').' '.$this->lender?->name;
    }
}
