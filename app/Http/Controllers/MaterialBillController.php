<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Http\Requests\MaterialBillRequest;
use App\Models\ExpenseItem;
use App\Models\InventoryCategory;
use App\Models\MaterialBill;
use App\Models\MaterialBillItem;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Diesel, Glue and Ink bills. A bill is what is owed to the supplier; it is paid later, in parts or all at once,
 * and only the payments come off the manager's cash.
 */
class MaterialBillController extends Controller
{
    public function create(InventoryCategory $inventoryCategory): View
    {
        abort_unless($inventoryCategory->usesQuantity(), 404);

        return $this->form(new MaterialBill(['bill_on' => today(), 'inventory_category_id' => $inventoryCategory->id]), $inventoryCategory);
    }

    public function store(MaterialBillRequest $request): RedirectResponse
    {
        $category = InventoryCategory::query()->findOrFail($request->integer('inventory_category_id'));
        $bill = new MaterialBill(['inventory_category_id' => $category->id, 'created_by' => $request->user()->id]);

        DB::transaction(function () use ($bill, $request, $category): void {
            $this->fill($bill, $request, $category);
            $paidNow = min((int) $request->validated('paid_now'), $bill->total);
            if ($paidNow > 0) {
                $bill->pay($paidNow, $bill->bill_on, $request->user()->id);
            }
        });

        return redirect()->route('inventory.index', ['tab' => 'material-'.$category->id])
            ->with('status', __(':name bill of Rs :total saved; Rs :unpaid unpaid.', ['name' => $category->localName(), 'total' => number_format($bill->total), 'unpaid' => number_format($bill->refresh()->unpaid())]));
    }

    public function edit(MaterialBill $materialBill): View
    {
        return $this->form($materialBill->load('items'), $materialBill->category);
    }

    public function update(MaterialBillRequest $request, MaterialBill $materialBill): RedirectResponse
    {
        $total = collect($request->lines())->sum('amount');
        if ($total < $materialBill->paid_amount) {
            return back()->withInput()->withErrors(['total' => __('Rs :paid has already been paid on this bill, so the total cannot be less.', ['paid' => number_format($materialBill->paid_amount)])]);
        }

        DB::transaction(fn () => $this->fill($materialBill, $request, $materialBill->category));

        return redirect()->route('inventory.index', ['tab' => 'material-'.$materialBill->inventory_category_id])->with('status', __('Bill saved.'));
    }

    private function form(MaterialBill $bill, InventoryCategory $category): View
    {
        return view('material-bills.form', [
            'bill' => $bill,
            'category' => $category,
            'suggestions' => $category->items()->active()->pluck('name')
                ->merge(MaterialBillItem::query()->whereHas('bill', fn ($q) => $q->where('inventory_category_id', $category->id))->distinct()->pluck('description'))
                ->merge(ExpenseItem::query()->whereHas('expense', fn ($q) => $q->where('inventory_category_id', $category->id))->whereNotNull('description')->distinct()->pluck('description'))
                ->map(fn ($n) => trim((string) $n))->filter()->unique(fn ($n) => mb_strtolower($n))->sort()->values()->all(),
            'lastRates' => $this->lastRates($category),
            'suppliers' => Party::query()->active()->where('kind', PartyKind::Supplier)->orderBy('name')->pluck('name')->all(),
            'back' => route('inventory.index', ['tab' => 'material-'.$category->id]),
        ]);
    }

    /**
     * Each item's price per quantity the last time it was bought on this tab (bills, and the expense entries
     * made before bills), keyed by the lower-case item name.
     *
     * @return array<string, float>
     */
    private function lastRates(InventoryCategory $category): array
    {
        $fromExpenses = ExpenseItem::query()->join('expenses', 'expenses.id', '=', 'expense_items.expense_id')
            ->where('expenses.inventory_category_id', $category->id)->whereNotNull('expense_items.rate')->whereNotNull('expense_items.description')
            ->orderBy('expenses.spent_on')->orderBy('expense_items.id')
            ->get(['expense_items.description', 'expense_items.rate', 'expenses.spent_on as on']);
        $fromBills = MaterialBillItem::query()->join('material_bills', 'material_bills.id', '=', 'material_bill_items.material_bill_id')
            ->where('material_bills.inventory_category_id', $category->id)
            ->orderBy('material_bills.bill_on')->orderBy('material_bill_items.id')
            ->get(['material_bill_items.description', 'material_bill_items.rate', 'material_bills.bill_on as on']);

        return $fromExpenses->concat($fromBills)->sortBy(fn ($l) => (string) $l->on)
            ->mapWithKeys(fn ($l) => [mb_strtolower(trim((string) $l->description)) => (float) $l->rate])->all();
    }

    /**
     * Saves the bill and its lines; ink and glue lines go into stock under the item with that name.
     */
    private function fill(MaterialBill $bill, MaterialBillRequest $request, InventoryCategory $category): void
    {
        $lines = $request->lines();
        $bill->fill([
            'bill_on' => Carbon::parse($request->validated('spent_on'))->toDateString(),
            'supplier' => $request->validated('supplier') ?: null,
            'total' => (int) collect($lines)->sum('amount'),
        ]);
        if ($request->hasFile('attachment')) {
            $bill->attachment = $this->storeAttachment($request, 'bills');
        }
        $bill->save();

        $bill->items()->delete();
        foreach ($lines as $line) {
            $name = trim((string) $line['item']) ?: $category->name;
            $bill->items()->create([
                'inventory_item_id' => $category->addsStock() ? $category->stockItemNamed($name)->getKey() : null,
                'description' => $name, 'quantity' => $line['quantity'], 'rate' => $line['rate'], 'amount' => $line['amount'],
            ]);
        }
        $bill->syncPaid();
    }
}
