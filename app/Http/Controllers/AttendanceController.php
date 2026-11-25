<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\DevicePunch;
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
        // Attendance opens on today's sheet; the month grid is the Month button.
        if (! $request->filled('month') && $request->user()->can('attendance.manage')) {
            return $this->day($request);
        }

        $month = $this->month($request->query('month'));
        $end = $month->copy()->endOfMonth();

        $employees = Employee::query()->active()->byNumber()
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
     * Moves the ticked people to the day or the night shift. Days already recorded keep the shift they were worked on.
     */
    public function moveShift(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'shift' => ['required', Rule::in([Attendance::DayShift, Attendance::NightShift])],
        ], ['employee_ids.required' => __('Tick the people to move first.')]);

        $moved = Employee::query()->whereIn('id', $data['employee_ids'])->update(['shift' => $data['shift']]);

        return back()->with('status', trans_choice($data['shift'] === Attendance::NightShift
            ? ':count person moved to the night shift.|:count people moved to the night shift.'
            : ':count person moved to the day shift.|:count people moved to the day shift.', $moved));
    }

    /**
     * Whether the form changed the day: the times compared as H:i, overtime to the minute.
     */
    private function changedByHand(Attendance $day): bool
    {
        $time = fn ($v) => $v ? substr((string) $v, 0, 5) : null;

        return $day->getOriginal('status') !== $day->status
            || $time($day->getOriginal('check_in')) !== $time($day->check_in)
            || $time($day->getOriginal('check_out')) !== $time($day->check_out)
            || abs((int) $day->getOriginal('overtime_minutes') - (int) $day->overtime_minutes) > 1
            || (string) $day->getOriginal('note') !== (string) $day->note;
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

        $employees = Employee::query()->active()->byNumber()->get();

        return view('attendance.day', [
            'date' => $date,
            'employees' => $employees,
            // Every punch the machine recorded that day, per machine number, in time order.
            'punches' => DevicePunch::query()->whereDate('punched_at', $date)->orderBy('punched_at')->get()->groupBy('pin'),
            'records' => Attendance::query()->whereDate('date', $date)->get()->keyBy('employee_id'),
            'statuses' => AttendanceStatus::cases(),
            'standardHours' => Setting::payrollRules()['standard_hours'],
            'shift' => Attendance::shift(),
            'shifts' => [Attendance::DayShift => Attendance::shift(Attendance::DayShift), Attendance::NightShift => Attendance::shift(Attendance::NightShift)],
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
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:48'],
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
            $day = Attendance::forDay($id, $date);
            if (! $day->exists) {
                $day->shift = Employee::query()->whereKey($id)->value('shift') ?: Attendance::DayShift;
            }
            $day->fill([
                'status' => $row['status'],
                'check_in' => $present ? ($row['check_in'] ?? null) : null,
                'check_out' => $present ? ($row['check_out'] ?? null) : null,
                'overtime_minutes' => $present ? (int) round(((float) ($row['overtime_hours'] ?? 0)) * 60) : 0,
                'note' => $row['note'] ?? null,
            ]);
            // Only a row someone actually changed counts as entered by hand; untouched rows keep filling in from
            // the attendance machine.
            if ($day->exists && ! $this->changedByHand($day)) {
                continue;
            }
            $day->source = 'manual';
            $day->save();
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
            'file' => ['required', 'file', 'extensions:xls,csv,txt', 'max:10240'],
            'overwrite' => ['boolean'],
        ]);

        $result = $importer->import($request->file('file')->getRealPath(), Setting::payrollRules()['standard_hours'], $request->boolean('overwrite'));

        return redirect()->route('attendance.import')->with('import', $result)
            ->with('status', __(':n days imported: :p present, :a absent.', ['n' => $result['saved'], 'p' => $result['present'], 'a' => $result['absent']]));
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
