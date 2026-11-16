<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The attendance machine software's daily report (.xls) imported as present / absent days with overtime.
 * The test builds a small file in the same old Excel format, with made-up people.
 */
class AttendanceMachineImportTest extends TestCase
{
    use RefreshDatabase;

    private const Header = ['Emp No.', 'AC-No.', 'No.', 'Name', 'Auto-Assign', 'Date', 'Timetable', 'On duty', 'Off duty', 'Clock In', 'Clock Out', 'Normal', 'Real time', 'Late', 'Early', 'Absent', 'OT Time', 'Work Time'];

    /**
     * An .xls in the machine software's format: a bare BIFF2 stream of text cells.
     *
     * @param  list<list<string>>  $rows
     */
    private function machineFile(array $rows): UploadedFile
    {
        $bytes = pack('vv', 0x0009, 4).pack('vv', 0x0002, 0x0010);
        foreach (array_merge([self::Header], $rows) as $r => $cells) {
            foreach ($cells as $c => $text) {
                $bytes .= pack('vv', 0x0004, 8 + strlen($text)).pack('vv', $r, $c)."\0\0\0".chr(strlen($text)).$text;
            }
        }
        $bytes .= pack('vv', 0x000A, 0);
        $path = tempnam(sys_get_temp_dir(), 'att').'.xls';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'oct data.xls', 'application/vnd.ms-excel', null, true);
    }

    /**
     * @return list<string>
     */
    private function row(string $ac, string $name, string $date, string $in, string $out, bool $absent = false, string $ot = ''): array
    {
        return ['500', $ac, '', $name, '', $date, 'SHIFT 1', '09:00', '17:00', $in, $out, '1', $absent ? '' : '1', '', '', $absent ? 'True' : '', $ot, ''];
    }

    public function test_the_machine_report_imports_present_absent_and_overtime(): void
    {
        $owner = User::factory()->owner()->create();
        $tariq = Employee::factory()->create(['name' => 'Tariq Mehmood', 'code' => 'E-010']);
        $nadeem = Employee::factory()->create(['name' => 'Nadeem Akhtar', 'code' => 'E-011', 'device_code' => '7']);

        $file = $this->machineFile([
            $this->row('3', 'TARIQ  MEHMOOD', '01-Oct-26', '09:10', '18:15', ot: '01:15'),
            $this->row('3', 'TARIQ  MEHMOOD', '02-Oct-26', '', '', absent: true),
            $this->row('3', 'TARIQ  MEHMOOD', '03-Oct-26', '08:06', ''), // clocked in only: still present
            $this->row('7', 'N AKHTAR', '01-Oct-26', '08:58', '21:17', ot: '04:17'),
            $this->row('9', 'NOBODY HERE', '01-Oct-26', '09:00', '17:00'),
        ]);

        $this->actingAs($owner)->get(route('attendance.import'))->assertOk()->assertSee('.xls');
        $this->actingAs($owner)->post(route('attendance.import.store'), ['file' => $file])
            ->assertRedirect(route('attendance.import'))->assertSessionHas('status', '4 days imported: 3 present, 1 absent.');

        $day = fn (Employee $e, string $d) => Attendance::query()->where('employee_id', $e->id)->whereDate('date', $d)->sole();
        $this->assertSame(AttendanceStatus::Present, $day($tariq, '2026-10-01')->status);
        $this->assertSame(75, $day($tariq, '2026-10-01')->overtime_minutes);
        $this->assertSame(AttendanceStatus::Absent, $day($tariq, '2026-10-02')->status);
        $this->assertSame(AttendanceStatus::Present, $day($tariq, '2026-10-03')->status);
        // Nadeem is matched by his machine number though the file spells his name differently.
        $this->assertSame(257, $day($nadeem, '2026-10-01')->overtime_minutes);
        // Tariq was matched by name; his machine number is saved for next time.
        $this->assertSame('3', $tariq->fresh()->device_code);
        $this->assertSame(['9 NOBODY HERE'], session('import')['unknown']);

        $this->actingAs($owner)->get(route('attendance.index', ['month' => '2026-10']))->assertOk()->assertSee('Tariq Mehmood');
    }

    public function test_hand_changes_are_kept_and_a_single_day_file_imports_that_day(): void
    {
        $owner = User::factory()->owner()->create();
        $tariq = Employee::factory()->create(['name' => 'Tariq Mehmood', 'device_code' => '3']);

        $this->actingAs($owner)->post(route('attendance.import.store'), ['file' => $this->machineFile([
            $this->row('3', 'TARIQ MEHMOOD', '01-Oct-26', '09:00', '19:00', ot: '02:00'),
        ])])->assertSessionHasNoErrors();

        // The manager changes the day by hand on the attendance sheet: overtime corrected to 1 hour.
        $this->actingAs($owner)->put(route('attendance.save-day'), ['date' => '2026-10-01', 'rows' => [$tariq->id => ['status' => 'present', 'overtime_hours' => 1]]])->assertSessionHasNoErrors();
        $this->assertSame(60, Attendance::query()->sole()->overtime_minutes);

        // A later import of the same day leaves the hand change alone, unless replacing is ticked.
        $again = fn (bool $overwrite) => $this->actingAs($owner)->post(route('attendance.import.store'), ['overwrite' => $overwrite ? 1 : 0, 'file' => $this->machineFile([
            $this->row('3', 'TARIQ MEHMOOD', '01-Oct-26', '09:00', '19:00', ot: '02:00'),
        ])]);
        $again(false);
        $this->assertSame(60, Attendance::query()->sole()->overtime_minutes);
        $again(true);
        $this->assertSame(120, Attendance::query()->sole()->overtime_minutes);
    }
}
