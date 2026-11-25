<?php

namespace App\Http\Controllers;

use App\Enums\AdvanceKind;
use App\Http\Requests\StoreSalaryAdvanceRequest;
use App\Models\Employee;
use App\Models\ForemanCash;
use App\Models\SalaryAdvance;
use App\Models\Setting;
use App\Support\ForemanTeams;
use App\Support\PressMath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
            'employees' => Employee::query()->byNumber()->get(),
            'show' => $show,
            'employeeId' => $employeeId,
        ]);
    }

    public function create(Request $request): View
    {
        return view('advances.create', [
            'employees' => Employee::query()->active()->byNumber()->get(),
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
     * The 22nd: each person's advance salary (about half the salary, changeable), less the kharcha they already
     * took from their foreman, gives what is handed over now. Saved amounts stay editable until the 10th's
     * payroll takes them. A foreman's "already taken" is what he kept of the cash he was given.
     */
    public function midMonth(Request $request): View
    {
        $rules = Setting::payrollRules();
        $month = $this->payMonth($request->query('month'));
        $records = $this->monthAdvances($month);
        // What each foreman was given between the 10th and the 21st, from the Foreman cash tab.
        $cash = ForemanCash::totals($month, ForemanCash::Advance);

        $employees = ForemanTeams::ordered(Employee::query()->active()->with('foreman')->get())
            ->each(function (Employee $e) use ($records, $rules): void {
                $record = $records->get($e->id);
                $e->setAttribute('advance_record', $record);
                $e->setAttribute('as_amount', $record?->amount ?? PressMath::roundToTen($e->monthly_salary * $rules['mid_month_percent'] / 100));
                $e->setAttribute('taken', (int) ($record?->taken_from_foreman ?? 0));
            });

        return view('advances.mid-month', [
            'month' => $month,
            'paidOn' => $records->first()?->given_on ?? $month->copy()->day(min($rules['mid_month_day'], $month->daysInMonth)),
            'percent' => $rules['mid_month_percent'],
            'employees' => $employees,
            'cash' => $cash,
            'locked' => $records->contains(fn (SalaryAdvance $a) => $a->payroll_run_id !== null),
        ]);
    }

    /**
     * One person's advance salary slip for the month: advance salary, what he already took from the foreman, and
     * what was handed over. Printed for him or the person collecting it.
     */
    public function midMonthSlip(Request $request, Employee $employee): View
    {
        $month = $this->payMonth($request->query('month'));
        $record = $this->monthAdvances($month)->get($employee->id);
        abort_unless($record, 404);

        return view('advances.mid-month-slip', ['employee' => $employee->load('foreman'), 'record' => $record, 'month' => $month]);
    }

    public function storeMidMonth(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'given_on' => ['required', 'date'],
            'as' => ['array'], 'as.*' => ['nullable', 'integer', 'min:0'],
            'taken' => ['array'], 'taken.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $month = $this->payMonth($data['month']);
        $records = $this->monthAdvances($month);
        abort_if($records->contains(fn (SalaryAdvance $a) => $a->payroll_run_id !== null), 409, __('This month\'s salaries are finalized. Reopen the payroll to change the advance salaries.'));

        $employees = Employee::query()->active()->get();
        // From a person's "Save & slip": only that person is saved; the rest of the sheet is left as it was.
        $only = $request->integer('only') ?: null;
        $as = collect($data['as'] ?? [])->map(fn ($v) => (int) $v)->all();
        $taken = collect($data['taken'] ?? [])->map(fn ($v) => (int) $v)->all();
        if ($only) {
            // One person: everyone else counts with what is already saved, so his kharcha comes off his foreman's cash.
            $taken = $records->map(fn (SalaryAdvance $a) => (int) $a->taken_from_foreman)->all();
            $taken[$only] = (int) ($data['taken'][$only] ?? 0);
        }
        $cash = ForemanCash::totals($month, ForemanCash::Advance);

        // A foreman's "already taken" is what he kept of the cash he was given.
        $errors = [];
        foreach (ForemanTeams::kept($employees, $cash, $taken) as $foremanId => $kept) {
            if ($kept < 0) {
                $errors["cash.{$foremanId}"] = __('The team of :name took Rs :n more than the cash given to him.', ['name' => $employees->firstWhere('id', $foremanId)->name, 'n' => number_format(-$kept)]);
            }
            $taken[$foremanId] = max(0, $kept);
        }
        // Saved with one person: his foreman's record (if already saved) gets the new "kept" too.
        $onlyForeman = $only ? ($employees->firstWhere('id', $only)?->is_foreman ? $only : $employees->firstWhere('id', $only)?->foreman_id) : null;
        if ($only) {
            $errors = array_filter($errors, fn ($k) => $k === "cash.{$onlyForeman}", ARRAY_FILTER_USE_KEY);
        }
        foreach ($employees as $e) {
            if ($only && $e->id !== $only) {
                continue;
            }
            $amount = $as[$e->id] ?? 0;
            $took = $taken[$e->id] ?? 0;
            if ($amount > $e->monthly_salary) {
                $errors["as.{$e->id}"] = __(':name\'s advance salary cannot be more than the salary (Rs :n).', ['name' => $e->name, 'n' => number_format($e->monthly_salary)]);
            } elseif ($took > $amount) {
                $errors["as.{$e->id}"] = __(':name already took Rs :took; raise the advance salary to at least that.', ['name' => $e->name, 'took' => number_format($took)]);
            }
        }
        if ($errors) {
            // A row saving itself as it is typed: the reason is shown on that row.
            if ($request->expectsJson()) {
                return response()->json(['message' => reset($errors), 'errors' => $errors], 422);
            }

            return back()->withErrors($errors)->withInput();
        }

        DB::transaction(function () use ($employees, $as, $taken, $data, $records, $request, $only, $onlyForeman): void {
            if ($only && $onlyForeman && $onlyForeman !== $only) {
                $records->get($onlyForeman)?->update(['taken_from_foreman' => $taken[$onlyForeman] ?? 0]);
            }
            foreach ($employees as $e) {
                if ($only && $e->id !== $only) {
                    continue;
                }
                $amount = $as[$e->id] ?? 0;
                $record = $records->get($e->id);
                if ($amount <= 0) {
                    $record?->delete();

                    continue;
                }
                // One person paid early (before the 22nd) is dated the day it was given.
                $on = $only && Carbon::parse($data['given_on'])->isFuture() ? today()->toDateString() : $data['given_on'];
                $values = ['given_on' => $on, 'amount' => $amount, 'taken_from_foreman' => $taken[$e->id] ?? 0];
                $record
                    ? $record->update($values)
                    : SalaryAdvance::create($values + ['employee_id' => $e->id, 'kind' => AdvanceKind::MidMonth, 'created_by' => $request->user()->id]);
            }
        });

        if ($only) {
            // Say whose cash it came off, so the manager sees it was taken.
            $expense = $this->monthAdvances($month)->get($only)?->cashOutExpense()->with('user')->first();
            $status = $expense
                ? __('Saved. Rs :n handed over comes off the cash of :name (Rs :left left).', ['n' => number_format($expense->amount), 'name' => $expense->user?->name, 'left' => number_format((int) $expense->user?->cashInHand())])
                : __('Saved. Nothing to hand over now, so no cash was taken.');

            if ($request->expectsJson()) {
                return response()->json(['message' => $status]);
            }

            return redirect()->route('salary-advances.mid-month.slip', ['month' => $month->format('Y-m'), 'employee' => $only])->with('status', $status);
        }

        $ids = $employees->pluck('id')->all();
        $total = collect($as)->only($ids)->sum() - collect($taken)->only($ids)->sum();

        return redirect()->route('salary-advances.mid-month', ['month' => $month->format('Y-m')])
            ->with('status', __('Advance salaries for :month saved. Rs :n to hand over now.', ['month' => $month->format('F Y'), 'n' => number_format(max(0, $total))]));
    }

    private function payMonth(mixed $value): Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::createFromFormat('!Y-m', $value)->startOfMonth() : today()->startOfMonth();
        } catch (\Throwable) {
            return today()->startOfMonth();
        }
    }

    /**
     * This month's advance salary of each person (one per person), keyed by employee.
     *
     * @return Collection<int, SalaryAdvance>
     */
    private function monthAdvances(Carbon $month): Collection
    {
        return SalaryAdvance::query()->notRejected()->where('kind', AdvanceKind::MidMonth)
            ->whereBetween('given_on', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()->endOfDay()])
            ->orderBy('id')->get()->keyBy('employee_id');
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
            'employees' => Employee::query()->where(fn ($q) => $q->where('is_active', true)->orWhereKey($salaryAdvance->employee_id))->byNumber()->get(),
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
