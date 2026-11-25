<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads an attendance export from the biometric device: the machine software's daily report (.xls, as exported
 * with its Export button) or a CSV. Three layouts are understood:
 *
 * - The daily report: AC-No., Name, Date, Clock In, Clock Out, Absent, OT Time (one row per person per day).
 *   "Absent" marks the day absent; a clock-in or clock-out marks it present, with the report's OT Time as overtime.
 * - One row per punch:  code, date-time  (or code, date, time)
 * - One row per day:    code, date, in, out
 *
 * People are matched by their attendance machine number (AC-No.), then by employee no., then by name; a person
 * matched by name has the machine number saved so the next import matches directly. Days marked by hand are left
 * alone unless $overwrite is set. A file with a single day imports just that day.
 */
class AttendanceImporter
{
    /**
     * @return array{saved: int, present: int, absent: int, skipped: int, unknown: list<string>, errors: list<string>, from: ?string, to: ?string}
     */
    public function import(string $path, float $standardHours, bool $overwrite = false): array
    {
        ['columns' => $columns, 'rows' => $rows] = $this->read($path);
        $result = ['saved' => 0, 'present' => 0, 'absent' => 0, 'skipped' => 0, 'unknown' => [], 'errors' => [], 'from' => null, 'to' => null];

        if ($rows->isEmpty()) {
            $result['errors'][] = __('The file has no rows.');

            return $result;
        }
        if (! in_array('code', $columns, true) && ! in_array('name', $columns, true)) {
            $result['errors'][] = __('No employee number column was found. Name it Code or AC-No.');
        }
        if (! in_array('datetime', $columns, true) && ! in_array('date', $columns, true)) {
            $result['errors'][] = __('No date or time column was found. Name it Time, or Date with In and Out.');
        }
        if ($result['errors'] !== []) {
            return $result;
        }

        $employees = Employee::query()->get();
        $shiftOf = $employees->pluck('shift', 'id')->all();
        $days = [];

        foreach ($rows as $number => $row) {
            $employee = $this->match($employees, $row);
            if (! $employee) {
                $label = trim(($row['code'] ?? '').' '.($row['name'] ?? ''));
                if ($label !== '') {
                    $result['unknown'][$label] = $label;
                }

                continue;
            }

            try {
                if (isset($row['datetime'])) {
                    $punches = array_filter([filled($row['datetime']) ? Carbon::parse($row['datetime']) : null]);
                    if ($punches === []) {
                        continue;
                    }
                    $date = $punches[0]->toDateString();
                } else {
                    $date = $this->date($row['date'] ?? '')?->toDateString();
                    if (! $date) {
                        continue;
                    }
                    $punches = array_values(array_filter([
                        filled($row['in'] ?? null) ? Carbon::parse($date.' '.$row['in']) : null,
                        filled($row['out'] ?? null) ? Carbon::parse($date.' '.$row['out']) : null,
                    ]));
                }
            } catch (\Throwable) {
                $result['errors'][] = __('Row :n: could not read the date or time.', ['n' => $number + 2]);

                continue;
            }

            $key = $employee->id.'|'.$date;
            $days[$key] ??= ['punches' => [], 'absent' => false, 'overtime' => null];
            $days[$key]['punches'] = array_merge($days[$key]['punches'], $punches);
            if ($this->truthy($row['absent'] ?? '') && $punches === []) {
                $days[$key]['absent'] = true;
            }
            if (isset($row['overtime']) && filled($row['overtime'])) {
                $days[$key]['overtime'] = ($days[$key]['overtime'] ?? 0) + $this->minutes($row['overtime']);
            }
        }

        foreach ($days as $key => $day) {
            [$employeeId, $date] = explode('|', $key);
            $punches = $day['punches'];
            if ($punches === [] && ! $day['absent']) {
                continue; // nothing recorded that day (e.g. a weekly off with no mark)
            }

            $existing = Attendance::query()->where('employee_id', $employeeId)->whereDate('date', $date)->first();
            if ($existing && $existing->source === 'manual' && ! $overwrite) {
                $result['skipped']++;

                continue;
            }

            usort($punches, fn (Carbon $a, Carbon $b) => $a <=> $b);
            $in = $punches[0] ?? null;
            $out = count($punches) > 1 ? end($punches) : null;
            $present = $punches !== [];

            ($existing ?? new Attendance(['employee_id' => $employeeId, 'date' => $date]))->fill([
                'status' => $present ? AttendanceStatus::Present : AttendanceStatus::Absent,
                'check_in' => $in?->format('H:i'),
                'check_out' => $out?->format('H:i'),
                // The report's own OT Time when it has one; otherwise time after the shift ends.
                'shift' => $shiftOf[$employeeId] ?? Attendance::DayShift,
                'overtime_minutes' => $present ? (int) ($day['overtime'] ?? Attendance::overtimeFor($date, $in?->format('H:i'), $out?->format('H:i'), $shiftOf[$employeeId] ?? Attendance::DayShift)) : 0,
                'source' => 'device',
            ])->save();

            $result['saved']++;
            $result[$present ? 'present' : 'absent']++;
            $result['from'] = $result['from'] === null || $date < $result['from'] ? $date : $result['from'];
            $result['to'] = $result['to'] === null || $date > $result['to'] ? $date : $result['to'];
        }

        $result['unknown'] = array_values($result['unknown']);

        return $result;
    }

