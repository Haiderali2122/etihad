<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Http\Requests\StoreLoanRequest;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanRepayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function index(Request $request): View
    {
        $show = in_array($request->query('show'), ['open', 'cleared', 'all'], true) ? $request->query('show') : 'open';
        $loans = Loan::query()->with('employee')->withSum('repayments', 'amount')->latest('issued_on')->get()
            ->filter(fn (Loan $l) => match ($show) {
                'open' => ! $l->isCleared() && $l->status !== ApprovalStatus::Rejected,
                'cleared' => $l->isCleared(),
                default => true,
            })->values();

        return view('loans.index', [
            'loans' => $loans,
            'show' => $show,
            'outstanding' => Loan::query()->approved()->withSum('repayments', 'amount')->get()->sum(fn (Loan $l) => $l->balance()),
            'monthlyDue' => Loan::query()->approved()->withSum('repayments', 'amount')->get()
                ->filter(fn (Loan $l) => ! $l->isCleared())->sum(fn (Loan $l) => min($l->monthly_instalment, $l->balance())),
        ]);
    }

    public function create(Request $request): View
    {
        return view('loans.create', [
            'employees' => Employee::query()->active()->orderBy('name')->get(),
            'employeeId' => $request->integer('employee') ?: null,
        ]);
    }

    public function store(StoreLoanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $loan = Loan::create(array_merge($data, [
            'first_instalment_month' => Carbon::createFromFormat('!Y-m', $data['first_instalment_month'])->startOfMonth()->toDateString(),
            'created_by' => $request->user()->id,
        ]));

        return redirect()->route('loans.show', $loan)->with('status', $loan->isPending()
            ? __('Loan saved and sent for approval.')
            : __('Loan saved.'));
    }

    /**
     * The loan statement; once the balance reaches zero it doubles as the clearance slip.
     */
    public function show(Loan $loan): View
    {
        return view('loans.show', ['loan' => $loan->load(['employee', 'repayments.payrollRun', 'creator'])->loadSum('repayments', 'amount')]);
    }

    /**
     * A cash repayment outside payroll.
     */
    /**
     * Cash paid back outside payroll, only on a loan the owner has approved and not dated before the loan was given.
     */
    public function repay(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($loan->status === ApprovalStatus::Approved, 409, __('Cash repayments can be recorded once the owner approves this loan.'));

        $loan->loadSum('repayments', 'amount');
        $data = $request->validate([
            'paid_on' => ['required', 'date', 'after_or_equal:'.$loan->issued_on->toDateString(), 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.$loan->balance()],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        LoanRepayment::create($data + ['loan_id' => $loan->id, 'created_by' => $request->user()->id]);

        return redirect()->route('loans.show', $loan)->with('status', __('Repayment of Rs :n recorded.', ['n' => number_format($data['amount'])]));
    }
}
