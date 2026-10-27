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

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('attachment');

        $purchase = Purchase::create($data + [
            'amount' => (int) round((float) $data['quantity'] * (float) $data['rate']),
            'attachment' => $this->storeAttachment($request, 'purchases'),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('inventory.index', ['tab' => 'purchases'])->with('status', $purchase->isPending()
            ? __('Purchase saved and sent for approval.')
            : __('Purchase saved.'));
    }
}
