<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diesel, Glue and Ink are bought on bills that are paid later, in parts; plate jobs are paid in parts too. Each
 * payment is its own dated row and only payments come off the manager's cash. Plate jobs already marked paid get
 * one payment for their amount, and their Cash-out line now belongs to that payment, so totals do not change.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('material_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->constrained()->restrictOnDelete();
            $table->date('bill_on')->index();
            $table->string('supplier', 120)->nullable();
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('attachment')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('material_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 120);
            $table->decimal('quantity', 10, 2);
            $table->decimal('rate', 12, 2);
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });

        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->date('paid_on')->index();
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('plate_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_amount')->default(0)->after('amount');
        });

        foreach (DB::table('plate_jobs')->whereNotNull('paid_on')->get() as $job) {
            $paymentId = DB::table('bill_payments')->insertGetId([
                'payable_type' => 'App\\Models\\PlateJob', 'payable_id' => $job->id, 'paid_on' => $job->paid_on,
                'amount' => $job->amount, 'note' => $job->payment_note, 'created_by' => $job->created_by,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('plate_jobs')->where('id', $job->id)->update(['paid_amount' => $job->amount]);
            DB::table('expenses')->where('source_type', 'App\\Models\\PlateJob')->where('source_id', $job->id)
                ->update(['source_type' => 'App\\Models\\BillPayment', 'source_id' => $paymentId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('bill_payments')->where('payable_type', 'App\\Models\\PlateJob')->get() as $payment) {
            DB::table('expenses')->where('source_type', 'App\\Models\\BillPayment')->where('source_id', $payment->id)
                ->update(['source_type' => 'App\\Models\\PlateJob', 'source_id' => $payment->payable_id]);
        }
        Schema::table('plate_jobs', fn (Blueprint $table) => $table->dropColumn('paid_amount'));
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('material_bill_items');
        Schema::dropIfExists('material_bills');
    }
};
