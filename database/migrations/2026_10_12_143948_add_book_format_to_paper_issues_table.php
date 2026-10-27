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
        Schema::table('paper_issues', function (Blueprint $table) {
            $table->foreignId('book_format_id')->nullable()->after('reference')->constrained()->nullOnDelete();
            $table->unsignedInteger('copies')->nullable()->after('book_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paper_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('book_format_id');
            $table->dropColumn('copies');
        });
    }
};
