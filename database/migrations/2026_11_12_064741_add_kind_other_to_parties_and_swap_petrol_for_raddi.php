<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parties of type "Other" get a typed name for their type (kept and offered next time). The material tabs
 * become Diesel, Glue, Ink and Raddi: Petrol stops being a tab (its records stay) and Raddi is added.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('kind_other', 80)->nullable()->after('kind');
        });

        $now = now();
        DB::table('inventory_categories')->whereRaw('LOWER(TRIM(name)) = ?', ['petrol'])->update(['show_as_tab' => false, 'updated_at' => $now]);

        $raddi = DB::table('inventory_categories')->whereRaw('LOWER(TRIM(name)) = ?', ['raddi'])->first();
        $raddi
            ? DB::table('inventory_categories')->where('id', $raddi->id)->update(['show_as_tab' => true, 'tracks_stock' => true, 'is_active' => true, 'updated_at' => $now])
            : DB::table('inventory_categories')->insert([
                'name' => 'Raddi', 'name_ur' => 'ردی', 'tracks_stock' => true, 'show_as_tab' => true, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
    }

    /**
     * Reverse the migrations. Raddi stays as a category; it may hold records.
     */
    public function down(): void
    {
        DB::table('inventory_categories')->whereRaw('LOWER(TRIM(name)) = ?', ['raddi'])->update(['show_as_tab' => false]);
        DB::table('inventory_categories')->whereRaw('LOWER(TRIM(name)) = ?', ['petrol'])->update(['show_as_tab' => true]);

        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('kind_other');
        });
    }
};
