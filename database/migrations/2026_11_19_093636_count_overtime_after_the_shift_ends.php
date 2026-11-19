<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Overtime is now the time worked after the shift ends (5 PM unless changed in Settings), not hours beyond a
 * normal day. Days filled in from the attendance machine are worked out again; days entered by hand are kept.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $end = (string) (DB::table('settings')->where('key', 'attendance_shift_end')->value('value') ?? '17:00');
        [$h, $m] = array_map('intval', explode(':', $end) + [0, 0]);
        $endMinutes = $h * 60 + $m;

        DB::table('attendances')->where('source', 'device')->whereNotNull('check_out')->orderBy('id')
            ->each(function (object $row) use ($endMinutes): void {
                [$oh, $om] = array_map('intval', explode(':', (string) $row->check_out) + [0, 0]);
                DB::table('attendances')->where('id', $row->id)->update(['overtime_minutes' => max(0, $oh * 60 + $om - $endMinutes)]);
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
