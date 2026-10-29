<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an entry the owner sent back has been corrected and entered again, the old one is marked as resent
 * so it no longer shows as waiting for the manager's attention. It stays on record as sent back.
 */
return new class extends Migration
{
    /**
     * Every table whose entries wait for the owner's approval.
     *
     * @var list<string>
     */
    private array $tables = [
        'paper_receipts', 'purchases', 'expenses', 'machine_repairs', 'salary_advances', 'foreman_sheets',
        'loans', 'work_orders', 'contractor_payments', 'paper_issues', 'inventory_issues',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('resent_at')->nullable()->after('review_note');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('resent_at');
            });
        }
    }
};
