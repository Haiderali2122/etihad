<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commands waiting to be sent to the attendance machine (one each time it checks in), and its last answers,
 * so what the machine replied can be seen.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->json('pending_commands')->nullable()->after('users_requested_at');
            $table->unsignedInteger('next_command_id')->default(1)->after('pending_commands');
            $table->text('last_reply')->nullable()->after('next_command_id');
            $table->string('info', 255)->nullable()->after('last_reply');
        });

        // A machine approved before this asks again, now in the way its software understands.
        DB::table('attendance_devices')->where('is_approved', true)->update(['users_requested_at' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropColumn(['pending_commands', 'next_command_id', 'last_reply', 'info']);
        });
    }
};
