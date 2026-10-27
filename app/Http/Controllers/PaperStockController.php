<?php

namespace App\Http\Controllers;

use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Support\PaperStock;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaperStockController extends Controller
{
    public function index(Request $request, PaperStock $stock): View
    {
        $tab = in_array($request->query('tab'), ['stock', 'receipts', 'issues'], true) ? $request->query('tab') : 'stock';
        $paperTypeId = $request->integer('paper_type_id') ?: null;
        $partyId = $request->integer('party_id') ?: null;

        $weekStart = now()->startOfWeek();

        return view('paper.index', [
            'tab' => $tab,
            'balances' => $stock->balances($paperTypeId, $partyId),
            'lowStock' => $stock->belowReorderLevel(),
            'receipts' => PaperReceipt::query()->with(['party', 'paperType', 'godown'])
                ->when($paperTypeId, fn ($q) => $q->where('paper_type_id', $paperTypeId))
                ->when($partyId, fn ($q) => $q->where('party_id', $partyId))
                ->latest('received_at')->paginate(25, ['*'], 'page')->withQueryString(),
            'issues' => PaperIssue::query()->with(['party', 'paperType'])
                ->when($paperTypeId, fn ($q) => $q->where('paper_type_id', $paperTypeId))
                ->when($partyId, fn ($q) => $q->where('party_id', $partyId))
                ->latest('issued_on')->latest('id')->paginate(25, ['*'], 'page')->withQueryString(),
            'receivedThisWeek' => (int) PaperReceipt::query()->notRejected()->where('received_at', '>=', $weekStart)->sum('quantity'),
            'trucksThisWeek' => PaperReceipt::query()->notRejected()->where('received_at', '>=', $weekStart)->count(),
            'issuedThisWeek' => (int) PaperIssue::query()->where('issued_on', '>=', $weekStart)->sum('quantity'),
            'paperTypes' => PaperType::query()->orderBy('code')->get(),
            'parties' => Party::query()->whereHas('paperReceipts')->orderBy('name')->get(),
            'filters' => ['paper_type_id' => $paperTypeId, 'party_id' => $partyId],
        ]);
    }
}
