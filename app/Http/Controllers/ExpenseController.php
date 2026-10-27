<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\CashFloat;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Machine;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $headId = $request->integer('head') ?: null;

        return view('expenses.index', [
            'expenses' => Expense::query()->with(['head', 'user', 'machine'])
                ->when($headId, fn ($q) => $q->where('expense_head_id', $headId))
                ->latest('spent_on')->latest('id')->paginate(30)->withQueryString(),
            'floats' => CashFloat::query()->with('user')->latest('given_on')->take(10)->get(),
            'heads' => ExpenseHead::query()->orderBy('name')->get(),
            'headId' => $headId,
            'cashGiven' => (int) CashFloat::query()->sum('amount'),
            'cashSpent' => (int) Expense::query()->notRejected()->whereIn('user_id', CashFloat::query()->select('user_id'))->sum('amount'),
            'todaySpent' => (int) Expense::query()->notRejected()->whereDate('spent_on', today())->sum('amount'),
            'dailyLimit' => (int) Setting::get(Setting::ManagerDailyLimit, 0),
            'managers' => User::query()->active()->with('role')->orderBy('name')->get()
                ->filter(fn (User $u) => ! $u->isOwner() && $u->can('expenses.create'))->values(),
        ]);
    }

    public function create(): View
    {
        return view('expenses.create', [
            'heads' => ExpenseHead::query()->active()->orderBy('name')->get(),
            'machines' => Machine::query()->orderBy('code')->get(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = ['spent_on' => Carbon::parse($request->validated('spent_on'))->toDateString()] + $request->safe()->except('attachment');

        $expense = new Expense($data + [
            'user_id' => $user->id,
            'attachment' => $this->storeAttachment($request, 'expenses'),
            'created_by' => $user->id,
        ]);

        if (! $user->can('approve')) {
            $expense->status = $this->withinDailyLimit($user, (int) $data['amount'])
                ? ApprovalStatus::Approved
                : ApprovalStatus::Pending;
        }

        $expense->save();

        return redirect()->route('expenses.index')->with('status', $expense->isPending()
            ? __('Over the daily limit — sent to the owner for approval.')
            : __('Expense saved.'));
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
            ->whereDate('created_at', today())
            ->notRejected()
            ->sum('amount');

        return $alreadySpent + $amount <= $limit;
    }
}
