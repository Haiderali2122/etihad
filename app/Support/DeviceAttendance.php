<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\Designation;
use App\Models\DevicePunch;
use App\Models\DeviceUser;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns what the ZKTeco machine sends (ADMS push) into attendance. People on the machine are matched to employees
 * by machine number (AC-No.) or by name; each day's first punch is the time in and the last the time out. Days
 * someone changed by hand are left alone.
 */
class DeviceAttendance
{
    /**
     * ATTLOG lines: "PIN<TAB>YYYY-MM-DD HH:MM:SS<TAB>state<TAB>verify…". Returns how many lines were read.
     */
    public function recordPunches(AttendanceDevice $device, string $body): int
    {
        $count = 0;
        $days = [];

        foreach ($this->lines($body) as $line) {
            $parts = explode("\t", $line);
            $pin = trim($parts[0] ?? '');
            try {
                $at = Carbon::parse(trim($parts[1] ?? ''));
            } catch (\Throwable) {
                continue;
            }
            // Punches from when the machine's clock was wrong (year 2000, or in the future) are skipped.
            if ($pin === '' || ! isset($parts[1]) || $at->year < 2020 || $at->gt(now()->addDay())) {
                continue;
            }

            $count++;
            DevicePunch::query()->insertOrIgnore([
                'attendance_device_id' => $device->id, 'pin' => $pin, 'punched_at' => $at->format('Y-m-d H:i:s'),
                'state' => is_numeric($parts[2] ?? null) ? (int) $parts[2] : null,
                'verify' => is_numeric($parts[3] ?? null) ? (int) $parts[3] : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $days[$pin][$at->copy()->subDay()->toDateString()] = true;
            $days[$pin][$at->toDateString()] = true;
        }

        foreach ($days as $pin => $dates) {
            if ($employee = $this->employeeFor($device, (string) $pin)) {
                ksort($dates);
                foreach (array_keys($dates) as $date) {
                    $this->syncDay($employee, $date);
                }
            }
        }

        return $count;
    }

    /**
     * The people registered on the machine. OPERLOG sends "USER PIN=1<TAB>Name=…" lines; USERINFO sends the same
     * without the "USER " prefix. Other lines (fingerprints, faces, admin actions) are skipped.
     */
    public function recordUsers(AttendanceDevice $device, string $body): int
    {
        $count = 0;
        foreach ($this->lines($body) as $line) {
            if (str_starts_with($line, 'USER ')) {
                $line = substr($line, 5);
            } elseif (! str_starts_with($line, 'PIN=')) {
                continue;
            }

            $fields = [];
            foreach (explode("\t", $line) as $pair) {
                [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $fields[trim($key)] = trim($value);
            }
            $pin = $fields['PIN'] ?? '';
            if ($pin === '') {
                continue;
            }

            $user = DeviceUser::query()->firstOrNew(['attendance_device_id' => $device->id, 'pin' => $pin]);
            $oldName = $user->name;
            $user->name = ($fields['Name'] ?? '') !== '' ? mb_substr($fields['Name'], 0, 120) : $user->name;
            $user->save();
            if ($oldName && $user->name !== $oldName && $user->employee && $user->employee->name === static::staffName($oldName)) {
                $user->employee->update(['name' => static::staffName($user->name)]);
            }
            // Everyone on the machine is on the staff list: a person not matched to anyone is added.
            if (! $this->employeeFor($device, $pin) && filled($user->name)) {
                $this->link($user, $this->newEmployee($user));
            }
            $count++;
        }

        return $count;
    }

    /**
     * The employee behind a machine user ID: the one linked on the Attendance machine page, else the employee
     * with that machine number, else one with the same name (who then gets the machine number saved).
     */
    public function employeeFor(AttendanceDevice $device, string $pin): ?Employee
    {
        $user = DeviceUser::query()->firstOrCreate(['attendance_device_id' => $device->id, 'pin' => $pin]);
        if ($user->employee_id && $user->employee) {
            return $user->employee;
        }

        $employee = Employee::query()->where('device_code', $pin)->first();
        if (! $employee && filled($user->name)) {
            $name = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $user->name)));
            $employee = Employee::query()->whereNull('device_code')->get()
                ->first(fn (Employee $e) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $e->name))) === $name);
        }
        if ($employee) {
            $this->link($user, $employee);
        }

        return $employee;
    }

    /**
     * A staff member for someone on the machine: their name and machine number, designation Worker and salary 0
     * until the rest is filled in on their page.
     */
    public function newEmployee(DeviceUser $user): Employee
    {
        $code = $user->pin;
        for ($n = 2; Employee::query()->where('code', $code)->exists(); $n++) {
            $code = 'M'.$user->pin.($n > 2 ? '-'.$n : '');
        }
        $designation = Designation::query()->whereRaw('LOWER(name) = ?', ['worker'])->value('name')
            ?? Designation::query()->create(['name' => 'Worker', 'is_active' => true])->name;

        return Employee::query()->create([
            'code' => $code,
            'device_code' => $user->pin,
            'name' => static::staffName((string) $user->name),
            'designation' => $designation,
            // A new worker joins the team of the foreman who leads most of the staff.
            'foreman_id' => Employee::query()->active()->where('is_foreman', true)->withCount('team')->orderByDesc('team_count')->value('id'),
            'monthly_salary' => 0,
            'is_active' => true,
            'notes' => __('Added from the attendance machine on :date; fill in salary and other details.', ['date' => today()->format('j M Y')]),
        ]);
    }

    /**
     * A machine name as a staff name: "ZEESHAN SHANI" -> "Zeeshan Shani".
     */
    public static function staffName(string $machineName): string
    {
        return mb_convert_case(mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $machineName))), MB_CASE_TITLE);
    }

    /**
     * Ties a person on the machine to an employee, and fills in every day they already punched.
     */
    public function link(DeviceUser $user, ?Employee $employee): void
    {
        if (! $employee && $user->employee_id) {
            Employee::query()->whereKey($user->employee_id)->where('device_code', $user->pin)->update(['device_code' => null]);
        }
        if ($employee) {
            Employee::query()->where('device_code', $user->pin)->whereKeyNot($employee->id)->update(['device_code' => null]);
            $employee->forceFill(['device_code' => $user->pin])->save();
        }
        $user->forceFill(['employee_id' => $employee?->id])->save();

        if ($employee) {
            DevicePunch::query()->where('pin', $user->pin)->selectRaw('DATE(punched_at) as day')->distinct()->pluck('day')
                ->each(fn ($day) => $this->syncDay($employee, (string) $day));
        }
    }

    /**
     * One employee's shift day from their punches: first punch in, last punch out, and overtime by the shift's rules. A day changed by hand on the day sheet is kept as it is.
     */
    public function syncDay(Employee $employee, string $date): void
    {
        if (blank($employee->device_code)) {
            return;
        }
        $kind = $employee->shift === Attendance::NightShift ? Attendance::NightShift : Attendance::DayShift;
        $punches = $this->shiftPunches((string) $employee->device_code, Carbon::parse($date), $kind);
        $day = Attendance::forDay($employee->id, $date);

        if ($punches->isEmpty()) {
            // e.g. the next morning's punch turned out to close an overnight stay, not open a new day.
            if ($day->exists && $day->source === 'device') {
                $day->delete();
            }

            return;
        }

        $in = $punches->first();
        $out = $punches->count() > 1 ? $punches->last() : null;

        // A day saved by hand keeps what was entered; punches only fill an In or Out left empty (e.g. the
        // clock-out arriving after the sheet was saved), and overtime follows a newly filled Out.
        if ($day->exists && $day->source === 'manual') {
            if (! $day->isPresent()) {
                return;
            }
            $fillOut = blank($day->check_out) && $out !== null;
            $day->fill(array_filter([
                'check_in' => blank($day->check_in) ? $in->format('H:i') : null,
                'check_out' => $fillOut ? $out->format('H:i') : null,
                'overtime_minutes' => $fillOut && ! $day->overtime_minutes
                    ? Attendance::overtimeFor($date, $day->check_in ?: $in->format('H:i'), $out->format('H:i'), $day->shiftKind()) : null,
            ], fn ($v) => $v !== null));
            if ($day->isDirty()) {
                $day->save();
            }

            return;
        }

        $day->fill([
            'status' => AttendanceStatus::Present,
            'shift' => $kind,
            'check_in' => $in->format('H:i'),
            'check_out' => $out?->format('H:i'),
            'overtime_minutes' => $out ? Attendance::overtimeFor($date, $in->format('H:i'), $out->format('H:i'), $kind) : 0,
            'source' => 'device',
        ])->save();
    }

    /**
     * The punches that belong to one shift day.
     *
     * Night shift: from noon that day to noon the next (in at 9 PM, out at 5 AM).
     * Day shift: that day's punches. A morning punch the next day closes the day instead of opening a new one
     * when it is that day's only punch (the person went home after it) and the day before was left open (an odd
     * number of punches): in at 9 AM, out at 9 AM the next morning.
     *
     * @return Collection<int, Carbon>
     */
    private function shiftPunches(string $pin, Carbon $day, string $kind): Collection
    {
        $between = fn (Carbon $from, Carbon $to) => DevicePunch::query()->where('pin', $pin)
            ->whereBetween('punched_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
            ->orderBy('punched_at')->pluck('punched_at');

        if ($kind === Attendance::NightShift) {
            return $between($day->copy()->setTime(12, 0), $day->copy()->addDay()->setTime(11, 59, 59));
        }

        $own = $between($day->copy()->startOfDay(), $day->copy()->endOfDay());
        if ($this->stayedOver($between, $day->copy()->subDay())) {
            $own = $own->slice(1)->values();
        }
        if ($this->stayedOver($between, $day)) {
            $own->push($between($day->copy()->addDay()->startOfDay(), $day->copy()->addDay()->setTime(11, 59, 59))->first());
        }

        return $own->values();
    }

    /**
     * Whether a day-shift day was left open and closed by the next morning's only punch.
     */
    private function stayedOver(\Closure $between, Carbon $day): bool
    {
        $own = $between($day->copy()->startOfDay(), $day->copy()->endOfDay());
        $next = $between($day->copy()->addDay()->startOfDay(), $day->copy()->addDay()->endOfDay());

        return $own->count() % 2 === 1 && $next->count() === 1 && $next->first()->hour < 12;
    }

    /**
     * @return list<string>
     */
    private function lines(string $body): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $body) ?: []), fn ($l) => $l !== ''));
    }
}
