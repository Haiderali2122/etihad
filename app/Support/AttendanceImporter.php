<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads an attendance export (CSV) from the biometric device. Two layouts are understood:
 *
 * - One row per punch:  code, date-time           (most devices' raw log)
 *                       code, date, time          (the same log with the date and time split)
 * - One row per day:    code, date, in, out
 *
 * The first and last punch of a day become check-in and check-out; time beyond the
 * standard day counts as overtime. Days marked by hand are left alone unless $overwrite is set.
 */
class AttendanceImporter
{
    /**
     * @return array{saved: int, skipped: int, unknown: list<string>, errors: list<string>}
     */
    public function import(string $path, float $standardHours, bool $overwrite = false): array
    {
        ['columns' => $columns, 'rows' => $rows] = $this->read($path);
        $result = ['saved' => 0, 'skipped' => 0, 'unknown' => [], 'errors' => []];

        if ($rows->isEmpty()) {
            $result['errors'][] = __('The file has no rows.');

            return $result;
        }
        if (! in_array('code', $columns, true)) {
            $result['errors'][] = __('No employee number column was found. Name it Code or AC-No.');
        }
        if (! in_array('datetime', $columns, true) && ! in_array('date', $columns, true)) {
            $result['errors'][] = __('No date or time column was found. Name it Time, or Date with In and Out.');
        }
        if ($result['errors'] !== []) {
            return $result;
        }

        $employees = Employee::query()->get()->keyBy(fn (Employee $e) => mb_strtolower(trim($e->code)));
        $days = [];

        foreach ($rows as $number => $row) {
            $code = mb_strtolower(trim((string) ($row['code'] ?? '')));
            if ($code === '') {
                continue;
            }
            if (! $employees->has($code)) {
                $result['unknown'][$row['code']] = (string) $row['code'];

                continue;
            }

            try {
                $punches = isset($row['datetime'])
                    ? array_filter([filled($row['datetime']) ? Carbon::parse($row['datetime']) : null])
                    : array_values(array_filter([
                        filled($row['in'] ?? null) ? Carbon::parse($row['date'].' '.$row['in']) : null,
                        filled($row['out'] ?? null) ? Carbon::parse($row['date'].' '.$row['out']) : null,
                    ]));
                if ($punches === []) {
                    continue;
                }
                $date = isset($row['datetime']) ? $punches[0]->toDateString() : Carbon::parse($row['date'])->toDateString();
            } catch (\Throwable) {
                $result['errors'][] = __('Row :n: could not read the date or time.', ['n' => $number + 2]);

                continue;
            }

            $key = $employees[$code]->id.'|'.$date;
            $days[$key] = array_merge($days[$key] ?? [], $punches);
        }

        foreach ($days as $key => $punches) {
            [$employeeId, $date] = explode('|', $key);
            $existing = Attendance::query()->where('employee_id', $employeeId)->whereDate('date', $date)->first();

            if ($existing && $existing->source === 'manual' && ! $overwrite) {
                $result['skipped']++;

                continue;
            }

            usort($punches, fn (Carbon $a, Carbon $b) => $a <=> $b);
            $in = $punches[0] ?? null;
            $out = count($punches) > 1 ? end($punches) : null;
            $worked = $in && $out ? $in->diffInMinutes($out) : 0;

            ($existing ?? new Attendance(['employee_id' => $employeeId, 'date' => $date]))->fill([
                'status' => AttendanceStatus::Present,
                'check_in' => $in?->format('H:i'),
                'check_out' => $out?->format('H:i'),
                'overtime_minutes' => (int) max(0, $worked - $standardHours * 60),
                'source' => 'device',
            ])->save();
            $result['saved']++;
        }

        $result['unknown'] = array_values($result['unknown']);

        return $result;
    }

    /**
     * Map the CSV header to code / datetime or code / date / in / out. A punch log with separate
     * date and time columns is read as one date-time per row.
     *
     * @return array{columns: list<string>, rows: Collection<int, array<string, string>>}
     */
    private function read(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle) ?: [];
        $header = array_map(fn ($h) => preg_replace('/[^a-z]/', '', mb_strtolower((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);

        $aliases = [
            'code' => ['code', 'acno', 'employeecode', 'empcode', 'empid', 'employeeid', 'userid', 'id', 'enrollno', 'badgenumber', 'no'],
            'datetime' => ['datetime', 'time', 'punchtime', 'checktime', 'timestamp', 'logtime'],
            'date' => ['date', 'day'],
            'in' => ['in', 'checkin', 'timein', 'intime'],
            'out' => ['out', 'checkout', 'timeout', 'outtime'],
        ];
        $map = [];
        foreach ($aliases as $field => $names) {
            foreach ($header as $index => $name) {
                if (in_array($name, $names, true) && ! in_array($index, $map, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }
        $splitPunch = isset($map['date'], $map['datetime']) && ! isset($map['in']) && ! isset($map['out']);
        if (isset($map['date']) && ! $splitPunch) {
            unset($map['datetime']);
        }

        $rows = collect();
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null]) {
                continue;
            }
            $row = collect($map)->map(fn (int $index) => trim((string) ($line[$index] ?? '')))->all();
            if ($splitPunch) {
                $row['datetime'] = filled($row['datetime']) ? $row['date'].' '.$row['datetime'] : '';
            }
            $rows->push($row);
        }
        fclose($handle);

        return ['columns' => array_keys($map), 'rows' => $rows];
    }
}
