<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Http\Requests\PartyRequest;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartyController extends Controller
{
    public function index(Request $request): View
    {
        $kind = $this->kindFrom($request->query('kind'));

        return view('parties.index', [
            'parties' => Party::query()
                ->when($kind, fn ($q) => $q->where('kind', $kind))
                ->withSum(['purchases' => fn ($q) => $q->notRejected()], 'amount')
                ->orderBy('name')
                ->get(),
            'kind' => $kind,
        ]);
    }

    /**
     * ?kind= presets the type (e.g. "Add buyer" from the Buyers screen) and ?return= is the page to go back to,
     * so adding a buyer from a loading or a plate processor from a plate job returns to that form.
     */
    public function create(Request $request): View
    {
        $kind = $this->kindFrom($request->query('kind'));

        return view('parties.form', [
            'party' => new Party(['is_active' => true, 'kind' => $kind]),
            'returnTo' => $this->safeReturnUrl($request->query('return'))
                ?? ($kind === PartyKind::Buyer ? route('buyers.index') : route('parties.index', array_filter(['kind' => $kind?->value]))),
        ]);
    }

    public function store(PartyRequest $request): RedirectResponse
    {
        $party = Party::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect($this->safeReturnUrl($request->input('return_to')) ?? route('parties.index'))
            ->with('status', $party->kind === PartyKind::Buyer ? __('Buyer saved.') : __('Party saved.'));
    }

    /**
     * Opened from a type tab, the form returns to that tab.
     */
    public function edit(Request $request, Party $party): View
    {
        return view('parties.form', [
            'party' => $party,
            'returnTo' => $this->safeReturnUrl($request->query('return'))
                ?? route('parties.index', array_filter(['kind' => $this->kindFrom($request->query('tab'))?->value])),
        ]);
    }

    public function update(PartyRequest $request, Party $party): RedirectResponse
    {
        $party->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect($this->safeReturnUrl($request->input('return_to')) ?? route('parties.index'))->with('status', __('Party saved.'));
    }

    private function kindFrom(mixed $value): ?PartyKind
    {
        return is_string($value) ? PartyKind::tryFrom($value) : null;
    }
}
