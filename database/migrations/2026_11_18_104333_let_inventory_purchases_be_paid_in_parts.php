<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory purchases are bills paid later, in parts, like the Diesel, Glue and Ink bills. Purchases entered
 * before this were counted as paid on their date: each gets one payment for its amount, and its Cash-out line
 * now belongs to that payment, so totals do not change.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_amount')->default(0)->after('amount');
        });

        foreach (DB::table('purchases')->get() as $purchase) {
            $expense = DB::table('expenses')->where('source_type', 'App\\Models\\Purchase')->where('source_id', $purchase->id)->first();
            if (! $expense) {
                continue;
            }

            $paymentId = DB::table('bill_payments')->insertGetId([
                'payable_type' => 'App\\Models\\Purchase', 'payable_id' => $purchase->id, 'paid_on' => $purchase->purchased_on,
                'amount' => $purchase->amount, 'created_by' => $purchase->created_by, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('expenses')->where('id', $expense->id)->update(['source_type' => 'App\\Models\\BillPayment', 'source_id' => $paymentId]);
            DB::table('purchases')->where('id', $purchase->id)->update(['paid_amount' => $purchase->amount]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('bill_payments')->where('payable_type', 'App\\Models\\Purchase')->get() as $payment) {
            DB::table('expenses')->where('source_type', 'App\\Models\\BillPayment')->where('source_id', $payment->id)
                ->update(['source_type' => 'App\\Models\\Purchase', 'source_id' => $payment->payable_id]);
        }
        DB::table('bill_payments')->where('payable_type', 'App\\Models\\Purchase')->delete();
        Schema::table('purchases', fn (Blueprint $table) => $table->dropColumn('paid_amount'));
    }
};
