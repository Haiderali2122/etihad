<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashFloatRequest;
use App\Models\CashFloat;
use Illuminate\Http\RedirectResponse;

class CashFloatController extends Controller
{
    /**
     * The owner records cash handed to the manager.
     */
    public function store(StoreCashFloatRequest $request): RedirectResponse
    {
        $float = CashFloat::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('expenses.index')
            ->with('status', __('Rs :amount given to :name.', ['amount' => number_format($float->amount), 'name' => $float->user->name]));
    }
}