    /**
     * The employee a row belongs to: by machine number, then employee no., then name. A person found by name
     * gets the row's machine number saved so the next import matches them directly.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  array<string, string>  $row
     */
    private function match(Collection $employees, array $row): ?Employee
    {
        $code = mb_strtolower(trim((string) ($row['code'] ?? '')));
        $name = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) ($row['name'] ?? ''))));

        if ($code !== '') {
            $found = $employees->first(fn (Employee $e) => $e->device_code !== null && mb_strtolower(trim($e->device_code)) === $code)
                ?? $employees->first(fn (Employee $e) => mb_strtolower(trim((string) $e->code)) === $code);
            if ($found) {
                return $found;
            }
        }

        if ($name !== '') {
            $found = $employees->first(fn (Employee $e) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $e->name))) === $name);
            if ($found && $code !== '' && blank($found->device_code)) {
                $found->forceFill(['device_code' => trim((string) $row['code'])])->save();
            }

            return $found;
        }

        return null;
    }

    private function date(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['d-M-y', 'd-M-Y', 'Y-m-d', 'd/m/Y', 'd.m.Y', 'd/m/y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date;
                }
            } catch (\Throwable) {
                // try the next format
            }
        }

        return Carbon::parse($value);
    }

    /**
     * "01:15" or "1.25" (hours) as minutes.
     */
    private function minutes(string $value): int
    {
        $value = trim($value);
        if (preg_match('/^(\d+):(\d{1,2})$/', $value, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }

        return is_numeric($value) ? (int) round((float) $value * 60) : 0;
    }

    private function truthy(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['true', 'yes', 'y', '1', 'a', 'absent'], true);
    }

    /**
     * Map the header to code / name / datetime or date / in / out / absent / overtime. A punch log with separate
     * date and time columns is read as one date-time per row.
     *
     * @return array{columns: list<string>, rows: Collection<int, array<string, string>>}
     */
    private function read(string $path): array
    {
        if (XlsReader::understands($path)) {
            $lines = XlsReader::rows($path);
        } else {
            $lines = [];
            $handle = fopen($path, 'r');
            while (($line = fgetcsv($handle)) !== false) {
                if ($line !== [null]) {
                    $lines[] = $line;
                }
            }
            fclose($handle);
        }

        $header = array_map(fn ($h) => preg_replace('/[^a-z]/', '', mb_strtolower((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), array_shift($lines) ?? []);

        $aliases = [
            'code' => ['acno', 'code', 'employeecode', 'empcode', 'empid', 'employeeid', 'userid', 'id', 'enrollno', 'badgenumber', 'no'],
            'name' => ['name', 'employeename', 'empname'],
            'datetime' => ['datetime', 'time', 'punchtime', 'checktime', 'timestamp', 'logtime'],
            'date' => ['date', 'day'],
            'in' => ['clockin', 'in', 'checkin', 'timein', 'intime'],
            'out' => ['clockout', 'out', 'checkout', 'timeout', 'outtime'],
            'absent' => ['absent'],
            'overtime' => ['ottime', 'overtime', 'othours', 'ot'],
        ];
        $map = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $name) {
                $index = array_search($name, $header, true);
                if ($index !== false && ! in_array($index, $map, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }
        $splitPunch = isset($map['date'], $map['datetime']) && ! isset($map['in']) && ! isset($map['out']);
        if (isset($map['date']) && ! $splitPunch) {
            unset($map['datetime']);
        }

        $rows = collect($lines)->map(function (array $line) use ($map, $splitPunch): array {
            $row = collect($map)->map(fn (int $index) => trim((string) ($line[$index] ?? '')))->all();
            if ($splitPunch) {
                $row['datetime'] = filled($row['datetime']) ? $row['date'].' '.$row['datetime'] : '';
            }

            return $row;
        })->filter(fn (array $row) => collect($row)->filter(fn ($v) => $v !== '')->isNotEmpty())->values();

        return ['columns' => array_keys($map), 'rows' => $rows];
    }
}
