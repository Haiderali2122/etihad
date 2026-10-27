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
        Schema::create('machine_repairs', function (Blueprint $table) {
            $table->id();
            $table->date('repaired_on');
            $table->foreignId('machine_id')->constrained();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_id')->constrained();
            $table->string('kind')->default('repair');
            $table->string('description');
            $table->unsignedBigInteger('bill_amount');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('amount_paid');
            $table->string('invoice_no')->nullable();
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
        Schema::dropIfExists('machine_repairs');
    }
};
