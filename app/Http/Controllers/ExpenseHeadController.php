<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseHeadRequest;
use App\Models\ExpenseHead;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExpenseHeadController extends Controller
{
    /**
     * Colours offered in the picker; they match the dashboard chart palette.
     *
     * @var list<string>
     */
    public const Palette = ['#262B7E', '#3E6FC1', '#7FA0DE', '#B9CBEE', '#D69A2D', '#8A8FB8', '#C7CADB', '#5A9C7D', '#9A6FB0', '#B0B5C9', '#E2A79B', '#C2412D'];

    public function index(): View
    {
        return view('expense-heads.index', [
            'heads' => ExpenseHead::query()
                ->withCount('expenses')
                ->withSum(['expenses' => fn ($q) => $q->notRejected()], 'amount')
                ->withSum(['expenses as month_amount' => fn ($q) => $q->notRejected()->where('spent_on', '>=', now()->startOfMonth())], 'amount')
                ->orderByDesc('is_active')->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('expense-heads.form', ['head' => new ExpenseHead(['color' => self::Palette[1], 'is_active' => true]), 'palette' => self::Palette]);
    }

    public function store(ExpenseHeadRequest $request): RedirectResponse
    {
        $head = ExpenseHead::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('expense-heads.index')->with('status', __('Expense head :name saved.', ['name' => $head->name]));
    }

    public function edit(ExpenseHead $expenseHead): View
    {
        return view('expense-heads.form', ['head' => $expenseHead, 'palette' => self::Palette]);
    }

    public function update(ExpenseHeadRequest $request, ExpenseHead $expenseHead): RedirectResponse
    {
        $expenseHead->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('expense-heads.index')->with('status', __('Expense head :name saved.', ['name' => $expenseHead->name]));
    }

    /**
     * A head with expenses stays so the roznamcha keeps its history; it can be deactivated instead.
     */
    public function destroy(ExpenseHead $expenseHead): RedirectResponse
    {
        if ($expenseHead->expenses()->exists()) {
            return back()->withErrors(['head' => __(':name has expenses recorded against it. Deactivate it instead so the history stays.', ['name' => $expenseHead->name])]);
        }

        $expenseHead->delete();

        return redirect()->route('expense-heads.index')->with('status', __('Expense head :name deleted.', ['name' => $expenseHead->name]));
    }
}
