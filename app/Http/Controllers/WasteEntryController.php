<?php

namespace App\Http\Controllers;

use App\Enums\WasteDisposal;
use App\Enums\WasteStage;
use App\Models\WasteEntry;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WasteEntryController extends Controller
{
    public function index(): View
    {
        $monthStart = now()->startOfMonth();

        return view('waste.index', [
            'entries' => WasteEntry::query()->with('workOrder')->latest('recorded_on')->latest('id')->paginate(30),
            'byStage' => WasteEntry::query()->where('recorded_on', '>=', $monthStart)->get()->groupBy(fn ($e) => $e->stage->label().' ('.$e->unit.')')
                ->map(fn ($rows) => (float) $rows->sum('quantity')),
            'soldThisMonth' => (int) WasteEntry::query()->where('recorded_on', '>=', $monthStart)->where('disposal', WasteDisposal::Sold)->sum('sale_amount'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('waste.create', [
            'orders' => WorkOrder::query()->with('bookFormat.book')->where('stage', '!=', 'waiting')->latest('id')->take(60)->get(),
            'orderId' => $request->integer('order') ?: null,
            'stages' => WasteStage::cases(),
            'disposals' => WasteDisposal::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'recorded_on' => ['required', 'date'],
            'stage' => ['required', Rule::enum(WasteStage::class)],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'in:kg,rims,sheets'],
            'responsible' => ['nullable', 'string', 'max:120'],
            'disposal' => ['required', Rule::enum(WasteDisposal::class)],
            'sale_amount' => ['nullable', 'required_if:disposal,sold', 'integer', 'min:0'],
            'sold_to' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = WasteEntry::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route($entry->work_order_id ? 'work-orders.show' : 'waste.index', $entry->work_order_id ? [$entry->work_order_id] : [])
            ->with('status', __('Waste recorded.'));
    }
}
