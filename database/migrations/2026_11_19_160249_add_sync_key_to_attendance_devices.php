<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The attendance reader program on an office computer reads the machine over the office network and sends what
 * it finds. It proves who it is with this key, and the last time it sent anything is kept.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->string('sync_key', 64)->nullable()->after('info');
            $table->timestamp('last_synced_at')->nullable()->after('sync_key');
            $table->string('last_sync_result', 255)->nullable()->after('last_synced_at');
        });

        foreach (DB::table('attendance_devices')->pluck('id') as $id) {
            DB::table('attendance_devices')->where('id', $id)->update(['sync_key' => Str::random(40)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropColumn(['sync_key', 'last_synced_at', 'last_sync_result']);
        });
    }
};
