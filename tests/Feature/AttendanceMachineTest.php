<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\DevicePunch;
use App\Models\DeviceUser;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The ZKTeco machine sends punches straight to the site (ADMS). It is only listened to once approved; people on it
 * are matched to employees, and each day's first and last punch become the time in and out.
 */
class AttendanceMachineTest extends TestCase
{
    use RefreshDatabase;

    private const Serial = 'AXXH184460039';

    private function push(string $table, string $body, ?string $stamp = '100'): TestResponse
    {
        return $this->call('POST', '/iclock/cdata?SN='.self::Serial.'&table='.$table.($stamp ? '&Stamp='.$stamp : ''), [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);
    }

    public function test_a_new_machine_waits_for_approval_and_its_data_is_ignored_until_then(): void
    {
        $this->get('/iclock/cdata?SN='.self::Serial.'&options=all')->assertOk()->assertSee('ATTLOGStamp=None')->assertSee('TimeZone=5');
        $device = AttendanceDevice::query()->sole();
        $this->assertFalse($device->is_approved);
        $this->assertNotNull($device->last_seen_at);

        $this->push('ATTLOG', "1\t2026-11-18 08:58:00\t0\t15\t0\t0\n")->assertOk();
        $this->assertSame(0, DevicePunch::query()->count());
        $this->get('/iclock/getrequest?SN='.self::Serial)->assertOk()->assertSee('OK');

        // Odd serial numbers are not recorded at all.
        $this->get('/iclock/cdata?SN=bad%20serial')->assertOk();
        $this->assertSame(1, AttendanceDevice::query()->count());
    }

    public function test_an_approved_machine_sends_people_and_punches_that_fill_attendance(): void
    {
        $owner = User::factory()->owner()->create();
        $zeeshan = Employee::factory()->create(['name' => 'Zeeshan Shani', 'device_code' => null]);
        $bilal = Employee::factory()->create(['name' => 'Bilal Ahmed', 'device_code' => null]);
        $this->get('/iclock/cdata?SN='.self::Serial);
        $device = AttendanceDevice::query()->sole();

        $this->actingAs($owner)->patch(route('attendance.machine.approve', $device), ['approved' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($device->refresh()->is_approved);

        // Once approved the machine is asked for everyone on it, once.
        $this->get('/iclock/getrequest?SN='.self::Serial.'&INFO=Ver%208.0.4.2,52,0,486')->assertSee('C:1:CHECK');
        $this->get('/iclock/getrequest?SN='.self::Serial)->assertSee('C:2:DATA QUERY ATTLOG StartTime=2000-01-01 00:00:00');
        $this->get('/iclock/getrequest?SN='.self::Serial)->assertSee('C:3:DATA QUERY USERINFO');
        $this->get('/iclock/getrequest?SN='.self::Serial)->assertDontSee('C:');
        $this->call('POST', '/iclock/devicecmd?SN='.self::Serial, [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'ID=3&Return=0&CMD=DATA')->assertOk();
        $this->assertStringContainsString('Return=0', (string) $device->refresh()->last_reply);
        $this->assertStringStartsWith('Ver 8.0.4.2', (string) $device->info);

        $this->push('OPERLOG', "USER PIN=1\tName=ZEESHAN SHANI\tPri=0\tPasswd=\tCard=\tGrp=1\nFP PIN=1\tFID=0\tSize=100\tValid=1\tTMP=abc\nUSER PIN=7\tName=WORKER SEVEN\tPri=0\n", '200')
            ->assertOk()->assertSee('OK: 2');
        // Matched by name, so the machine number is saved on the employee.
        $this->assertSame('1', $zeeshan->refresh()->device_code);

        // "Fetch all again" asks for everything once more.
        $this->actingAs($owner)->post(route('attendance.machine.fetch', $device))->assertSessionHasNoErrors();
        $this->assertCount(3, $device->refresh()->pending_commands);
        $this->assertSame('200', $device->refresh()->operlog_stamp);

        $this->push('ATTLOG', "1\t2026-11-17 08:58:10\t0\t15\t0\t0\n1\t2026-11-17 13:01:00\t1\t15\n1\t2026-11-17 19:30:00\t1\t15\n7\t2026-11-17 09:10:00\t0\t15\n", '300')
            ->assertOk()->assertSee('OK: 4');
        // The punch from when the clock said year 2000 is skipped; the same punches sent again are not counted twice.
        $this->push('ATTLOG', "1\t2026-11-17 19:30:00\t1\t15\n", '301')->assertOk();
        // A punch from when the machine's clock said year 2000 is skipped.
        $this->push('ATTLOG', "1\t2000-02-01 01:05:00\t0\t15\n", '303')->assertOk()->assertSee('OK: 0');
        $this->assertSame(4, DevicePunch::query()->count());

        $day = Attendance::query()->where('employee_id', $zeeshan->id)->sole();
        $this->assertSame(AttendanceStatus::Present, $day->status);
        $this->assertStringStartsWith('08:58', (string) $day->check_in);
        $this->assertStringStartsWith('19:30', (string) $day->check_out);
        $this->assertSame('device', $day->source);
        // The day view lists every punch and the time worked.
        $this->actingAs($owner)->get(route('attendance.day', ['date' => '2026-11-17']))->assertOk()
            ->assertSeeInOrder(['8:58 AM', '1:01 PM', '7:30 PM'])->assertSee('10h 32m')->assertSee('All punches');

        // Worker 7 is not an employee yet: linking him fills in the day he already punched.
        // Someone on the machine who is not on the staff list is added: name, machine number, salary 0 for now.
        $added = Employee::query()->where('device_code', '7')->sole();
        $this->assertSame('Worker Seven', $added->name);
        $this->assertSame(0, (int) $added->monthly_salary);
        $this->actingAs($owner)->get(route('employees.index'))->assertOk()->assertSee('Worker Seven')->assertSee('Salary not set');
        $this->actingAs($owner)->get(route('attendance.machine'))->assertOk()->assertSee('WORKER SEVEN')->assertSee('Connected');
        $seven = DeviceUser::query()->where('pin', '7')->sole();
        $this->actingAs($owner)->patch(route('attendance.machine.link', $seven), ['employee_id' => $bilal->id])->assertSessionHasNoErrors();
        $this->assertSame('7', $bilal->refresh()->device_code);
        $this->assertSame(1, Attendance::query()->where('employee_id', $bilal->id)->count());

        // A day corrected by hand is not overwritten by later punches.
        $day->update(['source' => 'manual', 'check_out' => '18:00']);
        $this->push('ATTLOG', "1\t2026-11-17 20:15:00\t1\t15\n", '302');
        $this->assertStringStartsWith('18:00', (string) $day->refresh()->check_out);

        // A day saved by hand before the clock-out came gets only the missing Out (and its overtime) filled in.
        $day->update(['check_out' => null, 'overtime_minutes' => 0]);
        $this->push('ATTLOG', '1	2026-11-17 18:30:00	1	15
', '304');
        $day->refresh();
        $this->assertStringStartsWith('08:58', (string) $day->check_in);
        $this->assertStringStartsWith('20:15', (string) $day->check_out);
        $this->assertSame(195, $day->overtime_minutes);
        $this->assertSame('manual', $day->source);
    }

    public function test_the_employee_form_opens_with_the_machine_name_and_number(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner)->get(route('employees.create', ['name' => 'WORKER SEVEN', 'device_code' => '7']))->assertOk()
            ->assertSee('value="WORKER SEVEN"', false)->assertSee('value="7"', false);
    }

    public function test_staff_are_listed_by_number(): void
    {
        $owner = User::factory()->owner()->create();
        foreach (['10' => 'Aslam', '2' => 'Zubair', '1' => 'Majid', 'M3' => 'Bashir'] as $code => $name) {
            Employee::factory()->create(['code' => $code, 'name' => $name]);
        }

        $this->actingAs($owner)->get(route('employees.index'))->assertOk()->assertSeeInOrder(['Majid', 'Zubair', 'Aslam', 'Bashir']);
    }

    public function test_late_early_and_overtime_follow_the_9_to_5_shift_and_add_up_for_the_month(): void
    {
        $owner = User::factory()->owner()->create();
        $bilal = Employee::factory()->create(['name' => 'Bilal']);
        // In at 10:00 and out at 6 PM: 1 hour late and 1 hour overtime.
        $this->actingAs($owner)->put(route('attendance.save-day'), ['date' => '2026-11-17', 'rows' => [$bilal->id => ['status' => 'present', 'check_in' => '10:00', 'check_out' => '18:00', 'overtime_hours' => 1]]])->assertSessionHasNoErrors();
        // In at 9:08 and out at 4:30 PM: on time (within the 20 minutes' grace), 30 minutes early.
        $this->actingAs($owner)->put(route('attendance.save-day'), ['date' => '2026-11-18', 'rows' => [$bilal->id => ['status' => 'present', 'check_in' => '09:08', 'check_out' => '16:30']]])->assertSessionHasNoErrors();

        $day = Attendance::query()->whereDate('date', '2026-11-17')->sole();
        $this->assertSame(60, $day->lateMinutes());
        $this->assertSame(0, $day->earlyMinutes());
        $this->assertSame(60, $day->overtime_minutes);
        $this->assertSame('late', $day->tone());
        $this->assertSame(60, Attendance::overtimeAfterShift('18:00'));
        $second = Attendance::query()->whereDate('date', '2026-11-18')->sole();
        $this->assertSame(0, $second->lateMinutes());
        $this->assertSame(30, $second->earlyMinutes());
        $this->assertSame('present', $second->tone());
        // Up to 9:20 is on time; after that the whole time from 9:00 counts.
        foreach (['09:10' => 0, '09:20' => 0, '09:21' => 21, '09:25' => 25] as $in => $late) {
            $second->update(['check_in' => $in]);
            $this->assertSame($late, $second->refresh()->lateMinutes(), 'in at '.$in);
        }
        // Leaving from 4:45 is not early; up to 5:15 is not overtime; past them the whole time counts.
        foreach (['16:50' => 0, '16:45' => 0, '16:44' => 16] as $out => $early) {
            $second->update(['check_out' => $out]);
            $this->assertSame($early, $second->refresh()->earlyMinutes(), 'out at '.$out);
        }
        $second->update(['check_out' => '16:30']);
        $this->assertSame(0, Attendance::overtimeAfterShift('17:15'));
        $this->assertSame(40, Attendance::overtimeAfterShift('17:40'));
        $second->update(['check_in' => '09:08']);

        $this->actingAs($owner)->get(route('attendance.day', ['date' => '2026-11-18']))->assertOk()->assertSee('30m')->assertSee('late after 20 min');
        // The month adds them up: late 1h, early 30m, overtime 1h.
        $this->actingAs($owner)->get(route('attendance.index', ['month' => '2026-11']))->assertOk()
            ->assertSeeInOrder(['Bilal', '1h 00m', '30m', '1h 00m']);
        // Attendance opens on today; the month grid is one click away.
        $this->actingAs($owner)->get(route('attendance.index'))->assertOk()->assertSee(today()->translatedFormat('l j F Y'))->assertSee('Month')->assertSee(route('attendance.machine'));
    }

    public function test_the_office_reader_program_sends_punches_with_the_machine_key(): void
    {
        $owner = User::factory()->owner()->create();
        $device = AttendanceDevice::query()->create(['serial_number' => self::Serial, 'is_approved' => true]);
        $this->assertSame(40, strlen((string) $device->sync_key));
        $payload = ['serial' => self::Serial, 'users' => 'USER PIN=5	Name=ALI RAZA', 'punches' => '5	2026-11-19 09:20:00	0	15
5	2026-11-19 17:45:00	1	15'];

        // A wrong key is refused; the right one fills in the day.
        $this->postJson('/attendance-sync', $payload + ['key' => 'wrong'])->assertForbidden();
        $this->assertSame(0, DevicePunch::query()->count());
        $this->postJson('/attendance-sync', $payload + ['key' => $device->sync_key])->assertOk()->assertJson(['ok' => true, 'new_punches' => 2]);
        $this->postJson('/attendance-sync', $payload + ['key' => $device->sync_key])->assertOk()->assertJson(['new_punches' => 0]);

        $day = Attendance::query()->sole();
        $this->assertSame(0, $day->lateMinutes()); // 9:20 is within the 20 minutes' grace
        $this->assertSame(45, $day->overtime_minutes);
        $this->assertSame('Ali Raza', $day->employee->name);
        $this->assertNotNull($device->refresh()->last_synced_at);

        // The page offers the program and the settings file with the key.
        $this->actingAs($owner)->get(route('attendance.machine'))->assertOk()->assertSee('Reader program')->assertSee(route('attendance.machine.reader-program', $device));
        $this->actingAs($owner)->get(route('attendance.machine.reader-program', $device))->assertOk()
            ->assertDownload('EtihadAttendance_'.self::Serial.'_'.$device->sync_key.'_192.168.10.200.exe');
        $this->actingAs($owner)->get(route('attendance.machine.reader-settings', $device))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="etihad-attendance.ini"')->assertSee('key = '.$device->sync_key)->assertSee('serial = '.self::Serial);
    }

    public function test_saving_the_sheet_only_marks_rows_changed_by_hand_and_today_has_no_mark_all_buttons(): void
    {
        $owner = User::factory()->owner()->create();
        $ali = Employee::factory()->create(['name' => 'Ali']);
        $sara = Employee::factory()->create(['name' => 'Sara']);
        $fromMachine = fn (Employee $e) => Attendance::query()->create(['employee_id' => $e->id, 'date' => today()->toDateString(), 'status' => 'present', 'check_in' => '09:05', 'source' => 'device']);
        $fromMachine($ali);
        $fromMachine($sara);

        $this->actingAs($owner)->get(route('attendance.day'))->assertOk()->assertDontSee('Mark unmarked as present')->assertDontSee('Mark all off');
        $this->actingAs($owner)->get(route('attendance.day', ['date' => today()->subDay()->toDateString()]))->assertSee('Mark unmarked as present');

        // Saving with Ali untouched and a note added for Sara: only Sara counts as entered by hand.
        $this->actingAs($owner)->put(route('attendance.save-day'), ['date' => today()->toDateString(), 'rows' => [
            $ali->id => ['status' => 'present', 'check_in' => '09:05'],
            $sara->id => ['status' => 'present', 'check_in' => '09:05', 'note' => 'Left for the bank'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame('device', Attendance::query()->where('employee_id', $ali->id)->sole()->source);
        $this->assertSame('manual', Attendance::query()->where('employee_id', $sara->id)->sole()->source);
    }

    public function test_absent_counts_people_without_a_punch_and_the_cards_filter_the_rows(): void
    {
        $owner = User::factory()->owner()->create();
        $came = Employee::factory()->create(['name' => 'Came']);
        Employee::factory()->create(['name' => 'Stayed home']);
        Attendance::query()->create(['employee_id' => $came->id, 'date' => today()->toDateString(), 'status' => 'present', 'check_in' => '09:30', 'source' => 'device']);

        $this->actingAs($owner)->get(route('attendance.day'))->assertOk()
            ->assertSee('0 marked absent · 1 no punch')->assertSee('data-filter="absent"', false)->assertSee('data-filter="late"', false)
            ->assertSee('data-status="none"', false)->assertSee('data-late="1"', false);
    }
}
