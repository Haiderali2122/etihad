<?php

namespace App\Http\Controllers;

use App\Enums\PaperIssueKind;
use App\Http\Requests\StorePaperIssueRequest;
use App\Models\Book;
use App\Models\BookFormat;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\PaperIssue;
use App\Models\Setting;
use App\Support\PaperStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaperIssueController extends Controller
{
    public function create(Request $request, PaperStock $stock): View
    {
        return view('paper.issues.create', [
            'balances' => $stock->balances()->filter(fn (array $row): bool => $row['balance'] > 0)->values(),
            'kinds' => PaperIssueKind::cases(),
            'kind' => PaperIssueKind::tryFrom((string) $request->query('kind')) ?? PaperIssueKind::InHouse,
            'machines' => Machine::query()->orderBy('code')->get(),
            'employees' => Employee::query()->active()->orderBy('name')->get(),
            'books' => Book::query()->active()->whereHas('formats')->with('formats')->orderBy('name')->get(),
            'defaultWastage' => (float) Setting::get(Setting::DefaultWastagePercent, 3),
        ]);
    }

    public function store(StorePaperIssueRequest $request): RedirectResponse
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

        $issue = PaperIssue::create([
            ...$data,
            'wastage_percent' => $data['wastage_percent'] ?? 0,
            'created_by' => $request->user()->id,
        ]);

        $message = __(':qty rims issued to :to.', ['qty' => number_format($issue->quantity), 'to' => $issue->issued_to]);

        return redirect()->route('paper.index', ['tab' => 'issues'])
            ->with('status', $issue->isPending() ? $message.' '.__('Sent to the owner for approval.') : $message);
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
