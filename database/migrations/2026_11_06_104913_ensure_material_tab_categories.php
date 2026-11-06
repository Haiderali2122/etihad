<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Petrol, diesel, glue and ink must always exist as their own tabs (and sidebar entries), even after the
 * data was cleared. Existing categories are switched back on; missing ones are added.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $tabs = ['Petrol' => 'پیٹرول', 'Diesel' => 'ڈیزل', 'Glue' => 'گلو', 'Ink' => 'سیاہی'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        foreach ($this->tabs as $name => $ur) {
            $existing = DB::table('inventory_categories')->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->first();
            $existing
                ? DB::table('inventory_categories')->where('id', $existing->id)->update(['show_as_tab' => true, 'tracks_stock' => true, 'is_active' => true, 'updated_at' => $now])
                : DB::table('inventory_categories')->insert([
                    'name' => $name, 'name_ur' => $ur, 'tracks_stock' => true, 'show_as_tab' => true, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
        }
    }

    /**
     * Reverse the migrations. The categories stay; they may hold records by now.
     */
    public function down(): void
    {
        //
    }
};
