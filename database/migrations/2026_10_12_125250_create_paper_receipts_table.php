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
        Schema::create('paper_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('slip_no')->unique();
            $table->dateTime('received_at');
            $table->foreignId('party_id')->constrained();
            $table->foreignId('paper_type_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->string('truck_no')->nullable();
            $table->string('store');
            $table->string('received_by');
            $table->string('note')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_receipts');
    }
};
