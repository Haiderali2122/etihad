<?php

namespace App\Http\Controllers;

use App\Enums\PaperIssueKind;
use App\Http\Requests\StorePaperIssueRequest;
use App\Models\Book;
use App\Models\BookFormat;
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

        if ($format = BookFormat::query()->with('book')->find($data['book_format_id'] ?? null)) {
            $data['book_name'] = $format->book->formats()->count() > 1 ? "{$format->book->name} — {$format->name}" : $format->book->name;
        }

        $issue = PaperIssue::create([
            ...$data,
            'wastage_percent' => $data['wastage_percent'] ?? 0,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('paper.index', ['tab' => 'issues'])
            ->with('status', __(':qty rims issued to :to.', ['qty' => number_format($issue->quantity), 'to' => $issue->issued_to]));
    }
}
