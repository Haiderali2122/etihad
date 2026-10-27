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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->date('purchased_on');
            $table->foreignId('party_id')->constrained();
            $table->string('category')->index();
            $table->string('item');
            $table->decimal('quantity', 12, 2);
            $table->string('unit');
            $table->decimal('rate', 12, 2);
            $table->unsignedBigInteger('amount');
            $table->string('bill_no')->nullable();
            $table->string('attachment')->nullable();
            $table->string('note')->nullable();
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
        Schema::dropIfExists('purchases');
    }
};
