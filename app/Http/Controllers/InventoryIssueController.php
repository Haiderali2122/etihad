<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryIssueRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\Machine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryIssueController extends Controller
{
    public function create(Request $request): View
    {
        return view('inventory.issues.create', [
            'categories' => InventoryCategory::query()->active()->where('tracks_stock', true)
                ->with(['items' => fn ($q) => $q->active()->withStock()->orderBy('name')])
                ->orderBy('name')->get()
                ->filter(fn (InventoryCategory $c) => $c->items->isNotEmpty()),
            'itemId' => $request->integer('item') ?: null,
            'machines' => Machine::query()->orderBy('code')->get(),
        ]);
    }

    public function store(StoreInventoryIssueRequest $request): RedirectResponse
    {
        $issue = InventoryIssue::create($request->validated() + ['created_by' => $request->user()->id]);
        $issue->load('item');

        return redirect()->route('inventory.index', ['tab' => 'issues'])->with('status', __(':qty :unit :item moved out to :to.', [
            'qty' => InventoryItem::qty($issue->quantity), 'unit' => $issue->item->unit, 'item' => $issue->item->name, 'to' => $issue->issued_to,
        ]));
    }
}
