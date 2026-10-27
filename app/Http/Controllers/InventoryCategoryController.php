<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryCategoryRequest;
use App\Models\InventoryCategory;
use App\Models\Purchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryCategoryController extends Controller
{
    public function index(): View
    {
        return view('inventory.categories.index', [
            'categories' => InventoryCategory::query()->withCount('items')->orderByDesc('is_active')->orderBy('name')->get(),
            'purchased' => Purchase::query()->notRejected()
                ->join('inventory_items', 'inventory_items.id', '=', 'purchases.inventory_item_id')
                ->groupBy('inventory_items.inventory_category_id')
                ->selectRaw('inventory_items.inventory_category_id as category_id, SUM(purchases.amount) as total')
                ->pluck('total', 'category_id'),
        ]);
    }

    public function create(): View
    {
        return view('inventory.categories.form', ['category' => new InventoryCategory(['tracks_stock' => true, 'is_active' => true])]);
    }

    public function store(InventoryCategoryRequest $request): RedirectResponse
    {
        $category = InventoryCategory::create($this->attributes($request));

        return redirect()->route('inventory-categories.index')->with('status', __('Category :name saved.', ['name' => $category->name]));
    }

    public function edit(InventoryCategory $inventoryCategory): View
    {
        return view('inventory.categories.form', ['category' => $inventoryCategory]);
    }

    public function update(InventoryCategoryRequest $request, InventoryCategory $inventoryCategory): RedirectResponse
    {
        $inventoryCategory->update($this->attributes($request));

        return redirect()->route('inventory-categories.index')->with('status', __('Category :name saved.', ['name' => $inventoryCategory->name]));
    }

    /**
     * A category that still has items stays; deactivate it instead.
     */
    public function destroy(InventoryCategory $inventoryCategory): RedirectResponse
    {
        if ($inventoryCategory->items()->exists()) {
            return back()->withErrors(['category' => __(':name still has items. Move or delete them first, or deactivate the category.', ['name' => $inventoryCategory->name])]);
        }

        $inventoryCategory->delete();

        return redirect()->route('inventory-categories.index')->with('status', __('Category :name deleted.', ['name' => $inventoryCategory->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(InventoryCategoryRequest $request): array
    {
        return $request->validated() + [
            'tracks_stock' => $request->boolean('tracks_stock'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
