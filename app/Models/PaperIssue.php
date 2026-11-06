<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\PaperIssueKind;
use App\Models\Concerns\RecordsDeletion;
use App\Models\Concerns\RequiresApproval;
use Database\Factories\PaperIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paper leaving the godown: to an in-house machine, to an outside press, or back to the supplier.
 *
 * The rims leave stock when issued (also while the entry waits for the owner); one the owner sends back drops out.
 * An in-house issue can be closed at the end of the shift with the rims that came back, which return to stock.
 * Paper issued by an approved work order is approved with it.
 */
#[Fillable(['issued_on', 'kind', 'party_id', 'paper_type_id', 'quantity', 'reference', 'book_format_id', 'book_name', 'copies', 'issued_to', 'employee_id', 'machine_id', 'wastage_percent', 'note', 'created_by'])]
class PaperIssue extends Model
{
    /** @use HasFactory<PaperIssueFactory> */
    use HasFactory, RecordsDeletion, RequiresApproval;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'kind' => PaperIssueKind::class,
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'settled_at' => 'datetime',
            'copies' => 'integer',
            'wastage_percent' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<PaperType, $this>
     */
    public function paperType(): BelongsTo
    {
        return $this->belongsTo(PaperType::class);
    }

    /**
     * @return BelongsTo<BookFormat, $this>
     */
    public function bookFormat(): BelongsTo
    {
        return $this->belongsTo(BookFormat::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    /**
     * Only paper given to an in-house machine comes back at the end of a shift; outside presses return
     * their leftover rims on the work order.
     */
    public function canBeSettled(): bool
    {
        return $this->kind === PaperIssueKind::InHouse && ! $this->isSettled() && $this->status !== ApprovalStatus::Rejected;
    }

    /**
     * Rims that stay out of stock: those issued less any returned at the end of the shift.
     */
    public function used(): int
    {
        return $this->quantity - $this->returned_quantity;
    }

    public function resendUrl(): string
    {
        return route('paper-issues.create', ['kind' => $this->kind->value]);
    }

    public function approvalKind(): string
    {
        return __('Paper issue');
    }

    public function approvalTitle(): string
    {
        return __(':qty rims :paper to :to', ['qty' => number_format($this->quantity), 'paper' => $this->paperType->code, 'to' => $this->issued_to]);
    }

    public function approvalDetail(): string
    {
        return collect([$this->kind->label(), $this->party->name, $this->book_name, $this->reference])->filter()->implode(' · ');
    }

    public function approvalAmount(): ?int
    {
        return null;
    }

    public function approvalIcon(): string
    {
        return 'paper';
    }

    public function approvalUrl(): string
    {
        return route('paper.index', ['tab' => 'issues']);
    }

    public function deletionBlocker(): ?string
    {
        $order = WorkOrder::query()->where('paper_issue_id', $this->id)->value('number');

        return $order ? __('This paper was issued for work order :no; change or delete that order instead.', ['no' => $order]) : null;
    }

    public function deletionLabel(): string
    {
        return number_format($this->quantity).' '.($this->paperType?->code ?? '').' — '.($this->party?->name ?? '').', '.$this->issued_on?->format('j M Y');
    }
}
