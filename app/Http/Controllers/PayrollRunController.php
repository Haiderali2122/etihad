<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Support\PayrollCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PayrollRunController extends Controller
{
    public function index(): View
    {
        return view('payroll.index', [
            'runs' => PayrollRun::query()->withCount('lines')->withSum('lines', 'net_pay')->latest('month')->get(),
            'suggested' => now()->subMonthNoOverflow()->format('Y-m'),
        ]);
    }

    public function store(Request $request, PayrollCalculator $calculator): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = Carbon::createFromFormat('!Y-m', $data['month'])->startOfMonth();

        $existing = PayrollRun::query()->whereDate('month', $month)->first();
        if ($existing) {
            return redirect()->route('payroll.show', $existing);
        }

        $run = $calculator->prepare($month, $request->user());

        return redirect()->route('payroll.show', $run)->with('status', __('Payroll for :month prepared for :n people.', ['month' => $run->label(), 'n' => $run->lines->count()]));
    }

    public function show(PayrollRun $payrollRun): View
    {
        $lines = $payrollRun->lines()->with('employee')->get()->sortBy('employee.name')->values();

        return view('payroll.show', [
            'run' => $payrollRun->load('finalizer'),
            'lines' => $lines,
            'totals' => [
                'salary' => $lines->sum('monthly_salary'),
                'overtime' => $lines->sum('overtime_amount'),
                'absence' => $lines->sum('absence_deduction'),
                'advances' => $lines->sum('advances_deducted'),
                'loans' => $lines->sum('loan_deducted'),
                'net' => $lines->sum('net_pay'),
            ],
            'unmarked' => $lines->sum('unmarked_days'),
        ]);
    }

    /**
     * Hand edits on a draft: this month's loan instalment, bonus, other deductions and a note.
     */
    public function updateLines(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        abort_unless($payrollRun->isDraft(), 409, __('This payroll is finalized. Reopen it to make changes.'));

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.loan_deducted' => ['required', 'integer', 'min:0'],
            'lines.*.bonus' => ['required', 'integer', 'min:0'],
            'lines.*.other_deduction' => ['required', 'integer', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $errors = [];
        foreach ($payrollRun->lines()->with('employee')->get() as $line) {
            $row = $data['lines'][$line->id] ?? null;
            if (! $row) {
                continue;
            }
            $owed = $line->employee->loanBalance();
            if ($row['loan_deducted'] > $owed) {
                $errors["lines.{$line->id}.loan_deducted"] = __(':name only owes Rs :n on loans.', ['name' => $line->employee->name, 'n' => number_format($owed)]);

                continue;
            }
            $line->fill($row);
            $line->net_pay = $line->computeNet();
            $line->save();
        }

        $redirect = redirect()->route('payroll.show', $payrollRun);

        return $errors ? $redirect->withErrors($errors) : $redirect->with('status', __('Payroll saved.'));
    }

    public function recalculate(Request $request, PayrollRun $payrollRun, PayrollCalculator $calculator): RedirectResponse
    {
        $calculator->prepare($payrollRun->month, $request->user(), $request->boolean('reset'));

        return redirect()->route('payroll.show', $payrollRun)->with('status', __('Payroll recalculated from the latest attendance, advances and loans.'));
    }

    public function finalize(Request $request, PayrollRun $payrollRun, PayrollCalculator $calculator): RedirectResponse
    {
        abort_unless($payrollRun->isDraft(), 409);

        $earlier = $payrollRun->earlierDraft();
        if ($earlier) {
            return redirect()->route('payroll.show', $payrollRun)
                ->withErrors(['payroll' => __('Finalize :month first. Its advances and loan instalments come before this month.', ['month' => $earlier->label()])]);
        }

        $calculator->finalize($payrollRun, $request->user());

        return redirect()->route('payroll.show', $payrollRun)->with('status', __('Payroll for :month finalized. Advances and loan instalments are marked as deducted.', ['month' => $payrollRun->label()]));
    }

    public function reopen(PayrollRun $payrollRun, PayrollCalculator $calculator): RedirectResponse
    {
        abort_if($payrollRun->isDraft(), 409);
        $calculator->reopen($payrollRun);

        return redirect()->route('payroll.show', $payrollRun)->with('status', __('Payroll reopened as a draft.'));
    }

    /**
     * Printable salary slips: all of them, or one employee's with ?line=.
     */
    public function slips(Request $request, PayrollRun $payrollRun): View
    {
        $lines = $payrollRun->lines()->with('employee')
            ->when($request->integer('line'), fn ($q, $id) => $q->whereKey($id))
            ->get()->sortBy('employee.name')->values();

        return view('payroll.slips', ['run' => $payrollRun, 'lines' => $lines]);
    }
}
