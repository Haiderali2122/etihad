<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Younus ka khata covers every rim movement (godown → our press, godown → outside press, outside press → back,
 * our press → folding) and the outside printing charge Younus takes to the press, each at a rate per rim.
 * An outsourced work order records its printing rate per rim when it is created.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rim_loadings', function (Blueprint $table) {
            // Lines recorded before this were all rims carried from the press to folding.
            $table->string('kind', 20)->default('to_folding')->after('work_order_id')->index();
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->decimal('printing_rate', 10, 2)->nullable()->after('press_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', fn (Blueprint $table) => $table->dropColumn('printing_rate'));
        Schema::table('rim_loadings', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
