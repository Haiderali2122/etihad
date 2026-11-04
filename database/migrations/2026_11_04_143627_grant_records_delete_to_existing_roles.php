<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deleting now needs its own "Delete records" permission. Roles that could already delete keep that ability,
 * so this grants it to every existing non-owner role. The owner (full access) always has it; owners can
 * untick it on any role to hide the delete buttons for that role.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (DB::table('roles')->where('is_super', false)->get() as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];
            if (! in_array('records.delete', $permissions, true)) {
                $permissions[] = 'records.delete';
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('roles')->get() as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];
            if (($key = array_search('records.delete', $permissions, true)) !== false) {
                unset($permissions[$key]);
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }
    }
};
