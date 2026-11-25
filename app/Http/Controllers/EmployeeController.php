<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Requests\EmployeeRequest;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), ['active', 'inactive', 'all'], true) ? $request->query('status') : 'active';

        $employees = Employee::query()->with('foreman')
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('designation', 'like', "%{$search}%")))
            ->withSum(['advances as open_advances' => fn ($q) => $q->approved()->whereNull('payroll_run_id')], 'amount')
            ->get()
            // By employee no. as numbers (1, 2, … 10, not 1, 10, 2); the same as the attendance machine number.
            ->sortBy(fn (Employee $e) => $e->code, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('employees.index', [
            'employees' => $employees,
            'search' => $search,
            'status' => $status,
            'payrollTotal' => (int) Employee::query()->active()->sum('monthly_salary'),
            'activeCount' => Employee::query()->active()->count(),
            'presentToday' => Employee::query()->active()->whereHas('attendances', fn ($q) => $q->whereDate('date', today())->where('status', AttendanceStatus::Present))->count(),
        ]);
    }

    /**
     * Opened from the Attendance machine page, the name and machine number come filled in.
     */
    public function create(Request $request): View
    {
        return $this->form(new Employee([
            'is_active' => true, 'joined_on' => today(),
            'name' => mb_substr(trim((string) $request->query('name')), 0, 120) ?: null,
            'device_code' => mb_substr(trim((string) $request->query('device_code')), 0, 30) ?: null,
        ]));
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create($this->attributes($request));

        return redirect()->route('employees.show', $employee)->with('status', __('Employee :name saved.', ['name' => $employee->name]));
    }

    public function show(Employee $employee): View
    {
        $month = now()->startOfMonth();

        return view('employees.show', [
            'employee' => $employee->load(['foreman', 'team']),
            'month' => $month,
            'attendance' => $employee->attendances()->whereBetween('date', [$month->copy(), $month->copy()->endOfMonth()])->get()->keyBy(fn ($a) => $a->date->day),
            'advances' => $employee->advances()->latest('given_on')->take(15)->get(),
            'loans' => $employee->loans()->withSum('repayments', 'amount')->latest('issued_on')->get(),
            'payslips' => $employee->payrollLines()->with('run')->latest('id')->take(12)->get(),
            'openAdvances' => $employee->outstandingAdvances(),
            'loanBalance' => $employee->loanBalance(),
            'canDelete' => ! $employee->hasHistory(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return $this->form($employee);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($this->attributes($request));

        return redirect()->route('employees.show', $employee)->with('status', __('Employee :name saved.', ['name' => $employee->name]));
    }

    /**
     * Takes the person off attendance and payroll, keeping their records under "Left / inactive". "left" also saves
     * today as the leaving date (pay up to today); "inactive" saves none. A foreman's team is left without a foreman
     * until one is chosen.
     */
    public function leave(Request $request, Employee $employee): RedirectResponse
    {
        $hasLeft = $request->validate(['reason' => ['required', 'in:left,inactive']])['reason'] === 'left';

        $employee->team()->update(['foreman_id' => null]);
        $employee->update(['is_active' => false, 'left_on' => $hasLeft ? ($employee->left_on ?? today()) : null]);

        return back()->with('status', $hasLeft
            ? __(':name marked as left. Their records are kept under Left / inactive.', ['name' => $employee->name])
            : __(':name marked as inactive. Their records are kept under Left / inactive.', ['name' => $employee->name]));
    }

    /**
     * Brings someone who left back onto the attendance sheet and payroll.
     */
    public function rejoin(Employee $employee): RedirectResponse
    {
        $employee->update(['is_active' => true, 'left_on' => null]);

        return back()->with('status', __(':name is active again.', ['name' => $employee->name]));
    }

    /**
     * Only a record with no attendance, advances, loans or payroll can be deleted; otherwise mark the person as left.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->hasHistory()) {
            return back()->withErrors(['employee' => __(':name has attendance or salary records. Set a leaving date and make them inactive instead.', ['name' => $employee->name])]);
        }

        $employee->team()->update(['foreman_id' => null]);
        $employee->delete();

        return redirect()->route('employees.index')->with('status', __('Employee :name deleted.', ['name' => $employee->name]));
    }

    private function form(Employee $employee): View
    {
        return view('employees.form', [
            'employee' => $employee,
            'foremen' => Employee::query()->where('is_foreman', true)->active()->whereKeyNot($employee->id ?? 0)->orderBy('name')->get(),
            'designations' => Designation::query()
                ->where(fn ($q) => $q->active()->orWhere('name', $employee->designation))
                ->orderBy('name')->get()
                ->mapWithKeys(fn (Designation $d) => [$d->name => $d->name_ur ? "{$d->name} · {$d->name_ur}" : $d->name]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(EmployeeRequest $request): array
    {
        return $request->validated() + [
            'is_foreman' => $request->boolean('is_foreman'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
