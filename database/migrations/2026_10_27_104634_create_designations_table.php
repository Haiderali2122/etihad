<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Job titles staff can be given. Employees keep the title as text so slips and old records read the same;
 * the list starts from the titles already in use.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('name_ur', 80)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $existing = DB::table('employees')->whereNotNull('designation')->where('designation', '!=', '')->distinct()->orderBy('designation')->pluck('designation');
        DB::table('designations')->insert($existing->map(fn (string $name) => ['name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])->all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
