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
        Schema::create('paper_issues', function (Blueprint $table) {
            $table->id();
            $table->date('issued_on');
            $table->string('kind');
            $table->foreignId('party_id')->constrained();
            $table->foreignId('paper_type_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->string('reference')->nullable();
            $table->string('book_name')->nullable();
            $table->string('issued_to');
            $table->decimal('wastage_percent', 5, 2)->default(0);
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
        Schema::dropIfExists('paper_issues');
    }
};
