<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salaries are paid twice a month: an advance salary on the 22nd and the rest on the 10th of the next month.
 * Before each payday the manager gives a foreman cash; the foreman hands it to his team as kharcha. On payday
 * the manager records what each person already took, and only the difference is paid out.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('foreman_cashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foreman_id')->constrained('employees')->restrictOnDelete();
            $table->date('month');
            $table->string('payday', 10); // "advance" (22nd) or "final" (10th of next month)
            $table->date('given_on');
            $table->unsignedBigInteger('amount');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['foreman_id', 'month', 'payday']);
        });

        Schema::table('salary_advances', function (Blueprint $table) {
            $table->unsignedBigInteger('taken_from_foreman')->default(0)->after('amount');
        });

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('taken_from_foreman')->default(0)->after('net_pay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_lines', fn (Blueprint $table) => $table->dropColumn('taken_from_foreman'));
        Schema::table('salary_advances', fn (Blueprint $table) => $table->dropColumn('taken_from_foreman'));
        Schema::dropIfExists('foreman_cashes');
    }
};
