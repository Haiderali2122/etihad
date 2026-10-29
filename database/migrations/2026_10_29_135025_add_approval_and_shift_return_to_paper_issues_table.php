<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paper issued by hand now names the employee and machine, waits for the owner when the manager enters it,
 * and in-house issues can be closed at the end of the shift with the rims that came back. Earlier entries stay approved.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paper_issues', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('issued_to')->constrained()->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('returned_quantity')->default(0)->after('quantity');
            $table->timestamp('settled_at')->nullable()->after('returned_quantity');
            $table->foreignId('settled_by')->nullable()->after('settled_at')->constrained('users')->nullOnDelete();
            $table->string('status')->default('approved')->index()->after('note');
            $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_note')->nullable()->after('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paper_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('machine_id');
            $table->dropConstrainedForeignId('settled_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['returned_quantity', 'settled_at', 'status', 'reviewed_at', 'review_note']);
        });
    }
};
