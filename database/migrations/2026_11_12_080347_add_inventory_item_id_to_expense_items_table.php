<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ink and glue bought through their tab's expense form go into stock: each line points at the item it adds to.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('expense_head_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
        });
    }
};
