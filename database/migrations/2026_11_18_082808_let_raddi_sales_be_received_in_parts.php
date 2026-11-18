<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Raddi sale money comes in parts: each amount received is kept with its date and the rest is still to be
 * received. Sales entered before this were paid on the spot, so each gets one receipt for its total.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->date('received_on')->index();
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('received_amount')->default(0)->after('total');
        });

        foreach (DB::table('sales')->where('total', '>', 0)->get() as $sale) {
            DB::table('sale_receipts')->insert([
                'sale_id' => $sale->id, 'received_on' => $sale->sold_on, 'amount' => $sale->total,
                'created_by' => $sale->created_by, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('sales')->where('id', $sale->id)->update(['received_amount' => $sale->total]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('received_amount'));
        Schema::dropIfExists('sale_receipts');
    }
};
