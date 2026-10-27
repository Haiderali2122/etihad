<?php

namespace Tests\Feature\E2E;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-end flows for Staff (employees, foremen, designations) and Attendance, driven over HTTP.
 */
class StaffAttendanceE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-27 10:00:00'));
        $this->owner = User::factory()->owner()->create();
        foreach (['Printer', 'Binder', 'Foreman', 'Helper', 'Cutter', 'Folder'] as $name) {
            Designation::factory()->create(['name' => $name]);
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::factory()->create(['permissions' => $permissions])->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'E-501',
            'name' => 'Aslam Khan',
            'name_ur' => 'اسلم خان',
            'designation' => 'Printer',
            'monthly_salary' => 31000,
            'joined_on' => '2026-01-01',
            'is_foreman' => 0,
            'is_active' => 1,
        ], $overrides);
    }

    private function csv(string $content, string $name = 'log.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    // ---------------------------------------------------------------- pages & permissions

    public function test_owner_can_open_every_staff_and_attendance_page(): void
    {
        $foreman = Employee::factory()->foreman()->create();
        $employee = Employee::factory()->create(['foreman_id' => $foreman->id]);
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-01']);
        $designation = Designation::query()->where('name', 'Printer')->sole();

        $urls = [
            route('employees.index'), route('employees.index', ['status' => 'inactive']), route('employees.index', ['status' => 'all', 'q' => 'E-']),
            route('employees.create'), route('employees.show', $employee), route('employees.show', $foreman), route('employees.edit', $employee),
            route('designations.index'), route('designations.create'), route('designations.edit', $designation),
            route('attendance.index'), route('attendance.index', ['month' => '2026-09']), route('attendance.index', ['month' => 'garbage']),
            route('attendance.day'), route('attendance.day', ['date' => '2026-10-01']), route('attendance.import'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->owner)->get($url)->assertOk();
        }
    }

    public function test_staff_view_only_role_sees_lists_but_cannot_change_anything(): void
    {
        $viewer = $this->userWith(['staff.view']);
        $employee = Employee::factory()->create(['designation' => 'Printer']);
        $designation = Designation::query()->where('name', 'Printer')->sole();

        $this->actingAs($viewer)->get(route('employees.index'))->assertOk()->assertDontSee(route('employees.create'));
        $this->actingAs($viewer)->get(route('employees.show', $employee))->assertOk()->assertDontSee(route('employees.edit', $employee));
        $this->actingAs($viewer)->get(route('attendance.index'))->assertOk()->assertDontSee(route('attendance.import'));

        foreach ([route('employees.create'), route('employees.edit', $employee), route('designations.index'), route('designations.create'),
            route('designations.edit', $designation), route('attendance.day'), route('attendance.import')] as $url) {
            $this->actingAs($viewer)->get($url)->assertForbidden();
        }

        $this->actingAs($viewer)->post(route('employees.store'), $this->employeePayload())->assertForbidden();
        $this->actingAs($viewer)->put(route('employees.update', $employee), $this->employeePayload())->assertForbidden();
        $this->actingAs($viewer)->delete(route('employees.destroy', $employee))->assertForbidden();
        $this->actingAs($viewer)->post(route('designations.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('designations.destroy', $designation))->assertForbidden();
        $this->actingAs($viewer)->put(route('attendance.save-day'), ['date' => '2026-10-27', 'rows' => [$employee->id => ['status' => 'present']]])->assertForbidden();
        $this->actingAs($viewer)->post(route('attendance.import.store'), ['file' => $this->csv("code,datetime\n")])->assertForbidden();

        $this->assertModelExists($employee);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_attendance_manage_without_staff_view_can_mark_but_not_see_month_grid_or_staff(): void
    {
        $marker = $this->userWith(['attendance.manage']);
        $employee = Employee::factory()->create();

        $this->actingAs($marker)->get(route('attendance.day'))->assertOk()->assertSee($employee->name);
        $this->actingAs($marker)->get(route('attendance.import'))->assertOk();
        $this->actingAs($marker)->get(route('attendance.index'))->assertForbidden();
        $this->actingAs($marker)->get(route('employees.index'))->assertForbidden();
        $this->actingAs($marker)->get(route('employees.show', $employee))->assertForbidden();

        $this->actingAs($marker)->put(route('attendance.save-day'), ['date' => '2026-10-27', 'rows' => [$employee->id => ['status' => 'present']]])
            ->assertRedirect(route('attendance.day', ['date' => '2026-10-27']));
        $this->assertSame(1, $employee->attendances()->count());
    }

    public function test_role_with_no_permissions_gets_403_everywhere_and_guest_is_sent_to_login(): void
    {
        $nobody = $this->userWith([]);
        $employee = Employee::factory()->create();

        foreach ([route('employees.index'), route('employees.show', $employee), route('attendance.index'), route('attendance.day'), route('designations.index')] as $url) {
            $this->actingAs($nobody)->get($url)->assertForbidden();
        }

        auth()->logout();
        $this->get(route('employees.index'))->assertRedirect(route('login'));
    }

    public function test_pages_render_in_urdu(): void
    {
        $employee = Employee::factory()->create(['name' => 'Aslam', 'name_ur' => 'اسلم', 'designation' => 'Printer']);
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-05']);

        $session = ['locale' => 'ur'];
        $this->actingAs($this->owner)->withSession($session)->get(route('attendance.index'))->assertOk()->assertSee('حاضری')->assertSee('اسلم');
        $this->actingAs($this->owner)->withSession($session)->get(route('attendance.day', ['date' => '2026-10-05']))->assertOk()->assertSee('اسلم');
        $this->actingAs($this->owner)->withSession($session)->get(route('attendance.import'))->assertOk();
        $this->actingAs($this->owner)->withSession($session)->get(route('employees.index'))->assertOk();
        $this->actingAs($this->owner)->withSession($session)->get(route('employees.show', $employee))->assertOk()->assertSee('اسلم');
        $this->actingAs($this->owner)->withSession($session)->get(route('employees.create'))->assertOk();
        $this->actingAs($this->owner)->withSession($session)->get(route('designations.index'))->assertOk()->assertSee('عہدے');

        $this->actingAs($this->owner)->withSession($session)->post(route('employees.store'), $this->employeePayload(['code' => 'U-1']))
            ->assertSessionHas('status', 'ملازم Aslam Khan محفوظ ہو گیا۔');
    }

    // ---------------------------------------------------------------- employees

    public function test_full_lifecycle_add_mark_profile_and_deactivate(): void
    {
        $response = $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload());
        $employee = Employee::query()->where('code', 'E-501')->sole();
        $response->assertRedirect(route('employees.show', $employee))->assertSessionHasNoErrors();
        $this->assertTrue($employee->is_active);
        $this->assertFalse($employee->is_foreman);

        $this->actingAs($this->owner)->get(route('employees.index'))->assertOk()->assertSee('Aslam Khan');
        $this->actingAs($this->owner)->get(route('employees.index', ['q' => 'E-501']))->assertOk()->assertSee('Aslam Khan');
        $this->actingAs($this->owner)->get(route('employees.index', ['q' => 'nomatch']))->assertOk()->assertDontSee('Aslam Khan');

        $this->actingAs($this->owner)->put(route('attendance.save-day'), [
            'date' => '2026-10-26',
            'rows' => [$employee->id => ['status' => 'present', 'check_in' => '09:00', 'check_out' => '20:00', 'overtime_hours' => 2.5, 'note' => 'late shift']],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->put(route('attendance.save-day'), [
            'date' => '2026-10-27', 'rows' => [$employee->id => ['status' => 'absent']],
        ])->assertSessionHasNoErrors();

        $record = $employee->attendances()->whereDate('date', '2026-10-26')->sole();
        $this->assertSame(150, $record->overtime_minutes);
        $this->assertSame('manual', $record->source);
        $this->assertSame('late shift', $record->note);

        $this->actingAs($this->owner)->get(route('employees.show', $employee))->assertOk()
            ->assertSee('Aslam Khan')
            ->assertSee('Overtime this month: 2.5 hours')
            ->assertSee('1 absent, 0 leave')
            ->assertDontSee('Delete employee');

        $this->actingAs($this->owner)->get(route('attendance.index'))->assertOk()->assertSee('26: Present · OT 2.5 h', false);

        // History exists, so delete is refused; the person is marked as left instead.
        $this->actingAs($this->owner)->from(route('employees.show', $employee))->delete(route('employees.destroy', $employee))
            ->assertSessionHasErrors('employee');
        $this->assertModelExists($employee);

        $this->actingAs($this->owner)->put(route('employees.update', $employee), $this->employeePayload(['is_active' => 0, 'left_on' => '2026-10-27']))
            ->assertSessionHasNoErrors();
        $employee->refresh();
        $this->assertFalse($employee->is_active);
        $this->assertSame('2026-10-27', $employee->left_on->toDateString());

        $profile = route('employees.show', $employee);
        $this->actingAs($this->owner)->get(route('employees.index')); // consumes the "saved" flash, which names the employee
        $this->actingAs($this->owner)->get(route('employees.index'))->assertDontSee($profile, false);
        $this->actingAs($this->owner)->get(route('employees.index', ['status' => 'inactive']))->assertSee($profile, false);
        $this->actingAs($this->owner)->get(route('attendance.day'))->assertDontSee('Aslam Khan');
    }

    public function test_employee_validation_errors(): void
    {
        Employee::factory()->create(['code' => 'E-501']);

        $this->actingAs($this->owner)->post(route('employees.store'), [])
            ->assertSessionHasErrors(['code', 'name', 'designation', 'monthly_salary']);
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload())->assertSessionHasErrors('code');
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => 'E-502', 'monthly_salary' => -5]))->assertSessionHasErrors('monthly_salary');
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => 'E-502', 'monthly_salary' => 'abc']))->assertSessionHasErrors('monthly_salary');
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => str_repeat('9', 21)]))->assertSessionHasErrors('code');
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => 'E-502', 'joined_on' => 'not a date']))->assertSessionHasErrors('joined_on');
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => 'E-502', 'designation' => 'Astronaut']))->assertSessionHasErrors('designation');

        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => 'E-502', 'joined_on' => '2026-05-10', 'left_on' => '2026-05-09']))
            ->assertSessionHasErrors('left_on');
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => 'E-502', 'joined_on' => '2026-05-10', 'left_on' => '2026-05-10']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Employee::query()->count());
    }

    public function test_updating_an_employee_keeps_their_own_code(): void
    {
        $employee = Employee::factory()->create(['code' => 'E-700', 'designation' => 'Printer']);
        Employee::factory()->create(['code' => 'E-701']);

        $this->actingAs($this->owner)->put(route('employees.update', $employee), $this->employeePayload(['code' => 'E-700', 'name' => 'Renamed']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $employee->refresh()->name);

        $this->actingAs($this->owner)->put(route('employees.update', $employee), $this->employeePayload(['code' => 'E-701']))
            ->assertSessionHasErrors('code');
    }

    /**
     * A leaving date can be recorded for staff whose joining date is unknown.
     */
    public function test_left_on_can_be_set_when_joined_on_is_unknown(): void
    {
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['joined_on' => null, 'left_on' => '2026-10-01', 'is_active' => 0]))
            ->assertSessionHasNoErrors();
    }

    public function test_foreman_and_team(): void
    {
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => 'F-1', 'name' => 'Boss Man', 'designation' => 'Foreman', 'is_foreman' => 1]));
        $foreman = Employee::query()->where('code', 'F-1')->sole();
        $this->assertTrue($foreman->is_foreman);

        $this->actingAs($this->owner)->get(route('employees.create'))->assertSee('Boss Man');
        // The foreman is not offered as his own foreman.
        $this->actingAs($this->owner)->get(route('employees.edit', $foreman))->assertOk()->assertDontSee('<option value="'.$foreman->id.'"', false);

        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => 'W-1', 'name' => 'Worker One', 'foreman_id' => $foreman->id]))
            ->assertSessionHasNoErrors();
        $worker = Employee::query()->where('code', 'W-1')->sole();
        $this->assertTrue($worker->foreman->is($foreman));
        $this->actingAs($this->owner)->get(route('employees.show', $worker))->assertSee('under Boss Man');

        // Cannot be their own foreman.
        $this->actingAs($this->owner)->put(route('employees.update', $foreman), $this->employeePayload(['code' => 'F-1', 'name' => 'Boss Man', 'designation' => 'Foreman', 'is_foreman' => 1, 'foreman_id' => $foreman->id]))
            ->assertSessionHasErrors('foreman_id');
        $this->assertNull($foreman->refresh()->foreman_id);

        // Cannot work under someone who is not a foreman, or who does not exist.
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => 'W-2', 'foreman_id' => $worker->id]))->assertSessionHasErrors('foreman_id');
        $this->actingAs($this->owner)->post(route('employees.store'), $this->employeePayload(['code' => 'W-2', 'foreman_id' => 99999]))->assertSessionHasErrors('foreman_id');

        // Deleting a foreman with no history frees his team.
        $this->actingAs($this->owner)->delete(route('employees.destroy', $foreman))->assertRedirect(route('employees.index'));
        $this->assertModelMissing($foreman);
        $this->assertNull($worker->refresh()->foreman_id);
        $this->actingAs($this->owner)->get(route('employees.show', $worker))->assertOk();
    }

    public function test_employee_with_no_history_can_be_deleted_from_the_profile(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner)->get(route('employees.show', $employee))->assertSee('Delete employee');
        $this->actingAs($this->owner)->delete(route('employees.destroy', $employee))->assertRedirect(route('employees.index'));
        $this->assertModelMissing($employee);
        $this->actingAs($this->owner)->get(route('employees.show', $employee))->assertNotFound();
    }

    /**
     * The profile hides "Delete employee" when attendance exists in any month, matching what destroy() allows.
     */
    public function test_delete_button_is_hidden_when_older_attendance_exists(): void
    {
        $employee = Employee::factory()->create();
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-08-15']);

        $this->actingAs($this->owner)->get(route('employees.show', $employee))->assertDontSee('Delete employee');
    }

    // ---------------------------------------------------------------- designations

    public function test_designation_rename_cascades_to_employees_and_their_pages(): void
    {
        $designation = Designation::query()->where('name', 'Binder')->sole();
        $binder = Employee::factory()->create(['designation' => 'Binder', 'name' => 'Bilal']);
        $left = Employee::factory()->create(['designation' => 'Binder', 'is_active' => false]);

        $this->actingAs($this->owner)->get(route('designations.index'))->assertOk()->assertSee('Binder');

        $this->actingAs($this->owner)->put(route('designations.update', $designation), ['name' => 'Book binder', 'name_ur' => 'جلد ساز', 'is_active' => 1])
            ->assertRedirect(route('designations.index'));

        $this->assertSame('Book binder', $binder->refresh()->designation);
        $this->assertSame('Book binder', $left->refresh()->designation);
        $this->actingAs($this->owner)->get(route('employees.show', $binder))->assertSee('Book binder');
        $this->actingAs($this->owner)->get(route('employees.create'))->assertSee('Book binder · جلد ساز');

        // The employee can still be saved after the rename.
        $this->actingAs($this->owner)->put(route('employees.update', $binder), $this->employeePayload(['code' => $binder->code, 'designation' => 'Book binder']))
            ->assertSessionHasNoErrors();

        // Rename onto an existing title is refused and nothing moves.
        $this->actingAs($this->owner)->put(route('designations.update', $designation), ['name' => 'Printer', 'is_active' => 1])->assertSessionHasErrors('name');
        $this->assertSame('Book binder', $binder->refresh()->designation);
    }

    public function test_inactive_designation_stays_on_existing_staff_form_but_is_not_offered_to_new_staff(): void
    {
        $designation = Designation::query()->where('name', 'Cutter')->sole();
        $cutter = Employee::factory()->create(['designation' => 'Cutter']);

        $this->actingAs($this->owner)->put(route('designations.update', $designation), ['name' => 'Cutter', 'is_active' => 0])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get(route('employees.create'))->assertDontSee('<option value="Cutter"', false);
        $this->actingAs($this->owner)->get(route('employees.edit', $cutter))->assertSee('<option value="Cutter"', false);
        $this->actingAs($this->owner)->put(route('employees.update', $cutter), $this->employeePayload(['code' => $cutter->code, 'designation' => 'Cutter']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->from(route('designations.index'))->delete(route('designations.destroy', $designation))->assertSessionHasErrors('designation');
        $this->assertModelExists($designation);
    }

    public function test_designation_validation(): void
    {
        $this->actingAs($this->owner)->post(route('designations.store'), [])->assertSessionHasErrors('name');
        $this->actingAs($this->owner)->post(route('designations.store'), ['name' => str_repeat('a', 81)])->assertSessionHasErrors('name');
        $this->actingAs($this->owner)->post(route('designations.store'), ['name' => 'Printer'])->assertSessionHasErrors('name');
        $this->actingAs($this->owner)->post(route('designations.store'), ['name' => 'Packer'])->assertSessionHasNoErrors();
        $this->assertFalse(Designation::query()->where('name', 'Packer')->sole()->is_active);
    }

    // ---------------------------------------------------------------- attendance: day sheet

    public function test_day_sheet_validation(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-28', 'rows' => [$employee->id => ['status' => 'present']]])
            ->assertSessionHasErrors('date');
        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-27'])->assertSessionHasErrors('rows');
        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-27', 'rows' => [$employee->id => ['status' => 'sick']]])
            ->assertSessionHasErrors("rows.{$employee->id}.status");
        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-27', 'rows' => [$employee->id => ['status' => 'present', 'check_in' => '25:00']]])
            ->assertSessionHasErrors("rows.{$employee->id}.check_in");
        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-27', 'rows' => [$employee->id => ['status' => 'present', 'overtime_hours' => 17]]])
            ->assertSessionHasErrors("rows.{$employee->id}.overtime_hours");
        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-27', 'rows' => [$employee->id => ['status' => 'present', 'overtime_hours' => -1]]])
            ->assertSessionHasErrors("rows.{$employee->id}.overtime_hours");

        $this->assertSame(0, Attendance::query()->count());

        // Unknown employee ids in the sheet are ignored.
        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-27', 'rows' => [99999 => ['status' => 'present']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, Attendance::query()->count());
    }

    /**
     * Correcting a day that is already marked updates the existing record.
     */
    public function test_correcting_an_already_marked_day_updates_it(): void
    {
        $employee = Employee::factory()->create();
        $put = fn (array $row) => $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-26', 'rows' => [$employee->id => $row]]);

        $put(['status' => 'present', 'overtime_hours' => 2])->assertSessionHasNoErrors();
        $put(['status' => 'leave', 'overtime_hours' => 2])->assertRedirect(route('attendance.day', ['date' => '2026-10-26']));

        $record = $employee->attendances()->sole();
        $this->assertSame(AttendanceStatus::Leave, $record->status);
        $this->assertSame(0, $record->overtime_minutes);
    }

    public function test_clearing_a_status_removes_that_day(): void
    {
        $employee = Employee::factory()->create();
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-26']);

        $this->actingAs($this->owner)->get(route('attendance.day', ['date' => '2026-10-26']))->assertOk()->assertSee('1 already marked');

        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-26', 'rows' => [$employee->id => ['status' => '']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $employee->attendances()->count());
    }

    public function test_overtime_and_times_are_dropped_for_people_not_present(): void
    {
        [$a, $b] = Employee::factory()->count(2)->create();

        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-20', 'rows' => [
            $a->id => ['status' => 'off', 'check_in' => '09:00', 'check_out' => '17:00', 'overtime_hours' => 3],
            $b->id => ['status' => 'present', 'check_in' => '09:00', 'check_out' => '17:00', 'overtime_hours' => 0.25],
        ]])->assertSessionHasNoErrors()->assertSessionHas('status', 'Attendance saved for 2 people on 20 Oct 2026.');

        $off = $a->attendances()->sole();
        $this->assertNull($off->check_in);
        $this->assertSame(0, $off->overtime_minutes);
        $this->assertSame(15, $b->attendances()->sole()->overtime_minutes);

        $this->actingAs($this->owner)->get(route('attendance.day', ['date' => '2026-10-20']))->assertOk()->assertSee('value="09:00"', false);
    }

    /**
     * A bad ?date= on the day sheet falls back to today.
     */
    public function test_day_sheet_with_a_bad_date_does_not_crash(): void
    {
        $this->actingAs($this->owner)->get(route('attendance.day', ['date' => 'not-a-date']))->assertOk();
    }

    // ---------------------------------------------------------------- attendance: month grid & boundaries

    public function test_last_day_of_month_counts_in_grid_and_profile(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 18:00:00'));
        $employee = Employee::factory()->create(['name' => 'Edge Case']);

        $this->actingAs($this->owner)->put(route('attendance.save-day'), ['date' => '2026-10-31', 'rows' => [$employee->id => ['status' => 'present', 'overtime_hours' => 1]]])
            ->assertSessionHasNoErrors();
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-01', 'status' => AttendanceStatus::Absent]);
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-09-30', 'status' => AttendanceStatus::Absent]);
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-11-01', 'status' => AttendanceStatus::Absent]);

        $this->assertSame('2026-10-31 00:00:00', DB::table('attendances')->where('status', 'present')->value('date'));

        $this->actingAs($this->owner)->get(route('employees.show', $employee))->assertOk()
            ->assertSee('Overtime this month: 1 hours')
            ->assertSee('1 absent, 0 leave');

        $this->actingAs($this->owner)->get(route('attendance.index', ['month' => '2026-10']))->assertOk()
            ->assertSee('31: Present · OT 1 h', false)
            ->assertSee('1: Absent', false);

        // Looked at mid-month (on the 31st, ?month=2026-09 overflows into October; see the month-grid bug test).
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));
        $this->actingAs($this->owner)->get(route('attendance.index', ['month' => '2026-09']))->assertOk()->assertSee('30: Absent', false);
    }

    /**
     * On the 31st, asking for a 30-day month still shows that month rather than overflowing into the next.
     */
    public function test_month_grid_shows_the_requested_month_at_the_end_of_a_long_month(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 10:00:00'));
        $employee = Employee::factory()->create();
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-09-15', 'status' => AttendanceStatus::Leave]);

        $this->actingAs($this->owner)->get(route('attendance.index', ['month' => '2026-09']))->assertOk()
            ->assertSee('September 2026')
            ->assertSee('15: Paid leave', false);
    }

    public function test_month_grid_lists_only_active_staff_and_hides_next_link_for_the_current_month(): void
    {
        Employee::factory()->create(['name' => 'Still Here']);
        Employee::factory()->create(['name' => 'Long Gone', 'is_active' => false]);

        $this->actingAs($this->owner)->get(route('attendance.index'))->assertOk()
            ->assertSee('Still Here')->assertDontSee('Long Gone')
            ->assertSee(route('attendance.index', ['month' => '2026-09']), false)
            ->assertDontSee(route('attendance.index', ['month' => '2026-11']), false);
    }

    // ---------------------------------------------------------------- attendance: import

    public function test_import_punch_log_with_duplicates_unknown_codes_and_bad_rows(): void
    {
        $aslam = Employee::factory()->create(['code' => 'E-001']);
        Employee::factory()->create(['code' => 'e-002']);

        $file = $this->csv("\xEF\xBB\xBFAC-No.,Name,Time\n"
            ."E-001,Aslam,2026-10-12 08:58\n"
            ."E-001,Aslam,2026-10-12 08:58\n"
            ."E-001,Aslam,2026-10-12 21:04\n"
            ."E-002,Tariq,2026-10-12 09:00\n"
            ."E-099,Nobody,2026-10-12 09:00\n"
            ."E-099,Nobody,2026-10-13 09:00\n"
            ."E-001,Aslam,yesterday-ish garbage\n"
            .",,\n"
            ."\n");

        $this->actingAs($this->owner)->post(route('attendance.import.store'), ['file' => $file, 'overwrite' => 0])
            ->assertRedirect(route('attendance.import'))
            ->assertSessionHas('status', '2 days imported.');

        $result = session('import');
        $this->assertSame(['E-099'], $result['unknown']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Row 8', $result['errors'][0]);

        $day = $aslam->attendances()->sole();
        $this->assertSame('device', $day->source);
        $this->assertSame('08:58', substr($day->check_in, 0, 5));
        $this->assertSame('21:04', substr($day->check_out, 0, 5));
        $this->assertSame(246, $day->overtime_minutes);

        $this->actingAs($this->owner)->get(route('attendance.import'))->assertOk();
    }

    public function test_import_one_row_per_day_layout_and_result_page(): void
    {
        $employee = Employee::factory()->create(['code' => 'E-001']);

        $this->actingAs($this->owner)->followingRedirects()->post(route('attendance.import.store'), [
            'file' => $this->csv("code,date,in,out\nE-001,2026-10-12,09:00,17:00\nE-001,2026-10-13,09:00,\nE-404,2026-10-13,09:00,17:00\n"),
        ])->assertOk()->assertSee('E-404')->assertSee('2 days imported.');

        $this->assertSame(2, $employee->attendances()->count());
        $this->assertNull($employee->attendances()->whereDate('date', '2026-10-13')->sole()->check_out);
    }

    public function test_import_skips_manual_days_unless_overwrite(): void
    {
        $employee = Employee::factory()->create(['code' => 'E-001']);
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-12', 'status' => AttendanceStatus::Leave, 'source' => 'manual']);

        $this->actingAs($this->owner)->post(route('attendance.import.store'), ['file' => $this->csv("code,datetime\nE-001,2026-10-12 09:00\n")]);
        $this->assertSame(1, session('import')['skipped']);
        $this->assertSame(AttendanceStatus::Leave, $employee->attendances()->sole()->status);
    }

    /**
     * Importing with overwrite replaces a hand-marked day.
     */
    public function test_import_overwrite_replaces_a_manual_day(): void
    {
        $employee = Employee::factory()->create(['code' => 'E-001']);
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-12', 'status' => AttendanceStatus::Leave, 'source' => 'manual']);

        $this->actingAs($this->owner)->post(route('attendance.import.store'), ['file' => $this->csv("code,datetime\nE-001,2026-10-12 09:00\n"), 'overwrite' => 1])
            ->assertRedirect(route('attendance.import'));

        $record = $employee->attendances()->sole();
        $this->assertSame(AttendanceStatus::Present, $record->status);
        $this->assertSame('device', $record->source);
    }

    /**
     * Importing the same device file twice updates the days instead of duplicating them.
     */
    public function test_importing_the_same_file_twice_is_idempotent(): void
    {
        $employee = Employee::factory()->create(['code' => 'E-001']);
        $content = "code,datetime\nE-001,2026-10-12 09:00\nE-001,2026-10-12 19:00\n";

        $this->actingAs($this->owner)->post(route('attendance.import.store'), ['file' => $this->csv($content)])->assertSessionHas('status', '1 days imported.');
        $this->actingAs($this->owner)->post(route('attendance.import.store'), ['file' => $this->csv($content)])->assertRedirect(route('attendance.import'));

        $this->assertSame(1, $employee->attendances()->count());
    }

    public function test_import_rejects_missing_or_wrong_files(): void
    {
        $this->actingAs($this->owner)->post(route('attendance.import.store'), [])->assertSessionHasErrors('file');
        $this->actingAs($this->owner)->post(route('attendance.import.store'), ['file' => UploadedFile::fake()->image('photo.png')])->assertSessionHasErrors('file');
        $this->actingAs($this->owner)->post(route('attendance.import.store'), ['file' => UploadedFile::fake()->create('big.csv', 6000, 'text/csv')])->assertSessionHasErrors('file');
    }

    public function test_import_of_empty_and_header_only_files_reports_no_rows(): void
    {
        $this->actingAs($this->owner)->post(route('attendance.import.store'), ['file' => $this->csv('')])->assertRedirect(route('attendance.import'));
        $this->assertSame(['The file has no rows.'], session('import')['errors']);

        $this->actingAs($this->owner)->post(route('attendance.import.store'), ['file' => $this->csv("code,datetime\n")])->assertRedirect(route('attendance.import'));
        $this->assertSame(['The file has no rows.'], session('import')['errors']);
    }

    /**
     * A file whose header has no recognisable employee-number column reports an error instead of importing nothing.
     */
    public function test_import_with_unrecognised_columns_tells_the_user(): void
    {
        Employee::factory()->create(['code' => 'E-001']);

        $this->actingAs($this->owner)->post(route('attendance.import.store'), ['file' => $this->csv("Staff,When\nE-001,2026-10-12 09:00\n")]);

        $this->assertNotEmpty(session('import')['errors']);
    }

    /**
     * A punch log exported with separate Date and Time columns combines them into one punch per row.
     */
    public function test_import_punch_log_with_separate_date_and_time_columns(): void
    {
        $employee = Employee::factory()->create(['code' => 'E-001']);

        $this->actingAs($this->owner)->post(route('attendance.import.store'), [
            'file' => $this->csv("AC-No.,Date,Time\nE-001,2026-10-12,08:00\nE-001,2026-10-12,18:00\n"),
        ]);

        $day = $employee->attendances()->sole();
        $this->assertSame('08:00', substr((string) $day->check_in, 0, 5));
        $this->assertSame('18:00', substr((string) $day->check_out, 0, 5));
    }
}
