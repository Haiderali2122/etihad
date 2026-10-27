<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_ur')->nullable();
            $table->string('father_name')->nullable();
            $table->string('cnic', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('designation');
            $table->foreignId('foreman_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_foreman')->default(false);
            $table->unsignedInteger('monthly_salary');
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
