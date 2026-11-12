<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchases bought together on a material tab (e.g. Diesel and Mitti ka tel on one bill) share a receipt number,
 * so one printed receipt lists every line and the total.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('receipt_no', 20)->nullable()->after('bill_no')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['receipt_no']);
            $table->dropColumn('receipt_no');
        });
    }
};
