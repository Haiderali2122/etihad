<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The date a folding or binding job was finished. Jobs already marked done take their last update date.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contractor_jobs', function (Blueprint $table) {
            $table->date('done_on')->nullable()->after('state');
        });

        DB::table('contractor_jobs')->where('state', 'done')->update(['done_on' => DB::raw('date(updated_at)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractor_jobs', function (Blueprint $table) {
            $table->dropColumn('done_on');
        });
    }
};
