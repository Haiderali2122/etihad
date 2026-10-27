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
        Schema::create('outside_print_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->date('received_on');
            $table->unsignedInteger('printed_copies')->default(0);
            $table->unsignedInteger('leftover_rims')->default(0);
            $table->foreignId('paper_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('bill_amount')->nullable();
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
        Schema::dropIfExists('outside_print_receipts');
    }
};
