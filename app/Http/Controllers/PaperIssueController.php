<?php

namespace App\Http\Controllers;

use App\Enums\PaperIssueKind;
use App\Http\Requests\StorePaperIssueRequest;
use App\Models\Book;
use App\Models\BookFormat;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\PaperIssue;
use App\Models\PaperLoan;
use App\Models\Party;
use App\Models\Setting;
use App\Support\PaperStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaperIssueController extends Controller
{
    public function create(Request $request, PaperStock $stock): View
    {
        return view('paper.issues.create', [
            'balances' => $stock->balances()->filter(fn (array $row): bool => $row['balance'] > 0)->values(),
            // Every stock of each paper, for covering a shortage from another party's (or the press's) paper.
            'covers' => $stock->balances()->filter(fn (array $row): bool => $row['balance'] > 0)
                ->map(fn (array $row) => ['type' => $row['paper_type']->id, 'party' => $row['party']->id, 'name' => $row['party']->name, 'paper' => $row['paper_type']->label(), 'balance' => $row['balance']])->values(),
            'kinds' => PaperIssueKind::cases(),
            'kind' => PaperIssueKind::tryFrom((string) $request->query('kind')) ?? PaperIssueKind::InHouse,
            'machines' => Machine::query()->orderBy('code')->get(),
            'employees' => Employee::query()->active()->orderBy('name')->get(),
            'books' => Book::query()->active()->whereHas('formats')->with('formats')->orderBy('name')->get(),
            'defaultWastage' => (float) Setting::get(Setting::DefaultWastagePercent, 3),
        ]);
    }

    public function store(StorePaperIssueRequest $request, PaperStock $stock): RedirectResponse
    {
        $data = $request->validated();

        if ($data['kind'] === PaperIssueKind::Return->value) {
            $data = array_merge($data, ['book_format_id' => null, 'book_name' => null, 'copies' => null, 'wastage_percent' => 0]);
        }

        // Only paper for an in-house machine is handed to a member of staff.
        if ($data['kind'] !== PaperIssueKind::InHouse->value) {
            $data = array_merge($data, ['employee_id' => null, 'machine_id' => null]);
        } elseif (blank($data['issued_to'] ?? null)) {
            $data['issued_to'] = __('Machine').' '.Machine::query()->whereKey($data['machine_id'])->value('code');
        }

        if ($format = BookFormat::query()->with('book')->find($data['book_format_id'] ?? null)) {
            $data['book_name'] = $format->book->formats()->count() > 1 ? "{$format->book->name} — {$format->name}" : $format->book->name;
        }

        [$coverTypeId, $lenderId] = filled($data['shortage_stock'] ?? null) ? $request->shortageStock() : [null, null];
        unset($data['shortage_stock']);
        $available = $stock->available((int) $data['paper_type_id'], (int) $data['party_id']);
        $short = $lenderId ? max(0, (int) $data['quantity'] - $available) : 0;

        $issue = DB::transaction(function () use ($data, $request, $short, $available, $lenderId, $coverTypeId): PaperIssue {
            $issue = PaperIssue::create([
                ...$data,
                'quantity' => $short > 0 ? $available : $data['quantity'],
                'wastage_percent' => $data['wastage_percent'] ?? 0,
                'created_by' => $request->user()->id,
            ]);
            if ($short > 0) {
                $lender = Party::query()->find($lenderId);
                $cover = PaperIssue::create([
                    ...$data,
                    'party_id' => $lenderId,
                    'paper_type_id' => $coverTypeId,
                    'quantity' => $short,
                    'wastage_percent' => $data['wastage_percent'] ?? 0,
                    'note' => trim(__('Shortage of :n rims for :party', ['n' => $short, 'party' => $issue->party->name]).'. '.($data['note'] ?? '')),
                    'created_by' => $request->user()->id,
                ]);
                // Owed in the paper that was taken.
                PaperLoan::create([
                    'borrowed_on' => $data['issued_on'], 'paper_type_id' => $coverTypeId, 'rims' => $short,
                    'borrower_party_id' => $data['party_id'], 'lender_party_id' => $lender->id,
                    'paper_issue_id' => $issue->id, 'cover_issue_id' => $cover->id, 'created_by' => $request->user()->id,
                ]);
            }

            return $issue;
        });

        $message = __(':qty rims issued to :to.', ['qty' => number_format($issue->quantity + $short), 'to' => $issue->issued_to]);
        if ($short > 0) {
            $message .= ' '.__(':short rims were short and came from :lender; :party owes them (see Paper owed).', ['short' => $short, 'lender' => Party::query()->whereKey($lenderId)->value('name'), 'party' => $issue->party->name]);
        }

        return redirect()->route('paper.index', ['tab' => 'issues'])
            ->with('status', $issue->isPending() ? $message.' '.__('Sent to the owner for approval.') : $message);
    }

    /**
     * A shortage paid back: in rims (moved from the order party's stock back to the lender's) or in money.
     */
    public function settleLoan(Request $request, PaperLoan $paperLoan, PaperStock $stock): RedirectResponse
    {
        abort_unless($paperLoan->isOpen(), 409, __('This shortage is already settled.'));
        $data = $request->validate([
            'settled_how' => ['required', 'in:'.PaperLoan::SettledWithRims.','.PaperLoan::SettledWithMoney],
            'settled_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['nullable', 'required_if:settled_how,'.PaperLoan::SettledWithMoney, 'integer', 'min:1'],
            'return_party_id' => ['nullable', 'exists:parties,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        if ($data['settled_how'] === PaperLoan::SettledWithRims && $stock->available($paperLoan->paper_type_id, $paperLoan->borrower_party_id) < $paperLoan->rims) {
            return back()->withErrors(['settled_how' => __(':party has only :n rims of this paper in stock; receive their paper first.', ['party' => $paperLoan->borrower->name, 'n' => $stock->available($paperLoan->paper_type_id, $paperLoan->borrower_party_id)])]);
        }

        $withRims = $data['settled_how'] === PaperLoan::SettledWithRims;
        $paperLoan->update($data + [
            'settled_by' => $request->user()->id,
            'amount' => $withRims ? null : $data['amount'],
            // Rims go back into the stock they came from unless another stock was picked.
            'return_party_id' => $withRims && ($data['return_party_id'] ?? null) && (int) $data['return_party_id'] !== $paperLoan->lender_party_id ? (int) $data['return_party_id'] : null,
        ]);

        return back()->with('status', $withRims
            ? __(':n rims moved from :from into :to.', ['n' => $paperLoan->rims, 'from' => $paperLoan->borrower->name, 'to' => Party::query()->whereKey($paperLoan->refresh()->returnPartyId())->value('name')])
            : __("Shortage settled: :party paid Rs :amount to Sheikh sahib's account (not the manager's cash).", ['party' => $paperLoan->borrower->name, 'amount' => number_format($data['amount'])]));
    }

    /**
     * End of shift for in-house paper: rims that came back off the machine return to stock.
     */
    public function settle(Request $request, PaperIssue $paperIssue): RedirectResponse
    {
        abort_unless($paperIssue->canBeSettled(), 409, __('This entry is already closed.'));

        $data = $request->validate([
            'returned_quantity' => ['required', 'integer', 'min:0', 'max:'.$paperIssue->quantity],
        ]);

        $paperIssue->forceFill([
            'returned_quantity' => $data['returned_quantity'],
            'settled_at' => now(),
            'settled_by' => $request->user()->id,
        ])->save();

        return back()->with('status', __(':used rims used; :back rims back in stock.', ['used' => number_format($paperIssue->used()), 'back' => number_format($paperIssue->returned_quantity)]));
    }
}
