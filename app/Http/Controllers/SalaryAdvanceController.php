<?php

namespace App\Http\Controllers;

use App\Enums\AdvanceKind;
use App\Http\Requests\StoreSalaryAdvanceRequest;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Models\Setting;
use App\Support\PressMath;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalaryAdvanceController extends Controller
{
    public function index(Request $request): View
    {
        $show = in_array($request->query('show'), ['open', 'deducted', 'all'], true) ? $request->query('show') : 'open';
        $employeeId = $request->integer('employee') ?: null;

        return view('advances.index', [
            'advances' => SalaryAdvance::query()->with(['employee', 'foremanSheet.foreman', 'payrollRun'])
                ->when($show === 'open', fn ($q) => $q->notRejected()->whereNull('payroll_run_id'))
                ->when($show === 'deducted', fn ($q) => $q->whereNotNull('payroll_run_id'))
                ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
                ->latest('given_on')->latest('id')->paginate(30)->withQueryString(),
            'openTotal' => (int) SalaryAdvance::query()->approved()->whereNull('payroll_run_id')->sum('amount'),
            'monthTotal' => (int) SalaryAdvance::query()->approved()->where('given_on', '>=', now()->startOfMonth())->sum('amount'),
            'employees' => Employee::query()->orderBy('name')->get(),
            'show' => $show,
            'employeeId' => $employeeId,
        ]);
    }

    public function create(Request $request): View
    {
        return view('advances.create', [
            'employees' => Employee::query()->active()->orderBy('name')->get(),
            'employeeId' => $request->integer('employee') ?: null,
        ]);
    }

    public function store(StoreSalaryAdvanceRequest $request): RedirectResponse
    {
        $advance = SalaryAdvance::create($request->validated() + ['kind' => AdvanceKind::AdHoc, 'created_by' => $request->user()->id]);

        return redirect()->route('salary-advances.show', $advance)->with('status', $advance->isPending()
            ? __('Advance saved and sent for approval.')
            : __('Advance saved.'));
    }

    /**
     * A printable receipt the employee signs when taking the cash.
     */
    public function show(SalaryAdvance $salaryAdvance): View
    {
        return view('advances.show', ['advance' => $salaryAdvance->load(['employee', 'foremanSheet.foreman', 'payrollRun', 'creator'])]);
    }

    /**
     * The advance paid to everyone around the 22nd, pre-filled at the set percentage of salary.
     */
    public function midMonth(Request $request): View
    {
        $rules = Setting::payrollRules();
        $default = today()->day(min($rules['mid_month_day'], today()->daysInMonth));
        try {
            $date = Carbon::parse($request->query('date', $default->toDateString()));
        } catch (InvalidFormatException) {
            $date = $default;
        }
        $already = SalaryAdvance::query()->notRejected()->where('kind', AdvanceKind::MidMonth)
            ->whereBetween('given_on', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()->endOfDay()])
            ->pluck('amount', 'employee_id');

        return view('advances.mid-month', [
            'date' => $date,
            'percent' => $rules['mid_month_percent'],
            'already' => $already,
            'employees' => Employee::query()->active()->orderBy('name')->get()
                ->map(fn (Employee $e) => $e->setAttribute('suggested', PressMath::roundToTen($e->monthly_salary * $rules['mid_month_percent'] / 100))),
        ]);
    }

    public function storeMidMonth(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'given_on' => ['required', 'date'],
            'amounts' => ['required', 'array'],
            'amounts.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $given = Carbon::parse($data['given_on']);
        $alreadyPaid = SalaryAdvance::query()->notRejected()->where('kind', AdvanceKind::MidMonth)
            ->whereBetween('given_on', [$given->copy()->startOfMonth(), $given->copy()->endOfMonth()->endOfDay()])
            ->pluck('employee_id');

        $amounts = collect($data['amounts'])->map(fn ($v) => (int) $v)->filter();
        // Nobody gets this month's mid-month advance twice.
        $employees = Employee::query()->active()->whereIn('id', $amounts->keys())->whereNotIn('id', $alreadyPaid)->pluck('id');

        DB::transaction(function () use ($employees, $amounts, $data, $request): void {
            foreach ($employees as $id) {
                SalaryAdvance::create([
                    'employee_id' => $id, 'given_on' => $data['given_on'], 'amount' => $amounts[$id],
                    'kind' => AdvanceKind::MidMonth, 'created_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()->route('salary-advances.index')->with('status', __('Mid-month advance recorded for :n people, Rs :total in all.', [
            'n' => $employees->count(), 'total' => number_format($amounts->only($employees->all())->sum()),
        ]));
    }

    /**
     * Correct an advance before payroll has taken it. Foreman-sheet advances are changed on their sheet.
     */
    public function edit(SalaryAdvance $salaryAdvance): View|RedirectResponse
    {
        if ($blocked = $this->editBlocker($salaryAdvance)) {
            return redirect()->route('salary-advances.show', $salaryAdvance)->withErrors(['advance' => $blocked]);
        }

        return view('advances.create', [
            'advance' => $salaryAdvance,
            'employees' => Employee::query()->where(fn ($q) => $q->where('is_active', true)->orWhereKey($salaryAdvance->employee_id))->orderBy('name')->get(),
            'employeeId' => $salaryAdvance->employee_id,
        ]);
    }

    public function update(StoreSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance): RedirectResponse
    {
        if ($blocked = $this->editBlocker($salaryAdvance)) {
            return redirect()->route('salary-advances.show', $salaryAdvance)->withErrors(['advance' => $blocked]);
        }

        $salaryAdvance->fill($request->validated())->markEditedBy($request->user())->save();

        return redirect()->route('salary-advances.show', $salaryAdvance)->with('status', $salaryAdvance->isPending()
            ? __('Advance changed and sent to the owner for approval.')
            : __('Advance saved.'));
    }

    private function editBlocker(SalaryAdvance $advance): ?string
    {
        return match (true) {
            $advance->payroll_run_id !== null => __('This advance was already deducted from a finalized payroll. Reopen that payroll to change it.'),
            $advance->foreman_sheet_id !== null => __('This advance belongs to a foreman sheet; change it on the sheet.'),
            default => null,
        };
    }
}
