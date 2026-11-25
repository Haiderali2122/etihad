<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two shifts: day (9 AM - 5 PM) and night (9 PM - 5 AM). Each person is on one; each day's attendance keeps the
 * shift it was worked on. Sunday is the paid holiday, so time worked on a Sunday is all overtime: Sundays already
 * filled in from the machine are worked out again.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('shift', 10)->default('day')->after('designation');
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('shift', 10)->default('day')->after('status');
        });

        $minutes = function (?string $time): ?int {
            return $time && preg_match('/^(\d{1,2}):(\d{2})/', $time, $m) ? (int) $m[1] * 60 + (int) $m[2] : null;
        };
        DB::table('attendances')->where('source', 'device')->whereNotNull('check_in')->whereNotNull('check_out')->orderBy('id')
            ->each(function (object $row) use ($minutes): void {
                if (date('w', strtotime((string) $row->date)) !== '0') {
                    return;
                }
                $in = $minutes($row->check_in);
                $out = $minutes($row->check_out);
                while ($out <= $in) {
                    $out += 1440;
                }
                DB::table('attendances')->where('id', $row->id)->update(['overtime_minutes' => $out - $in]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', fn (Blueprint $table) => $table->dropColumn('shift'));
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('shift'));
    }
};
