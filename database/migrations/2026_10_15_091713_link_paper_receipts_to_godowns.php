<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the free-text "unloaded in" on each receipt with a link to a godown record,
 * creating one godown for every place already written on a slip.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paper_receipts', function (Blueprint $table) {
            $table->foreignId('godown_id')->nullable()->after('truck_no')->constrained()->restrictOnDelete();
        });

        $now = now();
        foreach (DB::table('paper_receipts')->distinct()->pluck('store')->filter() as $name) {
            $id = DB::table('godowns')->where('name', $name)->value('id')
                ?? DB::table('godowns')->insertGetId(['name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('paper_receipts')->where('store', $name)->update(['godown_id' => $id]);
        }

        Schema::table('paper_receipts', function (Blueprint $table) {
            $table->dropColumn('store');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paper_receipts', function (Blueprint $table) {
            $table->string('store')->default('')->after('truck_no');
        });

        foreach (DB::table('godowns')->get(['id', 'name']) as $godown) {
            DB::table('paper_receipts')->where('godown_id', $godown->id)->update(['store' => $godown->name]);
        }

        Schema::table('paper_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('godown_id');
        });
    }
};
