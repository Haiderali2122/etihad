<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Staff added from the attendance machine while it still had a placeholder name for them ("NN-53") take the
 * name the machine has now. Only those still carrying such a placeholder are changed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $rows = DB::table('employees')
            ->join('device_users', 'device_users.employee_id', '=', 'employees.id')
            ->where('employees.notes', 'like', 'Added from the attendance machine%')
            ->whereNotNull('device_users.name')
            ->get(['employees.id', 'employees.name as staff_name', 'device_users.name as machine_name']);

        foreach ($rows as $row) {
            $placeholder = (bool) preg_match('/^nn-?\d+$/i', trim($row->staff_name));
            $machine = trim((string) $row->machine_name);
            if ($placeholder && $machine !== '' && ! preg_match('/^nn-?\d+$/i', $machine)) {
                $name = mb_convert_case(mb_strtolower((string) preg_replace('/\s+/u', ' ', $machine)), MB_CASE_TITLE);
                DB::table('employees')->where('id', $row->id)->update(['name' => $name, 'updated_at' => now()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
