<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Models\DevicePunch;
use App\Models\DeviceUser;
use App\Models\Employee;
use App\Support\DeviceAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The attendance machine page: which machines are connected, the people registered on them and which employee
 * each one is, and the latest punches.
 */
class AttendanceMachineController extends Controller
{
    public function index(): View
    {
        $devices = AttendanceDevice::query()->orderByDesc('is_approved')->orderBy('id')->get();
        $users = DeviceUser::query()->with(['employee', 'device'])->get()
            ->sortBy(fn (DeviceUser $u) => [is_numeric($u->pin) ? (int) $u->pin : PHP_INT_MAX, $u->pin])->values();
        $names = $users->mapWithKeys(fn (DeviceUser $u) => [$u->pin => $u->employee?->name ?? $u->name]);

        return view('attendance.machine', [
            'devices' => $devices,
            'users' => $users,
            'employees' => Employee::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'device_code', 'is_active']),
            'punches' => DevicePunch::query()->latest('punched_at')->limit(30)->get(),
            'names' => $names,
            'serverHost' => parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);
    }

    /**
     * Start listening to a machine that called in, or stop.
     */
    public function approve(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $data = $request->validate(['approved' => ['required', 'boolean'], 'name' => ['nullable', 'string', 'max:120']]);
        $device->forceFill([
            'is_approved' => (bool) $data['approved'],
            'name' => $data['name'] ?? $device->name,
            // On approval the machine is asked again for everyone registered on it, and for all its punches.
            'users_requested_at' => $data['approved'] ? null : $device->users_requested_at,
            'attlog_stamp' => $data['approved'] ? null : $device->attlog_stamp,
            'operlog_stamp' => $data['approved'] ? null : $device->operlog_stamp,
        ])->save();

        return back()->with('status', $data['approved'] ? __('Machine :sn approved; its people and punches will arrive within a minute or two.', ['sn' => $device->serial_number]) : __('Machine :sn switched off.', ['sn' => $device->serial_number]));
    }

    /**
     * Asks the machine again for all its people and punches (nothing is counted twice).
     */
    public function fetch(AttendanceDevice $device): RedirectResponse
    {
        $device->queueFullUpload();

        return back()->with('status', __('Asked machine :sn for all its people and punches; they arrive within a few minutes.', ['sn' => $device->serial_number]));
    }

    /**
     * Everyone on the machine who is not on the staff list yet is added (name and machine number; salary later).
     */
    public function addAll(DeviceAttendance $attendance): RedirectResponse
    {
        $added = 0;
        DeviceUser::query()->whereNull('employee_id')->whereNotNull('name')->get()->each(function (DeviceUser $user) use ($attendance, &$added): void {
            if (! $attendance->employeeFor($user->device, $user->pin)) {
                $attendance->link($user, $attendance->newEmployee($user));
                $added++;
            }
        });

        return back()->with('status', trans_choice(':count person added to Staff; fill in their salary on their page.|:count people added to Staff; fill in their salary on their pages.', $added));
    }

    /**
     * Which employee a person on the machine is. Their past punches fill in their attendance straight away.
     */
    public function link(Request $request, DeviceUser $deviceUser, DeviceAttendance $attendance): RedirectResponse
    {
        $data = $request->validate(['employee_id' => ['nullable', 'exists:employees,id']]);
        $employee = $data['employee_id'] ? Employee::query()->find($data['employee_id']) : null;
        $attendance->link($deviceUser, $employee);

        return back()->with('status', $employee
            ? __(':pin :name is now :employee.', ['pin' => $deviceUser->pin, 'name' => $deviceUser->name, 'employee' => $employee->name])
            : __(':pin :name is not linked to anyone.', ['pin' => $deviceUser->pin, 'name' => $deviceUser->name]));
    }
}
