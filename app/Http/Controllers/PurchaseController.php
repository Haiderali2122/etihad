<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Http\Requests\StoreMaterialPurchaseRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\InventoryCategory;
use App\Models\Party;
use App\Models\Purchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // The material itself is the first item; more (e.g. Mitti ka tel under Diesel) are typed in under "Other…".
        if (! $category->items()->active()->exists()) {
            $category->items()->create([
                'name' => $category->name, 'name_ur' => $category->name_ur,
                'unit' => $category->defaultUnit(), 'is_active' => true,
            ]);
        }

        return view('purchases.material', [
            'category' => $category,
            'items' => $category->items()->active()->orderBy('id')->get(),
            'parties' => Party::query()->active()->where('kind', '!=', PartyKind::Press)->orderBy('name')->get(),
            'back' => route('inventory.index', ['tab' => 'material-'.$category->id]),
        ]);
    }

    /**
     * One bill on a material tab: each line becomes a purchase of that item, all sharing one receipt number.
     */
    public function storeMaterial(StoreMaterialPurchaseRequest $request, InventoryCategory $inventoryCategory): RedirectResponse
    {
        abort_unless($inventoryCategory->show_as_tab, 404);
        $data = $request->validated();
        $attachment = $this->storeAttachment($request, 'purchases');

        $first = DB::transaction(function () use ($data, $attachment, $request): Purchase {
            $purchases = collect($data['items'])->map(fn (array $line) => Purchase::create([
                'purchased_on' => $data['purchased_on'], 'party_id' => $data['party_id'],
                'inventory_item_id' => $line['inventory_item_id'], 'quantity' => $line['quantity'], 'rate' => $line['rate'],
                'amount' => (int) round((float) $line['quantity'] * (float) $line['rate']),
                'bill_no' => $data['bill_no'] ?? null, 'note' => $data['note'] ?? null,
                'attachment' => $attachment, 'created_by' => $request->user()->id,
            ]));
            $receiptNo = 'MP-'.str_pad((string) $purchases->first()->id, 5, '0', STR_PAD_LEFT);
            Purchase::query()->whereKey($purchases->pluck('id'))->update(['receipt_no' => $receiptNo]);

            return $purchases->first();
        });

        return redirect()->route('materials.receipt', $first)->with('status', __('Purchase saved.'));
    }

    /**
     * The printable receipt for one material bill: every line and the total.
     */
    public function materialReceipt(Purchase $purchase): View
    {
        $purchases = $purchase->receipt_no
            ? Purchase::query()->with(['item.category', 'party'])->where('receipt_no', $purchase->receipt_no)->orderBy('id')->get()
            : collect([$purchase->load(['item.category', 'party'])]);

        return view('purchases.receipt', [
            'receiptNo' => $purchase->receipt_no ?? 'PUR-'.str_pad((string) $purchase->id, 5, '0', STR_PAD_LEFT),
            'purchases' => $purchases,
            'first' => $purchases->first(),
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
