<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\InventoryCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Raddi sales: entered like the Diesel, Glue and Ink expenses (rows of item, quantity and price), but they are
 * money coming in, so they are kept on the Raddi tab only and never reach Cash-out.
 */
class SaleController extends Controller
{
    public function create(InventoryCategory $inventoryCategory): View
    {
        abort_unless($inventoryCategory->isSale(), 404);

        // Items sold before, each with the price per quantity it was last sold at (filled in when picked, changeable).
        $lastRates = SaleItem::query()->whereHas('sale', fn ($q) => $q->where('inventory_category_id', $inventoryCategory->id))
            ->orderBy('id')->get(['description', 'rate'])
            ->mapWithKeys(fn (SaleItem $i) => [mb_strtolower(trim($i->description)) => ['name' => $i->description, 'rate' => (float) $i->rate]]);

        return view('sales.create', [
            'category' => $inventoryCategory,
            'suggestions' => $lastRates->pluck('name')->prepend($inventoryCategory->name)->unique(fn ($n) => mb_strtolower(trim($n)))->sort()->values()->all(),
            'lastRates' => $lastRates->map(fn (array $r) => $r['rate'])->all(),
            'back' => route('inventory.index', ['tab' => 'material-'.$inventoryCategory->id]),
        ]);
    }

    public function store(StoreSaleRequest $request, InventoryCategory $inventoryCategory): RedirectResponse
    {
        abort_unless($inventoryCategory->isSale(), 404);
        $lines = collect($request->validated('items'))->map(fn (array $l) => $l + ['amount' => (int) round((float) $l['quantity'] * (float) $l['rate'])]);

        $sale = DB::transaction(function () use ($request, $inventoryCategory, $lines): Sale {
            $sale = Sale::create([
                'inventory_category_id' => $inventoryCategory->id,
                'sold_on' => $request->validated('sold_on'),
                'total' => (int) $lines->sum('amount'),
                'attachment' => $this->storeAttachment($request, 'sales'),
                'created_by' => $request->user()->id,
            ]);
            $lines->each(fn (array $l) => $sale->items()->create([
                'description' => $l['item'], 'quantity' => $l['quantity'], 'rate' => $l['rate'], 'amount' => $l['amount'],
            ]));

            $sale->receive((int) $request->validated('received_now'), $sale->sold_on, $request->user()->id);

            return $sale;
        });

        return redirect()->route('sales.show', $sale)->with('status', __('Sale saved.'));
    }

    /**
     * Money received for one sale: any amount, on any date up to today.
     */
    public function receive(Request $request, Sale $sale): RedirectResponse
    {
        $left = $sale->toReceive();
        $request->mergeIfMissing(['amount' => $left]);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.max(1, $left)],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['amount.max' => __('Only Rs :n is still to be received.', ['n' => number_format($left)])]);

        $sale->receive((int) $data['amount'], $data['received_on'], $request->user()->id, $data['note'] ?? null);
        $sale->refresh();

        return back()->with('status', $sale->toReceive() > 0
            ? __('Rs :got received; Rs :left still to be received.', ['got' => number_format($data['amount']), 'left' => number_format($sale->toReceive())])
            : __('Rs :got received; the sale is fully received.', ['got' => number_format($data['amount'])]));
    }

    /**
     * Everything still to be received on the tab, received on one date.
     */
    public function receiveAll(Request $request, InventoryCategory $inventoryCategory): RedirectResponse
    {
        abort_unless($inventoryCategory->isSale(), 404);
        $data = $request->validate(['received_on' => ['nullable', 'date', 'before_or_equal:today']]);
        $on = $data['received_on'] ?? today()->toDateString();

        $total = 0;
        DB::transaction(function () use ($inventoryCategory, $on, $request, &$total): void {
            Sale::query()->where('inventory_category_id', $inventoryCategory->id)->whereColumn('received_amount', '<', 'total')->get()
                ->each(function (Sale $sale) use ($on, $request, &$total): void {
                    $total += (int) $sale->receive($sale->toReceive(), $on, $request->user()->id)?->amount;
                });
        });

        return back()->with('status', $total > 0 ? __('Rs :n received; nothing is left to receive.', ['n' => number_format($total)]) : __('Nothing is left to receive.'));
    }

    /**
     * The sale slip, ready to print.
     */
    public function show(Sale $sale): View
    {
        return view('sales.show', ['sale' => $sale->load(['items', 'category', 'creator'])]);
    }
}
