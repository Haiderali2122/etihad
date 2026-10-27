<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The office manager receives trucks, so he also manages the godowns they are unloaded into.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->change(fn (array $permissions) => array_values(array_unique([...$permissions, 'godowns.manage'])));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->change(fn (array $permissions) => array_values(array_diff($permissions, ['godowns.manage'])));
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
