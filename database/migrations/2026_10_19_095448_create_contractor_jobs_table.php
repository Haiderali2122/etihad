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
        Schema::create('contractor_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->date('job_on');
            $table->decimal('forms', 6, 2);
            $table->unsignedInteger('copies');
            $table->decimal('folding_rate', 10, 2)->nullable();
            $table->decimal('form_rate', 10, 2)->nullable();
            $table->decimal('copy_rate', 10, 2)->nullable();
            $table->unsignedInteger('amount');
            $table->string('state')->default('in_progress');
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
        Schema::dropIfExists('contractor_jobs');
    }
};
