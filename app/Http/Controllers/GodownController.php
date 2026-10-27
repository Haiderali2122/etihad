<?php

namespace App\Http\Controllers;

use App\Http\Requests\GodownRequest;
use App\Models\Godown;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GodownController extends Controller
{
    public function index(): View
    {
        return view('godowns.index', [
            'godowns' => Godown::query()
                ->withCount('paperReceipts')
                ->withSum(['paperReceipts' => fn ($q) => $q->notRejected()], 'quantity')
                ->withMax('paperReceipts', 'received_at')
                ->orderByDesc('is_active')->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('godowns.form', ['godown' => new Godown(['is_active' => true])]);
    }

    public function store(GodownRequest $request): RedirectResponse
    {
        $godown = Godown::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('godowns.index')->with('status', __('Godown :name saved.', ['name' => $godown->name]));
    }

    public function edit(Godown $godown): View
    {
        return view('godowns.form', ['godown' => $godown]);
    }

    public function update(GodownRequest $request, Godown $godown): RedirectResponse
    {
        $godown->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('godowns.index')->with('status', __('Godown :name saved.', ['name' => $godown->name]));
    }

    /**
     * A godown with receipts stays so old slips keep their location; it can be deactivated instead.
     */
    public function destroy(Godown $godown): RedirectResponse
    {
        if ($godown->paperReceipts()->exists()) {
            return back()->withErrors(['godown' => __(':name has paper receipts recorded against it. Deactivate it instead so old slips keep their location.', ['name' => $godown->name])]);
        }

        $godown->delete();

        return redirect()->route('godowns.index')->with('status', __('Godown :name deleted.', ['name' => $godown->name]));
    }
}
