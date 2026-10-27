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
        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('dispatched_on');
            $table->foreignId('buyer_id')->constrained('parties')->restrictOnDelete();
            $table->string('destination')->nullable();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('book_format_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('bundles');
            $table->unsignedInteger('copies_per_bundle');
            $table->unsignedInteger('loose_copies')->default(0);
            $table->unsignedInteger('copies');
            $table->decimal('rims', 10, 2)->nullable();
            $table->decimal('labour_per_bundle', 10, 2)->default(0);
            $table->decimal('labour_per_rim', 10, 2)->default(0);
            $table->unsignedInteger('labour_amount')->default(0);
            $table->string('labour_paid_to')->nullable();
            $table->string('truck_no')->nullable();
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
        Schema::dropIfExists('dispatches');
    }
};
