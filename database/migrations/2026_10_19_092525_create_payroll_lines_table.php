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
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('monthly_salary');
            $table->unsignedTinyInteger('days_in_month');
            $table->decimal('present_days', 5, 1)->default(0);
            $table->decimal('absent_days', 5, 1)->default(0);
            $table->decimal('leave_days', 5, 1)->default(0);
            $table->decimal('off_days', 5, 1)->default(0);
            $table->unsignedTinyInteger('unmarked_days')->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->integer('overtime_amount')->default(0);
            $table->integer('absence_deduction')->default(0);
            $table->integer('bonus')->default(0);
            $table->integer('advances_deducted')->default(0);
            $table->integer('loan_deducted')->default(0);
            $table->integer('other_deduction')->default(0);
            $table->integer('net_pay')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
    }
};
