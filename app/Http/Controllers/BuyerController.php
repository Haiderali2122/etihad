<?php

namespace App\Http\Controllers;

use App\Enums\BuyerEntryKind;
use App\Enums\PartyKind;
use App\Models\BuyerEntry;
use App\Models\Dispatch;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BuyerController extends Controller
{
    public function index(): View
    {
        $buyers = Party::query()->where('kind', PartyKind::Buyer)->orderByDesc('is_active')->orderBy('name')->get();
        $supplied = Dispatch::query()->selectRaw('buyer_id, SUM(copies) as copies, COUNT(*) as loads, MAX(dispatched_on) as last_on')->groupBy('buyer_id')->get()->keyBy('buyer_id');
        $entries = BuyerEntry::query()->selectRaw('party_id, kind, SUM(amount) as total')->groupBy('party_id', 'kind')->get()->groupBy('party_id');

        return view('buyers.index', [
            'buyers' => $buyers->map(function (Party $buyer) use ($supplied, $entries) {
                $mine = $entries->get($buyer->id, collect());
                $advances = (int) $mine->firstWhere('kind', BuyerEntryKind::Advance)?->total;
                $adjusted = (int) $mine->firstWhere('kind', BuyerEntryKind::Adjustment)?->total;

                return [
                    'buyer' => $buyer,
                    'copies' => (int) ($supplied[$buyer->id]->copies ?? 0),
                    'loads' => (int) ($supplied[$buyer->id]->loads ?? 0),
                    'last_on' => $supplied[$buyer->id]->last_on ?? null,
                    'advance_left' => $advances - $adjusted,
                ];
            }),
        ]);
    }

    /**
     * What the buyer has received, and their advances and adjustments.
     */
    public function show(Party $buyer): View
    {
        abort_unless($buyer->kind === PartyKind::Buyer, 404);
        $entries = BuyerEntry::query()->where('party_id', $buyer->id)->with('dispatch')->latest('entry_on')->latest('id')->get();

        return view('buyers.show', [
            'buyer' => $buyer,
            'dispatches' => Dispatch::query()->where('buyer_id', $buyer->id)->with(['bookFormat.book', 'workOrder'])->latest('dispatched_on')->get(),
            'entries' => $entries,
            'advances' => (int) $entries->where('kind', BuyerEntryKind::Advance)->sum('amount'),
            'adjusted' => (int) $entries->where('kind', BuyerEntryKind::Adjustment)->sum('amount'),
        ]);
    }

    public function storeEntry(Request $request, Party $buyer): RedirectResponse
    {
        abort_unless($buyer->kind === PartyKind::Buyer, 404);

        $data = $request->validate([
            'entry_on' => ['required', 'date'],
            'kind' => ['required', Rule::enum(BuyerEntryKind::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'dispatch_id' => ['nullable', Rule::exists('dispatches', 'id')->where('buyer_id', $buyer->id)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['kind'] === BuyerEntryKind::Adjustment->value) {
            $left = (int) BuyerEntry::query()->where('party_id', $buyer->id)->where('kind', BuyerEntryKind::Advance)->sum('amount')
                - (int) BuyerEntry::query()->where('party_id', $buyer->id)->where('kind', BuyerEntryKind::Adjustment)->sum('amount');
            if ($data['amount'] > $left) {
                return back()->withInput()->withErrors(['amount' => __('Only Rs :n of advance is left to adjust.', ['n' => number_format($left)])]);
            }
        }

        BuyerEntry::create($data + ['party_id' => $buyer->id, 'created_by' => $request->user()->id]);

        return back()->with('status', __('Saved.'));
    }
}
