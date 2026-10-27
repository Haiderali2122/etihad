<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Enums\RepairKind;
use App\Http\Requests\StoreMachineRepairRequest;
use App\Models\Machine;
use App\Models\MachineRepair;
use App\Models\Part;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MachineRepairController extends Controller
{
    public function create(Request $request): View
    {
        return view('machines.repairs.create', [
            'machines' => Machine::query()->orderBy('code')->get(),
            'machineId' => $request->integer('machine') ?: null,
            'parts' => Part::query()->orderBy('name')->get(),
            'vendors' => Party::query()->active()->whereIn('kind', [PartyKind::Mechanic, PartyKind::Supplier, PartyKind::Other])->orderBy('name')->get(),
            'kinds' => RepairKind::cases(),
        ]);
    }

    public function store(StoreMachineRepairRequest $request): RedirectResponse
    {
        $repair = MachineRepair::create($request->safe()->except('attachment') + [
            'discount' => $request->integer('discount'),
            'attachment' => $this->storeAttachment($request, 'repairs'),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('machines.show', $repair->machine_id)->with('status', $repair->isPending()
            ? __('Repair saved and sent for approval.')
            : __('Repair saved.'));
    }
}
