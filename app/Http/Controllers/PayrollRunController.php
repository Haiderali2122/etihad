<?php

namespace App\Http\Controllers;

use App\Models\ForemanCash;
use App\Models\PayrollRun;
use App\Support\ForemanTeams;
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

    /**
     * The "Salaries (10th)" button: the month being paid on the coming 10th. Up to the 21st that is last month
     * (paid on this month's 10th); from the 22nd on it is this month (paid next month). Made the first time.
     */
    public function tenth(Request $request, PayrollCalculator $calculator): RedirectResponse
    {
        $month = (today()->day <= 21 ? today()->subMonthNoOverflow() : today())->startOfMonth();
        $run = PayrollRun::query()->whereDate('month', $month->toDateString())->first();

        if (! $run) {
            if (! $request->user()->can('payroll.prepare')) {
                return redirect()->route('payroll.index');
            }
            $run = $calculator->prepare($month, $request->user());
        }

        return redirect()->route('payroll.show', $run);
    }

    public function show(PayrollRun $payrollRun): View
    {
        $lines = $payrollRun->lines()->with('employee.foreman')->get();
        // Each foreman followed by his team, then everyone else, as on the 22nd.
        $order = ForemanTeams::ordered($lines->pluck('employee')->filter())->pluck('id')->flip();
        $lines = $lines->sortBy(fn ($l) => $order[$l->employee_id] ?? PHP_INT_MAX)->values();

        return view('payroll.show', [
            'run' => $payrollRun->load('finalizer'),
            'lines' => $lines,
            'cash' => ForemanCash::query()->where('payday', ForemanCash::Final)->whereDate('month', $payrollRun->month->toDateString())->get()->keyBy('foreman_id'),
            'totals' => [
                'salary' => $lines->sum('monthly_salary'),
                'overtime' => $lines->sum('overtime_amount'),
                'absence' => $lines->sum('absence_deduction'),
                'advances' => $lines->sum('advances_deducted'),
                'loans' => $lines->sum('loan_deducted'),
                'net' => $lines->sum('net_pay'),
                'taken' => $lines->sum('taken_from_foreman'),
            ],
            'unmarked' => $lines->sum('unmarked_days'),
        ]);
    }

    /**
     * Hand edits on a draft: this month's loan instalment, bonus, other deductions and a note.
     */
    public function updateLines(Request $request, PayrollRun $payrollRun, PayrollCalculator $calculator): RedirectResponse
    {
        abort_unless($payrollRun->isDraft(), 409, __('This payroll is finalized. Reopen it to make changes.'));

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.loan_deducted' => ['required', 'integer', 'min:0'],
            'lines.*.bonus' => ['required', 'integer', 'min:0'],
            'lines.*.other_deduction' => ['required', 'integer', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
            'lines.*.taken_from_foreman' => ['nullable', 'integer', 'min:0'],
            'cash' => ['array'], 'cash.*' => ['nullable', 'integer', 'min:0'],
        ]);

        // Kharcha taken from the foreman between the 23rd and the 9th; a foreman's own is what he kept of his cash.
        $allLines = $payrollRun->lines()->with('employee')->get();
        $cash = collect($data['cash'] ?? [])->map(fn ($v) => (int) $v)->all();
        $taken = $allLines->mapWithKeys(fn ($l) => [$l->employee_id => (int) ($data['lines'][$l->id]['taken_from_foreman'] ?? 0)])->all();
        $errors = [];
        foreach (ForemanTeams::kept($allLines->pluck('employee')->filter(), $cash, $taken) as $foremanId => $kept) {
            if ($kept < 0) {
                $errors["cash.{$foremanId}"] = __('The team of :name took Rs :n more than the cash given to him.', ['name' => $allLines->firstWhere('employee_id', $foremanId)?->employee->name, 'n' => number_format(-$kept)]);
            }
            $taken[$foremanId] = max(0, $kept);
        }
        if ($errors) {
            return redirect()->route('payroll.show', $payrollRun)->withErrors($errors)->withInput();
        }
        foreach ($allLines->where('employee.is_foreman', true) as $foremanLine) {
            $amount = $cash[$foremanLine->employee_id] ?? 0;
            $row = ForemanCash::query()->where('foreman_id', $foremanLine->employee_id)->where('payday', ForemanCash::Final)->whereDate('month', $payrollRun->month->toDateString())->first();
            if ($amount <= 0) {
                $row?->delete();

                continue;
            }
            ($row ?? new ForemanCash(['foreman_id' => $foremanLine->employee_id, 'month' => $payrollRun->month->toDateString(), 'payday' => ForemanCash::Final, 'created_by' => $request->user()->id]))
                ->fill(['given_on' => min(today(), $payrollRun->pay_date ?? today())->toDateString(), 'amount' => $amount])->save();
        }

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
            $line->fill(collect($row)->except('taken_from_foreman')->all() + ['taken_from_foreman' => $taken[$line->employee_id] ?? 0]);
            $line->loan_overridden = (int) $row['loan_deducted'] !== $calculator->loanInstalments($line->employee, $payrollRun->month->copy()->endOfMonth());
            $line->net_pay = $line->computeNet();
            if ($line->taken_from_foreman > max(0, $line->net_pay)) {
                $errors["lines.{$line->id}.taken_from_foreman"] = __(':name took Rs :took but only Rs :n is left to pay; give the extra as a loan.', ['name' => $line->employee->name, 'took' => number_format($line->taken_from_foreman), 'n' => number_format(max(0, $line->net_pay))]);

                continue;
            }
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
