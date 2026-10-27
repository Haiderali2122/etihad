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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('ordered_on');
            $table->foreignId('book_format_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('copies');
            $table->decimal('forms', 6, 2);
            $table->decimal('wastage_percent', 5, 2)->default(0);
            $table->unsignedInteger('rims_needed');
            $table->foreignId('paper_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('mode')->default('in_house');
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('press_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('stage')->default('waiting');
            $table->foreignId('paper_issue_id')->nullable()->constrained()->nullOnDelete();
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
        Schema::dropIfExists('work_orders');
    }
};
