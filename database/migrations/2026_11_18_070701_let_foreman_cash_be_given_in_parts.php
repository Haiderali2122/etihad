<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The manager gives a foreman cash in several parts, on any day. Each handout is its own row; its date decides
 * which payday it counts toward (10th–21st: the 22nd's advance salary; 22nd–9th: the 10th's salaries).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL needs an index on foreman_id for its foreign key: add the plain index before dropping the unique one.
        Schema::table('foreman_cashes', function (Blueprint $table) {
            $table->index(['foreman_id', 'month', 'payday']);
            $table->string('note')->nullable()->after('amount');
        });
        Schema::table('foreman_cashes', function (Blueprint $table) {
            $table->dropUnique(['foreman_id', 'month', 'payday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('foreman_cashes', function (Blueprint $table) {
            $table->unique(['foreman_id', 'month', 'payday']);
        });
        Schema::table('foreman_cashes', function (Blueprint $table) {
            $table->dropColumn('note');
            $table->dropIndex(['foreman_id', 'month', 'payday']);
        });
    }
};
