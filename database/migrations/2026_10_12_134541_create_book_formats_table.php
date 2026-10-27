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
        Schema::create('book_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('size')->nullable();
            $table->decimal('forms', 6, 2);
            $table->unsignedInteger('pages')->nullable();
            $table->foreignId('paper_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('folding_rate', 10, 2)->nullable();
            $table->decimal('binding_rate', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_formats');
    }
};
