<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One expense receipt can now hold several payments, each with its own head, payee and amount
 * (e.g. glue and ink to one shop, diesel to the pump). Existing lines take their receipt's head and
 * payee, and every expense gets at least one line so spending by head can always be read from the lines.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            $table->foreignId('expense_head_id')->nullable()->after('expense_id')->constrained()->restrictOnDelete();
            $table->string('paid_to')->nullable()->after('expense_head_id');
        });

        foreach (DB::table('expenses')->get(['id', 'expense_head_id', 'paid_to', 'reason', 'amount', 'created_at']) as $expense) {
            $updated = DB::table('expense_items')->where('expense_id', $expense->id)
                ->update(['expense_head_id' => $expense->expense_head_id, 'paid_to' => $expense->paid_to]);

            if ($updated === 0) {
                DB::table('expense_items')->insert([
                    'expense_id' => $expense->id, 'expense_head_id' => $expense->expense_head_id, 'paid_to' => $expense->paid_to,
                    'description' => $expense->reason, 'amount' => $expense->amount,
                    'created_at' => $expense->created_at, 'updated_at' => $expense->created_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_head_id');
            $table->dropColumn('paid_to');
        });
    }
};
