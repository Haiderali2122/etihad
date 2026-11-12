<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\InventoryCategory;
use App\Models\Party;
use App\Models\Purchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function create(Request $request): View
    {
        return view('purchases.create', [
            'parties' => Party::query()->active()->where('kind', '!=', PartyKind::Press)->orderBy('name')->get(),
            'categories' => InventoryCategory::query()->active()
                ->with(['items' => fn ($q) => $q->active()->orderBy('name')])
                ->orderBy('name')->get()
                ->filter(fn (InventoryCategory $c) => $c->items->isNotEmpty()),
            'itemId' => $request->integer('item') ?: null,
        ]);
    }

    /**
     * Diesel, glue, ink and raddi have their own purchase form on their tab: supplier, quantity, rate and bill.
     * The purchase is booked on the material's own stock item (made the first time) and comes off the
     * manager's cash through Cash-out like every purchase.
     */
    public function createMaterial(InventoryCategory $inventoryCategory): View
    {
        $category = $inventoryCategory;
        abort_unless($category->show_as_tab && $category->is_active, 404);

        $item = $category->items()->active()->orderBy('id')->first()
            ?? $category->items()->create([
                'name' => $category->name, 'name_ur' => $category->name_ur,
                'unit' => mb_strtolower($category->name) === 'diesel' ? 'litre' : 'kg', 'is_active' => true,
            ]);

        return view('purchases.material', [
            'category' => $category,
            'item' => $item,
            'parties' => Party::query()->active()->where('kind', '!=', PartyKind::Press)->orderBy('name')->get(),
            'back' => route('inventory.index', ['tab' => 'material-'.$category->id]),
        ]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('attachment');

        $purchase = Purchase::create($data + [
            'amount' => (int) round((float) $data['quantity'] * (float) $data['rate']),
            'attachment' => $this->storeAttachment($request, 'purchases'),
            'created_by' => $request->user()->id,
        ]);

        $category = $purchase->item?->category;
        $tab = $category?->show_as_tab ? 'material-'.$category->id : 'purchases';

        return redirect()->route('inventory.index', ['tab' => $tab])->with('status', $purchase->isPending()
            ? __('Purchase saved and sent for approval.')
            : __('Purchase saved.'));
    }
}
