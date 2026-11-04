<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\InventoryCategory;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * Inventory in three tabs: stock on hand, purchases (stock in) and items moved out.
     */
    public function index(Request $request): View
    {
        // Petrol, diesel, glue and ink each have a tab of their own: "material-<category id>".
        $materialTabs = InventoryCategory::query()->active()->where('show_as_tab', true)->orderBy('name')->get();
        $material = preg_match('/^material-(\d+)$/', (string) $request->query('tab'), $m) ? $materialTabs->firstWhere('id', (int) $m[1]) : null;
        $tab = $material ? 'material-'.$material->id : (in_array($request->query('tab'), ['stock', 'purchases', 'issues'], true) ? $request->query('tab') : 'stock');
        $categoryId = $request->integer('category') ?: null;
        $inCategory = fn ($q) => $q->when($categoryId, fn ($q) => $q->whereHas('item', fn ($q) => $q->where('inventory_category_id', $categoryId)));

        $items = InventoryItem::query()->withStock()->with('category')
            ->whereHas('category', fn ($q) => $q->where('tracks_stock', true))
            ->when($categoryId, fn ($q) => $q->where('inventory_category_id', $categoryId))
            ->orderByDesc('is_active')->orderBy('name')
            ->get();

        $monthStart = now()->startOfMonth();

        return view('inventory.index', [
            'tab' => $tab,
            'items' => $items,
            'lowItems' => $items->where('is_active', true)->filter(fn (InventoryItem $i) => in_array($i->level(), ['low', 'out'], true))->values(),
            'purchases' => Purchase::query()->with(['party', 'item.category'])->tap($inCategory)
                ->latest('purchased_on')->latest('id')->paginate(25, ['*'], 'page')->withQueryString(),
            'issues' => InventoryIssue::query()->with(['item.category', 'machine'])->tap($inCategory)
                ->latest('issued_on')->latest('id')->paginate(25, ['*'], 'page')->withQueryString(),
            'monthTotal' => (int) Purchase::query()->notRejected()->tap($inCategory)->where('purchased_on', '>=', $monthStart)->sum('amount'),
            'pendingTotal' => (int) Purchase::query()->pending()->tap($inCategory)->sum('amount'),
            'pendingCount' => Purchase::query()->pending()->tap($inCategory)->count(),
            'issuedThisMonth' => InventoryIssue::query()->tap($inCategory)->where('issued_on', '>=', $monthStart)->count(),
            'purchaseCount' => Purchase::query()->tap($inCategory)->count(),
            'issueCount' => InventoryIssue::query()->tap($inCategory)->count(),
            'categories' => InventoryCategory::query()->orderBy('name')->get(),
            'materialTabs' => $materialTabs,
            'material' => $material,
            'materialItems' => $material ? InventoryItem::query()->withStock()->where('inventory_category_id', $material->id)->orderBy('name')->get() : collect(),
            'materialPayments' => $material ? Expense::query()->with(['head', 'user', 'source'])->where('inventory_category_id', $material->id)->latest('spent_on')->latest('id')->limit(50)->get() : collect(),
            'materialIssues' => $material ? InventoryIssue::query()->with(['item', 'machine'])->whereHas('item', fn ($q) => $q->where('inventory_category_id', $material->id))->latest('issued_on')->latest('id')->limit(20)->get() : collect(),
            'categoryId' => $categoryId,
        ]);
    }
}
