<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The owner's graces: late after 20 minutes past the shift start, early when leaving more than 15 minutes before
 * its end, overtime only past 15 minutes after its end (then the whole time counts). Days from the attendance
 * machine get their overtime worked out again; days entered by hand keep theirs.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The new defaults (20, 15, 15) apply unless set; an older saved late grace is moved to 20.
        foreach (['attendance_late_grace' => 20, 'attendance_early_grace' => 15, 'attendance_overtime_grace' => 15] as $key => $value) {
            DB::table('settings')->where('key', $key)->update(['value' => $value, 'updated_at' => now()]);
        }
        DB::table('settings')->where('key', 'attendance_late_full_after')->delete();

        $end = (string) (DB::table('settings')->where('key', 'attendance_shift_end')->value('value') ?? '17:00');
        [$h, $m] = array_map('intval', explode(':', $end) + [0, 0]);
        $endMinutes = $h * 60 + $m;

        DB::table('attendances')->where('source', 'device')->whereNotNull('check_out')->orderBy('id')
            ->each(function (object $row) use ($endMinutes): void {
                [$oh, $om] = array_map('intval', explode(':', (string) $row->check_out) + [0, 0]);
                $after = max(0, $oh * 60 + $om - $endMinutes);
                DB::table('attendances')->where('id', $row->id)->update(['overtime_minutes' => $after > 15 ? $after : 0]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
