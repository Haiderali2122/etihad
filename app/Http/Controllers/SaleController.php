<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\InventoryCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\RedirectResponse;
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

        return view('sales.create', [
            'category' => $inventoryCategory,
            'suggestions' => SaleItem::query()->whereHas('sale', fn ($q) => $q->where('inventory_category_id', $inventoryCategory->id))
                ->distinct()->orderBy('description')->limit(300)->pluck('description')->prepend($inventoryCategory->name)->unique()->values()->all(),
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

            return $sale;
        });

        return redirect()->route('sales.show', $sale)->with('status', __('Sale saved.'));
    }

    /**
     * The sale slip, ready to print.
     */
    public function show(Sale $sale): View
    {
        return view('sales.show', ['sale' => $sale->load(['items', 'category', 'creator'])]);
    }
}
