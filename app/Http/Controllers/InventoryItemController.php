<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function create(Request $request): View
    {
        return $this->form(new InventoryItem([
            'inventory_category_id' => $request->integer('category') ?: null,
            'is_active' => true,
        ]));
    }

    public function store(InventoryItemRequest $request): RedirectResponse
    {
        $item = InventoryItem::create($this->attributes($request));

        return redirect()->route('inventory-items.show', $item)->with('status', __('Item :name saved.', ['name' => $item->name]));
    }

    /**
     * The item's ledger: every purchase in and every move out, with a running balance.
     */
    public function show(InventoryItem $inventoryItem): View
    {
        $inventoryItem->load('category')->loadSum(['purchases as stock_in' => fn ($q) => $q->notRejected()], 'quantity')->loadSum('issues as stock_out', 'quantity');

        $in = $inventoryItem->purchases()->with('party')->get()->map(fn ($p) => [
            'date' => $p->purchased_on, 'sort' => $p->purchased_on->format('Ymd').'0'.str_pad((string) $p->id, 8, '0', STR_PAD_LEFT),
            'kind' => 'in', 'qty' => (float) $p->quantity, 'who' => $p->party->name, 'ref' => $p->bill_no, 'status' => $p->status, 'model' => $p,
        ]);
        $out = $inventoryItem->issues()->with('machine')->get()->map(fn ($i) => [
            'date' => $i->issued_on, 'sort' => $i->issued_on->format('Ymd').'1'.str_pad((string) $i->id, 8, '0', STR_PAD_LEFT),
            'kind' => 'out', 'qty' => (float) $i->quantity, 'who' => $i->issued_to, 'ref' => $i->reference, 'status' => null, 'model' => $i,
        ]);

        $running = 0.0;
        $ledger = $in->concat($out)->sortBy('sort')->map(function (array $row) use (&$running): array {
            $counts = $row['kind'] === 'out' || $row['status']?->value !== 'rejected';
            if ($counts) {
                $running += $row['kind'] === 'in' ? $row['qty'] : -$row['qty'];
            }

            return $row + ['balance' => $running, 'counts' => $counts];
        })->reverse()->values();

        return view('inventory.items.show', ['item' => $inventoryItem, 'ledger' => $ledger]);
    }

    public function edit(InventoryItem $inventoryItem): View
    {
        return $this->form($inventoryItem);
    }

    public function update(InventoryItemRequest $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->update($this->attributes($request));

        return redirect()->route('inventory-items.show', $inventoryItem)->with('status', __('Item :name saved.', ['name' => $inventoryItem->name]));
    }

    /**
     * An item with purchases or moves out stays so its history is kept; deactivate it instead.
     */
    public function destroy(InventoryItem $inventoryItem): RedirectResponse
    {
        if ($inventoryItem->purchases()->exists() || $inventoryItem->issues()->exists()) {
            return back()->withErrors(['item' => __(':name has stock history. Deactivate it instead so the history stays.', ['name' => $inventoryItem->name])]);
        }

        $inventoryItem->delete();

        return redirect()->route('inventory.index')->with('status', __('Item :name deleted.', ['name' => $inventoryItem->name]));
    }

    private function form(InventoryItem $item): View
    {
        return view('inventory.items.form', [
            'item' => $item,
            'categories' => InventoryCategory::query()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $item->inventory_category_id))->orderBy('name')->get(),
            'units' => InventoryItem::query()->distinct()->orderBy('unit')->pluck('unit')->merge(['kg', 'litre', 'pcs', 'rims', 'packets', 'tins', 'rolls'])->unique()->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(InventoryItemRequest $request): array
    {
        return ['reorder_level' => $request->input('reorder_level') ?: 0] + $request->validated() + ['is_active' => $request->boolean('is_active')];
    }
}
