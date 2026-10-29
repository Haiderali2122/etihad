<?php

namespace App\Http\Controllers;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Http\Requests\MachineRequest;
use App\Models\Machine;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MachineController extends Controller
{
    /**
     * Spend everywhere is approved repair payments plus approved cash expenses tagged to the machine,
     * the same as the dashboard and the machine page.
     */
    public function index(): View
    {
        $monthStart = now()->startOfMonth();
        $month = [$monthStart, $monthStart->copy()->endOfMonth()];

        return view('machines.index', [
            'machines' => Machine::query()
                ->withSum(['repairs as month_repairs' => fn ($q) => $q->approved()->whereBetween('repaired_on', $month)], 'amount_paid')
                ->withSum(['expenses as month_expenses' => fn ($q) => $q->approved()->whereBetween('spent_on', $month)], 'amount')
                ->withSum(['repairs as total_repairs' => fn ($q) => $q->approved()], 'amount_paid')
                ->withSum(['expenses as total_expenses' => fn ($q) => $q->approved()], 'amount')
                ->with(['repairs' => fn ($q) => $q->latest('repaired_on')->limit(1)])
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function show(Machine $machine): View
    {
        return view('machines.show', [
            'machine' => $machine,
            'repairs' => $machine->repairs()->with(['part', 'vendor'])->latest('repaired_on')->latest('id')->get(),
            'expenses' => $machine->expenses()->with('head')->approved()->latest('spent_on')->get(),
        ]);
    }

    public function create(): View
    {
        return view('machines.form', [
            'machine' => new Machine(['status' => MachineStatus::Running, 'type' => MachineType::Printing]),
        ]);
    }

    public function store(MachineRequest $request): RedirectResponse
    {
        $machine = Machine::create($request->validated());

        return redirect()->route('machines.show', $machine)->with('status', __('Machine saved.'));
    }

    public function edit(Machine $machine): View
    {
        return view('machines.form', ['machine' => $machine]);
    }

    public function update(MachineRequest $request, Machine $machine): RedirectResponse
    {
        $machine->update($request->validated());

        return redirect()->route('machines.show', $machine)->with('status', __('Machine saved.'));
    }
}
