<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra work on a folding or binding job, each charged per 1,000 books: sticker pasting, numbering and pinning.
 * Empty when that work was not done on the job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractor_jobs', function (Blueprint $table) {
            $table->decimal('sticker_rate', 10, 2)->nullable()->after('copy_rate');
            $table->decimal('numbering_rate', 10, 2)->nullable()->after('sticker_rate');
            $table->decimal('pinning_rate', 10, 2)->nullable()->after('numbering_rate');
        });
    }

    public function down(): void
    {
        Schema::table('contractor_jobs', function (Blueprint $table) {
            $table->dropColumn(['sticker_rate', 'numbering_rate', 'pinning_rate']);
        });
    }
};
