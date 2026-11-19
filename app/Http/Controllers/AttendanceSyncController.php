<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Support\DeviceAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The attendance reader program on an office computer reads the machine over the office network (it does not
 * send punches by itself) and posts the people and punches here, with the machine's serial number and its key.
 */
class AttendanceSyncController extends Controller
{
    public function __construct(private DeviceAttendance $attendance) {}

    public function store(Request $request): JsonResponse
    {
        $device = AttendanceDevice::query()->where('serial_number', (string) $request->input('serial'))->first();
        if (! $device || ! $device->is_approved || blank($device->sync_key) || ! hash_equals($device->sync_key, (string) $request->input('key'))) {
            return response()->json(['ok' => false, 'error' => 'Unknown machine or wrong key.'], 403);
        }

        $people = $this->attendance->recordUsers($device, (string) $request->input('users', ''));
        $before = $device->punches()->count();
        $this->attendance->recordPunches($device, (string) $request->input('punches', ''));
        $new = $device->punches()->count() - $before;

        $result = __(':new new punches, :people people', ['new' => $new, 'people' => $people]);
        $device->forceFill(['last_synced_at' => now(), 'last_sync_result' => $result])->save();

        return response()->json(['ok' => true, 'new_punches' => $new, 'people' => $people, 'message' => $result]);
    }

    /**
     * The reader program for this machine. Its file name carries the machine's serial number, key and network
     * address, so double-clicking it is all the setup there is: it installs itself and starts with Windows.
     */
    public function program(AttendanceDevice $device): BinaryFileResponse
    {
        if (blank($device->sync_key)) {
            $device->forceFill(['sync_key' => Str::random(40)])->save();
        }
        $ip = explode(',', (string) $device->info)[4] ?? '';
        $ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '192.168.10.200';

        return response()->download(resource_path('reader/EtihadAttendance.exe'), 'EtihadAttendance_'.$device->serial_number.'_'.$device->sync_key.'_'.$ip.'.exe', [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * The settings file for the reader program: website, machine address, serial number and key.
     */
    public function settingsFile(AttendanceDevice $device): Response
    {
        if (blank($device->sync_key)) {
            $device->forceFill(['sync_key' => Str::random(40)])->save();
        }
        $ip = explode(',', (string) $device->info)[4] ?? '';

        $ini = implode("\r\n", [
            '[etihad]',
            'website = '.rtrim((string) config('app.url'), '/'),
            'machine_ip = '.($ip !== '' ? $ip : '192.168.10.200'),
            'serial = '.$device->serial_number,
            'key = '.$device->sync_key,
            'every_minutes = 5',
            '',
        ]);

        return response($ini, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="etihad-attendance.ini"',
        ]);
    }
}
