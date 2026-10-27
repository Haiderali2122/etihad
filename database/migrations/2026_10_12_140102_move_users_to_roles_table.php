<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the fixed owner/manager column with a link to the roles table, keeping each user's access.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $ownerId = DB::table('roles')->insertGetId([
            'name' => 'Owner', 'description' => 'Full access. Approves entries and manages users.',
            'permissions' => json_encode([]), 'is_super' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $managerId = DB::table('roles')->insertGetId([
            'name' => 'Manager', 'description' => 'Daily entries across modules; cannot approve.',
            'permissions' => json_encode(Role::ManagerPermissions), 'is_super' => false, 'created_at' => $now, 'updated_at' => $now,
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('email')->constrained()->restrictOnDelete();
        });

        DB::table('users')->where('role', 'owner')->update(['role_id' => $ownerId]);
        DB::table('users')->where('role', '!=', 'owner')->update(['role_id' => $managerId]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('manager')->after('email');
        });

        $ownerRoles = DB::table('roles')->where('is_super', true)->pluck('id');
        DB::table('users')->whereIn('role_id', $ownerRoles)->update(['role' => 'owner']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
