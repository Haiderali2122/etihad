<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The office manager keeps the staff records, attendance and advances and prepares payroll; the owner finalizes it.
 */
return new class extends Migration
{
    private const Permissions = ['staff.view', 'staff.manage', 'attendance.manage', 'advances.create', 'payroll.prepare'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->change(fn (array $permissions) => array_values(array_unique([...$permissions, ...self::Permissions])));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->change(fn (array $permissions) => array_values(array_diff($permissions, self::Permissions)));
    }

    /**
     * @param  callable(list<string>): list<string>  $update
     */
    private function change(callable $update): void
    {
        $role = DB::table('roles')->where('name', 'Manager')->where('is_super', false)->first();

        if ($role) {
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($update(json_decode($role->permissions ?? '[]', true) ?: [])),
                'updated_at' => now(),
            ]);
        }
    }
};
