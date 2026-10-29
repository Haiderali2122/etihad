<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Enums\WorkOrderStage;
use App\Http\Requests\StoreDispatchRequest;
use App\Models\Book;
use App\Models\Dispatch;
use App\Models\Party;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispatchController extends Controller
{
    public function index(): View
    {
        return view('dispatches.index', [
            'dispatches' => Dispatch::query()->with(['buyer', 'workOrder', 'bookFormat.book'])->latest('dispatched_on')->latest('id')->paginate(30),
            'monthCopies' => (int) Dispatch::query()->where('dispatched_on', '>=', now()->startOfMonth())->sum('copies'),
            'monthBundles' => (int) Dispatch::query()->where('dispatched_on', '>=', now()->startOfMonth())->sum('bundles'),
            'monthLabour' => (int) Dispatch::query()->where('dispatched_on', '>=', now()->startOfMonth())->sum('labour_amount'),
        ]);
    }

    /**
     * Opened from an order (?order=) or a buyer (?buyer=), the form starts with them filled in and goes back there.
     */
    public function create(Request $request): View
    {
        $order = WorkOrder::query()->with('bookFormat.book')->find($request->integer('order'));
        $order = $order?->canBeLoaded() ? $order : null;
        $buyer = Party::query()->where('kind', PartyKind::Buyer)->find($request->integer('buyer'));

        $orders = WorkOrder::query()->with('bookFormat.book')->whereIn('stage', Dispatch::orderStages())
            ->withSum('dispatches', 'copies')->latest('id')->get()
            ->filter(fn (WorkOrder $o) => $o->copies > (int) $o->dispatches_sum_copies)->values();

        return view('dispatches.create', [
            'number' => Dispatch::nextNumber(),
            'buyers' => Party::query()->active()->where('kind', PartyKind::Buyer)->orderBy('name')->get(),
            'orders' => $orders,
            'books' => Book::query()->active()->with('formats')->orderBy('name')->get(),
            'order' => $order,
            'buyerId' => $order?->buyer_id ?? $buyer?->id,
            'back' => match (true) {
                $order !== null => route('work-orders.show', $order),
                $buyer !== null => route('buyers.show', $buyer),
                default => route('dispatches.index'),
            },
        ]);
    }

    public function store(StoreDispatchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $order = isset($data['work_order_id']) ? WorkOrder::query()->find($data['work_order_id']) : null;
        $copies = $data['bundles'] * $data['copies_per_bundle'] + (int) ($data['loose_copies'] ?? 0);
        $labour = $data['bundles'] * (float) ($data['labour_per_bundle'] ?? 0) + (float) ($data['rims'] ?? 0) * (float) ($data['labour_per_rim'] ?? 0);

        $dispatch = Dispatch::create(array_merge($data, [
            'number' => Dispatch::nextNumber(),
            'book_format_id' => $data['book_format_id'] ?? $order?->book_format_id,
            'loose_copies' => (int) ($data['loose_copies'] ?? 0),
            'copies' => $copies,
            'labour_per_bundle' => $data['labour_per_bundle'] ?? 0,
            'labour_per_rim' => $data['labour_per_rim'] ?? 0,
            'labour_amount' => (int) round($labour),
            'created_by' => $request->user()->id,
        ]));

        if ($order && $order->dispatches()->sum('copies') >= $order->copies && $order->stage !== WorkOrderStage::Dispatched) {
            $order->update(['stage' => WorkOrderStage::Dispatched]);
        }

        return redirect()->route('dispatches.show', $dispatch)->with('status', __(':number saved: :copies copies to :buyer.', [
            'number' => $dispatch->number, 'copies' => number_format($copies), 'buyer' => $dispatch->buyer->name,
        ]));
    }

    /**
     * The loading slip, ready to print.
     */
    public function show(Dispatch $dispatch): View
    {
        return view('dispatches.show', ['dispatch' => $dispatch->load(['buyer', 'workOrder', 'bookFormat.book'])]);
    }
}
