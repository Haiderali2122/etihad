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
        $dispatch = new Dispatch(['number' => Dispatch::nextNumber(), 'created_by' => $request->user()->id]);
        $this->fill($dispatch, $request);
        $dispatch->save();
        $this->closeOrderIfLoaded($dispatch->workOrder);

        return redirect()->route('dispatches.show', $dispatch)->with('status', __(':number saved: :copies copies to :buyer.', [
            'number' => $dispatch->number, 'copies' => number_format($dispatch->copies), 'buyer' => $dispatch->buyer->name,
        ]));
    }

    public function edit(Dispatch $dispatch): View
    {
        $dispatch->load('workOrder.bookFormat.book');
        $orders = WorkOrder::query()->with('bookFormat.book')->whereIn('stage', Dispatch::orderStages())->latest('id')->get();
        if ($dispatch->workOrder && ! $orders->contains('id', $dispatch->work_order_id)) {
            $orders->prepend($dispatch->workOrder);
        }

        return view('dispatches.create', [
            'dispatch' => $dispatch,
            'number' => $dispatch->number,
            'buyers' => Party::query()->where('kind', PartyKind::Buyer)->where(fn ($q) => $q->where('is_active', true)->orWhereKey($dispatch->buyer_id))->orderBy('name')->get(),
            'orders' => $orders,
            'books' => Book::query()->active()->with('formats')->orderBy('name')->get(),
            'order' => $dispatch->workOrder,
            'buyerId' => $dispatch->buyer_id,
            'back' => route('dispatches.show', $dispatch),
        ]);
    }

    public function update(StoreDispatchRequest $request, Dispatch $dispatch): RedirectResponse
    {
        $previousOrder = $dispatch->workOrder;
        $this->fill($dispatch, $request);
        $dispatch->save();

        $this->closeOrderIfLoaded($dispatch->refresh()->workOrder);
        if ($previousOrder && $previousOrder->id !== $dispatch->work_order_id) {
            $this->closeOrderIfLoaded($previousOrder);
        }

        return redirect()->route('dispatches.show', $dispatch)->with('status', __(':number saved.', ['number' => $dispatch->number]));
    }

    /**
     * Copies and labour from the form, plus the truck photos and any other files. Photos left empty keep the
     * ones already attached; new files are added to the list.
     */
    private function fill(Dispatch $dispatch, StoreDispatchRequest $request): void
    {
        $data = $request->safe()->except(['truck_front', 'truck_back', 'files']);
        $order = isset($data['work_order_id']) ? WorkOrder::query()->find($data['work_order_id']) : null;

        $dispatch->fill(array_merge($data, [
            'book_format_id' => $data['book_format_id'] ?? $order?->book_format_id,
            'loose_copies' => (int) ($data['loose_copies'] ?? 0),
            'copies' => $data['bundles'] * $data['copies_per_bundle'] + (int) ($data['loose_copies'] ?? 0),
            'labour_per_bundle' => $data['labour_per_bundle'] ?? 0,
            'labour_amount' => (int) round($data['bundles'] * (float) ($data['labour_per_bundle'] ?? 0)),
        ]));

        foreach (['truck_front', 'truck_back'] as $photo) {
            if ($request->hasFile($photo)) {
                $dispatch->{$photo} = $request->file($photo)->store('attachments/dispatches', 'public');
            }
        }
        $files = collect($request->file('files', []))->map(fn ($file) => $file->store('attachments/dispatches', 'public'));
        if ($files->isNotEmpty()) {
            $dispatch->files = array_values(array_merge($dispatch->files ?? [], $files->all()));
        }
    }

    /**
     * An order closes once every copy is loaded, and opens again if a load is reduced or moved to another order.
     */
    private function closeOrderIfLoaded(?WorkOrder $order): void
    {
        if (! $order) {
            return;
        }

        $loaded = (int) $order->dispatches()->sum('copies');
        if ($loaded >= $order->copies && $order->stage !== WorkOrderStage::Dispatched) {
            $order->update(['stage' => WorkOrderStage::Dispatched]);
        } elseif ($loaded < $order->copies && $order->stage === WorkOrderStage::Dispatched) {
            $order->update(['stage' => WorkOrderStage::Ready]);
        }
    }

    /**
     * The loading slip, ready to print.
     */
    public function show(Dispatch $dispatch): View
    {
        return view('dispatches.show', ['dispatch' => $dispatch->load(['buyer', 'workOrder', 'bookFormat.book'])]);
    }
}
