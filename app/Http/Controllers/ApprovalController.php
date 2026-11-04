<?php

namespace App\Http\Controllers;

use App\Models\ContractorPayment;
use App\Models\Expense;
use App\Models\ForemanSheet;
use App\Models\InventoryIssue;
use App\Models\Loan;
use App\Models\MachineRepair;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\Purchase;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    /**
     * Route keys for each kind of entry that can wait for the owner.
     *
     * @var array<string, class-string<Model>>
     */
    public const Types = [
        'paper-receipt' => PaperReceipt::class,
        'purchase' => Purchase::class,
        'expense' => Expense::class,
        'repair' => MachineRepair::class,
        'advance' => SalaryAdvance::class,
        'foreman-sheet' => ForemanSheet::class,
        'loan' => Loan::class,
        'work-order' => WorkOrder::class,
        'contractor-payment' => ContractorPayment::class,
        'paper-issue' => PaperIssue::class,
        'inventory-issue' => InventoryIssue::class,
    ];

    public function index(): View
    {
        return view('approvals.index', ['items' => $this->pendingItems()]);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(isset(self::Types[$type]), 404);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:decision,reject'],
        ]);

        /** @var PaperReceipt|Purchase|Expense|MachineRepair|SalaryAdvance|ForemanSheet|Loan|WorkOrder|ContractorPayment $entry */
        $entry = self::Types[$type]::query()->findOrFail($id);

        abort_unless($entry->isPending(), 409, __('This entry has already been decided.'));
        abort_if($entry->created_by === $request->user()->id, 403, __('You cannot approve your own entry.'));

        // Some entries can't be approved as things stand, e.g. a work order the godown can no longer cover.
        if ($validated['decision'] === 'approve' && method_exists($entry, 'approvalBlocker') && ($blocker = $entry->approvalBlocker())) {
            return back()->withErrors(['decision' => $blocker]);
        }

        // Likewise, some entries can't be sent back, e.g. a receipt whose paper has already been issued.
        if ($validated['decision'] === 'reject' && method_exists($entry, 'rejectionBlocker') && ($blocker = $entry->rejectionBlocker())) {
            return back()->withErrors(['decision' => $blocker]);
        }

        $validated['decision'] === 'approve'
            ? $entry->approve($request->user(), $validated['note'] ?? null)
            : $entry->reject($request->user(), $validated['note']);

        return back()->with('status', $validated['decision'] === 'approve' ? __('Approved.') : __('Sent back to the manager.'));
    }

    /**
     * Open the usual form filled in from an entry the owner sent back, so it can be corrected and entered again.
     * Saving it marks the old entry as resent; the old one stays on record as sent back.
     */
    public function resend(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(isset(self::Types[$type]), 404);

        $entry = self::Types[$type]::query()->sentBackOpen()->findOrFail($id);
        abort_unless($entry->created_by === $request->user()->id, 403, __('Only the person who entered it can correct and resend it.'));

        $request->session()->put('resending', ['class' => $entry::class, 'id' => $entry->id]);

        return redirect($entry->resendUrl())
            ->withInput($entry->resendInput())
            ->with('status', __('Correct the details and save to send it to the owner again. Owner\'s note: :note', ['note' => $entry->review_note ?: '—']));
    }

    /**
     * Entries this person made that the owner sent back and that have not been entered again yet.
     *
     * @return Collection<int, array{type: string, model: Model}>
     */
    public function sentBackFor(User $user): Collection
    {
        return collect(self::Types)
            ->flatMap(fn (string $class, string $type) => $class::query()->sentBackOpen()->where('created_by', $user->id)
                // A foreman sheet's advances are sent back together with the sheet.
                ->when($class === SalaryAdvance::class, fn ($q) => $q->whereNull('foreman_sheet_id'))
                // Expenses copied from another tab are decided there, with the entry they came from.
                ->when($class === Expense::class, fn ($q) => $q->whereNull('source_type'))
                ->get()
                ->map(fn (Model $model) => ['type' => $type, 'model' => $model]))
            ->sortByDesc(fn (array $item) => $item['model']->reviewed_at)
            ->values();
    }

    /**
     * Every waiting entry across modules, newest first.
     *
     * @return Collection<int, array{type: string, model: Model}>
     */
    public function pendingItems(): Collection
    {
        $with = [
            PaperReceipt::class => ['party', 'paperType', 'creator'],
            Purchase::class => ['party', 'item', 'creator'],
            Expense::class => ['head', 'creator'],
            MachineRepair::class => ['machine', 'vendor', 'creator'],
            SalaryAdvance::class => ['employee', 'creator'],
            ForemanSheet::class => ['foreman', 'creator'],
            Loan::class => ['employee', 'creator'],
            WorkOrder::class => ['bookFormat.book', 'machine', 'press', 'party', 'creator'],
            ContractorPayment::class => ['contractor', 'workOrder', 'creator'],
            PaperIssue::class => ['party', 'paperType', 'creator'],
            InventoryIssue::class => ['item', 'machine', 'creator'],
        ];

        return collect(self::Types)
            ->flatMap(fn (string $class, string $type) => $class::query()->pending()->with($with[$class])
                // A foreman sheet's advances are approved together with the sheet.
                ->when($class === SalaryAdvance::class, fn ($q) => $q->whereNull('foreman_sheet_id'))
                // Expenses copied from another tab are decided there, with the entry they came from.
                ->when($class === Expense::class, fn ($q) => $q->whereNull('source_type'))
                ->get()
                ->map(fn (Model $model) => ['type' => $type, 'model' => $model]))
            ->sortByDesc(fn (array $item) => $item['model']->created_at)
            ->values();
    }
}
