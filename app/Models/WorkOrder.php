<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ContractorJobKind;
use App\Enums\PaperIssueKind;
use App\Enums\PrintMode;
use App\Enums\WorkOrderStage;
use App\Models\Concerns\RequiresApproval;
use App\Support\PaperStock;
use App\Support\PressMath;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A book order through the press: printed in-house or outsourced, then folded, bound and loaded.
 * Its paper leaves stock when the owner approves it.
 */
#[Fillable(['number', 'ordered_on', 'book_format_id', 'copies', 'forms', 'wastage_percent', 'rims_needed', 'paper_type_id', 'party_id', 'mode', 'machine_id', 'press_id', 'buyer_id', 'due_on', 'stage', 'note', 'created_by'])]
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory, RequiresApproval;

    protected function casts(): array
    {
        return [
            'ordered_on' => 'date',
            'due_on' => 'date',
            'printed_on' => 'date',
            'printed_copies' => 'integer',
            'copies' => 'integer',
            'forms' => 'decimal:2',
            'wastage_percent' => 'decimal:2',
            'rims_needed' => 'integer',
            'mode' => PrintMode::class,
            'stage' => WorkOrderStage::class,
        ];
    }

    /**
     * Once approved (by the owner, or on save when the owner enters it), issue the paper and start printing.
     */
    protected static function booted(): void
    {
        $start = function (self $order): void {
            if ($order->status === ApprovalStatus::Approved && ! $order->paper_issue_id) {
                $order->issuePaperAndStart();
            }
        };

        static::created($start);
        static::updated($start);
    }

    public static function nextNumber(): string
    {
        $last = static::query()->where('number', 'like', 'WO-%')->orderByDesc('id')->value('number');

        return 'WO-'.($last ? (int) substr($last, 3) + 1 : 1001);
    }

    /**
     * @return array{base: int, needed: int}
     */
    public static function rims(float $forms, int $copies, float $wastagePercent, int $sheetsPerUnit = 500): array
    {
        return [
            'base' => PressMath::rimsFor($forms, $copies, 0, $sheetsPerUnit),
            'needed' => PressMath::rimsFor($forms, $copies, $wastagePercent, $sheetsPerUnit),
        ];
    }

    public function issuePaperAndStart(): void
    {
        // The order itself was approved, so its paper issue is too.
        $issue = (new PaperIssue)->forceFill([
            'status' => ApprovalStatus::Approved,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at ?? now(),
            'issued_on' => now()->toDateString(),
            'kind' => $this->mode === PrintMode::Outsource ? PaperIssueKind::Outsource : PaperIssueKind::InHouse,
            'party_id' => $this->party_id,
            'paper_type_id' => $this->paper_type_id,
            'quantity' => $this->rims_needed,
            'reference' => $this->number,
            'book_format_id' => $this->book_format_id,
            'book_name' => $this->bookName(),
            'copies' => $this->copies,
            'issued_to' => $this->printedAt(),
            'machine_id' => $this->mode === PrintMode::InHouse ? $this->machine_id : null,
            'wastage_percent' => $this->wastage_percent,
            'created_by' => $this->reviewed_by ?? $this->created_by,
        ]);
        $issue->save();

        $this->forceFill(['paper_issue_id' => $issue->id, 'stage' => WorkOrderStage::Printing])->saveQuietly();
    }

    /**
     * Stops the owner approving an order the godown can no longer cover.
     */
    public function approvalBlocker(): ?string
    {
        $available = app(PaperStock::class)->available($this->paper_type_id, $this->party_id);

        return $this->rims_needed > $available
            ? __('Only :n rims of this paper are in stock for :party; the order needs :need.', ['n' => number_format($available), 'party' => $this->party->name, 'need' => number_format($this->rims_needed)])
            : null;
    }

    public function bookName(): string
    {
        $format = $this->bookFormat;

        return $format->book->formats()->count() > 1 ? "{$format->book->name} — {$format->name}" : $format->book->name;
    }

    public function printedAt(): string
    {
        return $this->mode === PrintMode::Outsource
            ? ($this->press?->name ?? __('Outside press'))
            : __('Machine :code', ['code' => $this->machine?->code ?? '—']);
    }

    /**
     * Rims sent out less what came back unused: leftovers from the outside press, or rims returned
     * from the machine at the end of the shift.
     */
    public function rimsUsed(): int
    {
        return $this->rims_needed - (int) $this->outsideReceipts()->sum('leftover_rims') - (int) ($this->paperIssue?->returned_quantity ?? 0);
    }

    /**
     * Copies that came off the press: entered when in-house printing is finished, or received back from the outside press.
     */
    public function printedCopies(): int
    {
        return $this->mode === PrintMode::Outsource
            ? (int) $this->outsideReceipts()->sum('printed_copies')
            : (int) $this->printed_copies;
    }

    public function copiesLeftToLoad(): int
    {
        return max(0, $this->copies - (int) $this->dispatches()->sum('copies'));
    }

    /**
     * Books can be loaded from binding onwards, while copies are left.
     */
    public function canBeLoaded(): bool
    {
        return in_array($this->stage, Dispatch::orderStages(), true) && $this->copiesLeftToLoad() > 0;
    }

    /**
     * Why the order cannot move to its next stage yet, or null when it can. Each stage needs its own work recorded first:
     * printing needs the printed copies, folding and binding need their contractor jobs given out and finished,
     * and dispatch happens only by loading the books.
     */
    public function advanceBlocker(): ?string
    {
        $jobsDone = function (ContractorJobKind $kind): ?string {
            $jobs = $this->contractorJobs()->where('kind', $kind)->get();

            return match (true) {
                $jobs->isEmpty() => __('Give the :work job to a contractor first.', ['work' => mb_strtolower($kind->label())]),
                $jobs->contains(fn (ContractorJob $job) => ! $job->isDone()) => __('Mark the :work job as done first.', ['work' => mb_strtolower($kind->label())]),
                default => null,
            };
        };

        return match ($this->stage) {
            WorkOrderStage::Waiting => __('Waiting for the owner\'s approval.'),
            WorkOrderStage::Printing => $this->mode === PrintMode::Outsource && $this->printedCopies() === 0
                ? __('Record the printed work received from the outside press first.')
                : null,
            WorkOrderStage::Folding => $jobsDone(ContractorJobKind::Folding),
            WorkOrderStage::Binding => $jobsDone(ContractorJobKind::Binding),
            WorkOrderStage::Ready => __('Load the books to dispatch them; the order closes once every copy is loaded.'),
            WorkOrderStage::Dispatched => __('This order is already dispatched.'),
        };
    }

    /**
     * @return BelongsTo<BookFormat, $this>
     */
    public function bookFormat(): BelongsTo
    {
        return $this->belongsTo(BookFormat::class);
    }

    /**
     * @return BelongsTo<PaperType, $this>
     */
    public function paperType(): BelongsTo
    {
        return $this->belongsTo(PaperType::class);
    }

    /**
     * Whose paper the order is printed on.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function press(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'press_id');
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<PaperIssue, $this>
     */
    public function paperIssue(): BelongsTo
    {
        return $this->belongsTo(PaperIssue::class);
    }

    /**
     * @return HasMany<OutsidePrintReceipt, $this>
     */
    public function outsideReceipts(): HasMany
    {
        return $this->hasMany(OutsidePrintReceipt::class);
    }

    /**
     * @return HasMany<ContractorJob, $this>
     */
    public function contractorJobs(): HasMany
    {
        return $this->hasMany(ContractorJob::class);
    }

    /**
     * @return HasMany<PlateJob, $this>
     */
    public function plateJobs(): HasMany
    {
        return $this->hasMany(PlateJob::class);
    }

    /**
     * @return HasMany<Dispatch, $this>
     */
    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    /**
     * @return HasMany<WasteEntry, $this>
     */
    public function wasteEntries(): HasMany
    {
        return $this->hasMany(WasteEntry::class);
    }

    public function resendUrl(): string
    {
        return route('work-orders.create');
    }

    public function approvalKind(): string
    {
        return __('Work order');
    }

    public function approvalTitle(): string
    {
        return "{$this->number} {$this->bookName()}";
    }

    public function approvalDetail(): string
    {
        return __(':copies copies, :forms forms, :rims rims to :where', [
            'copies' => number_format($this->copies), 'forms' => InventoryItem::qty($this->forms),
            'rims' => number_format($this->rims_needed), 'where' => $this->printedAt(),
        ]);
    }

    public function approvalAmount(): ?int
    {
        return null;
    }

    public function approvalIcon(): string
    {
        return 'orders';
    }

    public function approvalUrl(): string
    {
        return route('work-orders.show', $this);
    }
}
