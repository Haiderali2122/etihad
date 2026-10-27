<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchases used a fixed category list and a typed item name. Move them onto editable
 * categories and an item list, so each item has a running stock balance.
 */
return new class extends Migration
{
    /**
     * The old fixed categories, with Urdu names; paper's stock lives on the Paper stock screen.
     *
     * @var array<string, array{0: string, 1: string, 2: bool}>
     */
    private const Categories = [
        'paper' => ['Paper', 'کاغذ', false],
        'ink' => ['Ink', 'سیاہی', true],
        'plates' => ['Plates', 'پلیٹیں', true],
        'glue' => ['Glue', 'گلو', true],
        'parts' => ['Machine parts', 'مشین کے پرزے', true],
        'other' => ['Other', 'دیگر', true],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $categoryIds = [];
        foreach (self::Categories as $key => [$name, $ur, $tracks]) {
            $categoryIds[$key] = DB::table('inventory_categories')->insertGetId([
                'name' => $name, 'name_ur' => $ur, 'tracks_stock' => $tracks, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('party_id')->constrained()->restrictOnDelete();
        });

        foreach (DB::table('purchases')->get(['id', 'category', 'item', 'unit']) as $purchase) {
            $categoryId = $categoryIds[$purchase->category] ?? $categoryIds['other'];
            $itemId = DB::table('inventory_items')->where('inventory_category_id', $categoryId)->where('name', $purchase->item)->value('id')
                ?? DB::table('inventory_items')->insertGetId([
                    'inventory_category_id' => $categoryId, 'name' => $purchase->item, 'unit' => $purchase->unit,
                    'reorder_level' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            DB::table('purchases')->where('id', $purchase->id)->update(['inventory_item_id' => $itemId]);
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['category']);
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['category', 'item', 'unit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('category')->default('other')->index()->after('party_id');
            $table->string('item')->default('')->after('category');
            $table->string('unit')->default('')->after('quantity');
        });

        $keys = [];
        foreach (self::Categories as $key => [$name]) {
            $keys[$name] = $key;
        }

        $items = DB::table('inventory_items')
            ->join('inventory_categories', 'inventory_categories.id', '=', 'inventory_items.inventory_category_id')
            ->get(['inventory_items.id', 'inventory_items.name', 'inventory_items.unit', 'inventory_categories.name as category'])
            ->keyBy('id');

        foreach (DB::table('purchases')->get(['id', 'inventory_item_id']) as $purchase) {
            $item = $items[$purchase->inventory_item_id] ?? null;
            if ($item) {
                DB::table('purchases')->where('id', $purchase->id)->update([
                    'category' => $keys[$item->category] ?? 'other', 'item' => $item->name, 'unit' => $item->unit,
                ]);
            }
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
        });
    }
};
