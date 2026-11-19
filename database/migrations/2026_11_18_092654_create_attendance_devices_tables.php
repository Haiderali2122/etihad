<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ZKTeco attendance machine sends punches straight to the site (ADMS / Cloud Server). A machine is known by
 * its serial number and only an approved one is listened to; the people registered on it and every punch are kept.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number', 60)->unique();
            $table->string('name', 120)->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('attlog_stamp', 40)->nullable();
            $table->string('operlog_stamp', 40)->nullable();
            $table->timestamp('users_requested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained()->cascadeOnDelete();
            $table->string('pin', 30);
            $table->string('name', 120)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['attendance_device_id', 'pin']);
        });

        Schema::create('device_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained()->cascadeOnDelete();
            $table->string('pin', 30)->index();
            $table->dateTime('punched_at')->index();
            $table->unsignedSmallInteger('state')->nullable();
            $table->unsignedSmallInteger('verify')->nullable();
            $table->timestamps();
            $table->unique(['attendance_device_id', 'pin', 'punched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_punches');
        Schema::dropIfExists('device_users');
        Schema::dropIfExists('attendance_devices');
    }
};
