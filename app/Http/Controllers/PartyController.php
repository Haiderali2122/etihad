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
        $kind = is_string($request->query('kind')) ? PartyKind::tryFrom($request->query('kind')) : null;

        return view('parties.index', [
            'parties' => Party::query()
                ->when($kind, fn ($q) => $q->where('kind', $kind))
                ->withSum(['purchases' => fn ($q) => $q->notRejected()], 'amount')
                ->orderBy('name')
                ->get(),
            'kind' => $kind,
        ]);
    }

    public function create(): View
    {
        return view('parties.form', ['party' => new Party(['is_active' => true])]);
    }

    public function store(PartyRequest $request): RedirectResponse
    {
        Party::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('parties.index')->with('status', __('Party saved.'));
    }

    public function edit(Party $party): View
    {
        return view('parties.form', ['party' => $party]);
    }

    public function update(PartyRequest $request, Party $party): RedirectResponse
    {
        $party->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('parties.index')->with('status', __('Party saved.'));
    }
}
