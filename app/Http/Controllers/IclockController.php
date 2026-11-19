<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Support\DeviceAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The ZKTeco machine's own protocol (ADMS / "Cloud Server"). The machine calls these addresses by itself with
 * its serial number; there is no login, so a machine is only listened to once it has been approved on the
 * Attendance machine page. Replies are plain text, as the machine expects.
 */
class IclockController extends Controller
{
    public function __construct(private DeviceAttendance $attendance) {}

    /**
     * GET: the machine asks for its settings when it starts. POST: it sends punches (ATTLOG) or users (OPERLOG,
     * USERINFO).
     */
    public function cdata(Request $request): Response
    {
        $device = $this->device($request);
        if (! $device) {
            return $this->text('OK');
        }

        if ($request->isMethod('get')) {
            return $this->text(implode("\n", [
                'GET OPTION FROM: '.$device->serial_number,
                // Everything not sent yet; "None" the first time, so all punches already on the machine come too.
                'ATTLOGStamp='.($device->attlog_stamp ?: 'None'),
                'OPERLOGStamp='.($device->operlog_stamp ?: 'None'),
                'ATTPHOTOStamp=None',
                'ErrorDelay=60',
                'Delay=30',
                'TransTimes=00:00;14:05',
                'TransInterval=1',
                // Send punches, user changes and new users as they happen (firmware 8.x reads the flags as digits).
                'TransFlag=1111000000',
                'ServerVer=2.4.1',
                'PushProtVer=2.4.1',
                'TimeZone=5',
                'Realtime=1',
                'Encrypt=None',
            ]));
        }

        if (! $device->is_approved) {
            return $this->text('OK');
        }

        $table = strtoupper((string) $request->query('table'));
        $body = (string) $request->getContent();
        $stamp = $request->query('Stamp');

        $count = match ($table) {
            'ATTLOG' => $this->attendance->recordPunches($device, $body),
            'OPERLOG', 'USERINFO', 'USER' => $this->attendance->recordUsers($device, $body),
            default => 0,
        };

        if ($stamp !== null && $stamp !== '') {
            match ($table) {
                'ATTLOG' => $device->forceFill(['attlog_stamp' => mb_substr((string) $stamp, 0, 40)])->save(),
                'OPERLOG' => $device->forceFill(['operlog_stamp' => mb_substr((string) $stamp, 0, 40)])->save(),
                default => null,
            };
        }

        return $this->text('OK: '.$count);
    }

    /**
     * The machine checks every few seconds for commands. Once approved, it is asked once for everyone
     * registered on it, so their names reach the site.
     */
    public function getrequest(Request $request): Response
    {
        $device = $this->device($request);
        if (! $device?->is_approved) {
            return $this->text('OK');
        }
        // The machine reports its firmware, user and punch counts here now and then.
        if ($request->filled('INFO')) {
            $device->forceFill(['info' => mb_substr((string) $request->query('INFO'), 0, 255)])->save();
        }
        // Once approved, it is asked once for everything it holds.
        if ($device->users_requested_at === null) {
            $device->forceFill(['users_requested_at' => now()])->save();
            $device->queueFullUpload();
        }

        return $this->text($device->takeCommand() ?? 'OK');
    }

    /**
     * The machine's answer to a command.
     */
    public function devicecmd(Request $request): Response
    {
        $device = $this->device($request);
        // The latest answers are kept so what the machine replied can be seen.
        $reply = trim(mb_substr((string) $request->getContent(), 0, 500));
        if ($device && $reply !== '') {
            $device->forceFill(['last_reply' => mb_substr(trim($reply."\n".$device->last_reply), 0, 2000)])->save();
        }

        return $this->text('OK');
    }

    /**
     * The machine named in the request, recorded the first time it calls in (waiting for approval).
     */
    private function device(Request $request): ?AttendanceDevice
    {
        $serial = trim((string) $request->query('SN'));
        if ($serial === '' || mb_strlen($serial) > 60 || ! preg_match('/^[A-Za-z0-9_-]+$/', $serial)) {
            return null;
        }

        $device = AttendanceDevice::query()->where('serial_number', $serial)->first();
        // A few unknown machines can wait for approval; beyond that, strangers are not recorded.
        if (! $device) {
            if (AttendanceDevice::query()->where('is_approved', false)->count() >= 5) {
                return null;
            }
            $device = AttendanceDevice::query()->create(['serial_number' => $serial]);
        }
        $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->save();

        return $device;
    }

    private function text(string $body): Response
    {
        return response($body."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
