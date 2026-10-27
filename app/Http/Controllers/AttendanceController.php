<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Support\AttendanceImporter;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * The month at a glance: one row per employee, one square per day.
     */
    public function index(Request $request): View
    {
        $month = $this->month($request->query('month'));
        $end = $month->copy()->endOfMonth();

        $employees = Employee::query()->active()->orderBy('name')
            ->with(['attendances' => fn ($q) => $q->whereBetween('date', [$month, $end])])
            ->get();

        return view('attendance.index', [
            'month' => $month,
            'employees' => $employees,
            'days' => range(1, $month->daysInMonth),
            'lastDay' => $month->isSameMonth(today()) ? today()->day : $month->daysInMonth,
        ]);
    }

    /**
     * Mark everyone for one day.
     */
    public function day(Request $request): View
    {
        try {
            $date = Carbon::parse($request->query('date', today()->toDateString()))->startOfDay();
        } catch (InvalidFormatException) {
            $date = today();
        }

        return view('attendance.day', [
            'date' => $date,
            'employees' => Employee::query()->active()->orderBy('name')->get(),
            'records' => Attendance::query()->whereDate('date', $date)->get()->keyBy('employee_id'),
            'statuses' => AttendanceStatus::cases(),
            'standardHours' => Setting::payrollRules()['standard_hours'],
        ]);
    }

    public function saveDay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'rows' => ['required', 'array'],
            'rows.*.status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'rows.*.check_in' => ['nullable', 'date_format:H:i'],
            'rows.*.check_out' => ['nullable', 'date_format:H:i'],
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:16'],
            'rows.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $date = Carbon::parse($data['date'])->toDateString();
        $saved = 0;
        $ids = Employee::query()->whereIn('id', array_keys($data['rows']))->pluck('id');

        foreach ($ids as $id) {
            $row = $data['rows'][$id];
            if (empty($row['status'])) {
                Attendance::query()->where('employee_id', $id)->whereDate('date', $date)->delete();

                continue;
            }
            $present = $row['status'] === AttendanceStatus::Present->value;
            Attendance::forDay($id, $date)->fill([
                'status' => $row['status'],
                'check_in' => $present ? ($row['check_in'] ?? null) : null,
                'check_out' => $present ? ($row['check_out'] ?? null) : null,
                'overtime_minutes' => $present ? (int) round(((float) ($row['overtime_hours'] ?? 0)) * 60) : 0,
                'note' => $row['note'] ?? null,
                'source' => 'manual',
            ])->save();
            $saved++;
        }

        return redirect()->route('attendance.day', ['date' => $date])
            ->with('status', __('Attendance saved for :n people on :date.', ['n' => $saved, 'date' => Carbon::parse($date)->format('j M Y')]));
    }

    public function importForm(): View
    {
        return view('attendance.import', ['standardHours' => Setting::payrollRules()['standard_hours']]);
    }

    public function import(Request $request, AttendanceImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'overwrite' => ['boolean'],
        ]);

        $result = $importer->import($request->file('file')->getRealPath(), Setting::payrollRules()['standard_hours'], $request->boolean('overwrite'));

        return redirect()->route('attendance.import')->with('import', $result)
            ->with('status', __(':n days imported.', ['n' => $result['saved']]));
    }

    private function month(?string $value): Carbon
    {
        try {
            return Carbon::createFromFormat('!Y-m', (string) $value)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
