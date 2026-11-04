<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A permanent log of records people delete: what was deleted, the reason they gave, and who did it.
 * It is append-only, so the history stays even after the record itself is gone.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deletions', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('kind');                 // what sort of record, e.g. "Book"
            $table->string('label');                // a readable name for the deleted record
            $table->string('note')->nullable();     // the reason the person gave
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deleted_by_name')->nullable(); // kept even if the user is later removed
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deletions');
    }
};
