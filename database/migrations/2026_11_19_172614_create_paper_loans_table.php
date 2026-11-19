<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an order needs more rims than its party has, the rest is taken from another stock (the press's own or
 * another party's) and the order party owes those rims. It is settled later with rims (moved back between the two
 * stocks) or with money.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('paper_loans', function (Blueprint $table) {
            $table->id();
            $table->date('borrowed_on')->index();
            $table->foreignId('paper_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrower_party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('lender_party_id')->constrained('parties')->restrictOnDelete();
            $table->unsignedInteger('rims');
            $table->foreignId('paper_issue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cover_issue_id')->nullable()->constrained('paper_issues')->nullOnDelete();
            $table->date('settled_on')->nullable();
            $table->string('settled_how', 10)->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_loans');
    }
};
