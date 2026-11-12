<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lines entered on the Diesel, Glue, Ink and Raddi tabs carry a quantity (e.g. litres) and a price per unit; the line total is quantity × rate.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->nullable()->after('description');
            $table->decimal('rate', 12, 2)->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'rate']);
        });
    }
};
