<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rims paid back for a shortage go back into the stock they were taken from, or into another stock picked when
 * settling.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paper_loans', function (Blueprint $table) {
            $table->foreignId('return_party_id')->nullable()->after('settled_how')->constrained('parties')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paper_loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_party_id');
        });
    }
};
