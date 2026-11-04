<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\CashFloat;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\InventoryCategory;
use App\Models\Machine;
use App\Models\Setting;
use App\Models\User;
use App\Support\OtherOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $headId = $request->integer('head') ?: null;

        return view('expenses.index', [
            'expenses' => Expense::query()->with(['head', 'user', 'machine', 'source'])
                ->when($headId, fn ($q) => $q->where('expense_head_id', $headId))
                ->latest('spent_on')->latest('id')->paginate(30)->withQueryString(),
            'floats' => CashFloat::query()->with('user')->latest('given_on')->take(10)->get(),
            'heads' => ExpenseHead::query()->orderBy('name')->get(),
            'headId' => $headId,
            // Cash with the manager only goes down for what was spent from that cash; payments the owner
            // made himself still count in the spending totals.
            'cashGiven' => (int) CashFloat::query()->sum('amount'),
            'cashSpent' => (int) Expense::query()->notRejected()->whereIn('user_id', CashFloat::query()->select('user_id'))->sum('amount'),
            'totalSpent' => (int) Expense::query()->notRejected()->sum('amount'),
            'todaySpent' => (int) Expense::query()->notRejected()->whereDate('spent_on', today())->sum('amount'),
            'todayFromCash' => (int) Expense::query()->notRejected()->whereDate('spent_on', today())->whereIn('user_id', CashFloat::query()->select('user_id'))->sum('amount'),
            'dailyLimit' => (int) Setting::get(Setting::ManagerDailyLimit, 0),
            'managers' => User::query()->active()->with('role')->orderBy('name')->get()
                ->filter(fn (User $u) => ! $u->isOwner() && $u->can('expenses.create'))->values(),
        ]);
    }

    /**
     * Opened from a material tab (?category=), the payment is filed under that material.
     */
    public function create(Request $request): View
    {
        $category = InventoryCategory::query()->where('show_as_tab', true)->find($request->integer('category'));

        return view('expenses.create', [
            'expense' => new Expense([
                'spent_on' => today(),
                'inventory_category_id' => $category?->id,
                'expense_head_id' => $category ? OtherOption::findOrCreate(ExpenseHead::class, $category->name)->getKey() : null,
            ]),
            'category' => $category,
            'heads' => ExpenseHead::query()->active()->orderBy('name')->get(),
            'machines' => Machine::query()->orderBy('code')->get(),
            'back' => $category ? route('inventory.index', ['tab' => 'material-'.$category->id]) : route('expenses.index'),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $user = $request->user();
        $expense = new Expense(['user_id' => $user->id, 'created_by' => $user->id]);
        $this->fill($expense, $request);

        if (! $user->can('approve')) {
            $expense->status = $this->withinDailyLimit($user, $expense->amount)
                ? ApprovalStatus::Approved
                : ApprovalStatus::Pending;
        }

        DB::transaction(function () use ($expense, $request): void {
            $expense->save();
            $this->saveItems($expense, $request->validated('items'));
        });

        return redirect()->route('expenses.show', $expense)->with('status', $expense->isPending()
            ? __('Over the daily limit — sent to the owner for approval.')
            : __('Expense saved.'));
    }

    /**
     * The expense receipt (raseed): items, total and signatures, ready to print.
     */
    public function show(Expense $expense): View
    {
        return view('expenses.show', ['expense' => $expense->load(['items', 'head', 'user', 'machine', 'category', 'source'])]);
    }

    public function edit(Expense $expense): View
    {
        abort_if($expense->isLinked(), 409, __('This payment was made on another tab; change it there.'));

        return view('expenses.create', [
            'expense' => $expense->load('items'),
            'category' => $expense->category,
            'heads' => ExpenseHead::query()->where(fn ($q) => $q->where('is_active', true)->orWhereKey($expense->expense_head_id))->orderBy('name')->get(),
            'machines' => Machine::query()->orderBy('code')->get(),
            'back' => route('expenses.show', $expense),
        ]);
    }

    /**
     * The owner's changes stand; anyone else's changed expense goes back to the owner for approval.
     */
    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        abort_if($expense->isLinked(), 409, __('This payment was made on another tab; change it there.'));

        $this->fill($expense, $request);
        $user = $request->user();
        if ($user->can('approve')) {
            $expense->forceFill(['status' => ApprovalStatus::Approved, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
        } else {
            $expense->forceFill(['status' => ApprovalStatus::Pending, 'reviewed_by' => null, 'reviewed_at' => null]);
        }

        DB::transaction(function () use ($expense, $request): void {
            $expense->save();
            $expense->items()->delete();
            $this->saveItems($expense, $request->validated('items'));
        });

        return redirect()->route('expenses.show', $expense)->with('status', $expense->isPending()
            ? __('Changes saved and sent to the owner for approval.')
            : __('Expense saved.'));
    }

    /**
     * Header fields from the form; the amount is the total of the items and the reason lists them.
     */
    private function fill(Expense $expense, StoreExpenseRequest $request): void
    {
        $items = collect($request->validated('items'));

        $expense->fill([
            'spent_on' => Carbon::parse($request->validated('spent_on'))->toDateString(),
            'expense_head_id' => $request->validated('expense_head_id'),
            'machine_id' => $request->validated('machine_id'),
            'inventory_category_id' => $request->validated('inventory_category_id'),
            'paid_to' => $request->validated('paid_to'),
            'amount' => (int) $items->sum('amount'),
            'reason' => mb_substr($items->pluck('description')->implode(', '), 0, 255),
        ]);

        if ($request->hasFile('attachment')) {
            $expense->attachment = $this->storeAttachment($request, 'expenses');
        }
    }

    /**
     * @param  list<array{description: string, amount: int|string}>  $items
     */
    private function saveItems(Expense $expense, array $items): void
    {
        foreach ($items as $item) {
            $expense->items()->create(['description' => $item['description'], 'amount' => (int) $item['amount']]);
        }
    }

    /**
     * The manager may enter up to the owner's daily limit each day without asking; anything beyond waits for approval.
     *
     * The limit counts what was entered today, whatever date the spend carries, so backdating cannot dodge it.
     */
    private function withinDailyLimit(User $user, int $amount): bool
    {
        $limit = (int) Setting::get(Setting::ManagerDailyLimit, 0);

        if ($limit <= 0) {
            return false;
        }

        $alreadySpent = (int) Expense::query()
            ->where('user_id', $user->id)
            ->whereNull('source_type')
            ->whereDate('created_at', today())
            ->notRejected()
            ->sum('amount');

        return $alreadySpent + $amount <= $limit;
    }
}
