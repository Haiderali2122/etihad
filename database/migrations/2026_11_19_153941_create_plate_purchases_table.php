<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blank plates bought from suppliers, by size: the stock the plate jobs draw on. Each purchase is a bill paid
 * later, in parts, like the other bills.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plate_purchases', function (Blueprint $table) {
            $table->id();
            $table->date('purchased_on')->index();
            $table->string('supplier', 120)->nullable();
            $table->string('plate_size', 40)->index();
            $table->unsignedInteger('plates');
            $table->decimal('rate', 12, 2);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('bill_no', 40)->nullable();
            $table->string('attachment')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plate_purchases');
    }
};
