<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money given to a plate processor, like Younus's khata: it clears their oldest unpaid plate bills, and anything
 * more is kept as an advance that the next plate bills use up. The parts used on bills are bill payments linked to
 * it; the money itself comes off the manager's cash once, as this payment.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->date('paid_on')->index();
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('bill_payments', function (Blueprint $table) {
            $table->foreignId('processor_payment_id')->nullable()->after('payable_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processor_payment_id');
        });
        Schema::dropIfExists('processor_payments');
    }
};
