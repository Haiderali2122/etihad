<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Changes from the 4 November 2026 press notes:
 *
 * - Machine parts are given to a kharadia, so a category can be marked for machines and an item moved out
 *   can name the kharadia (party) it went to.
 * - Petrol, diesel, glue and ink get their own inventory tabs, with payments recorded against them.
 * - Every payment made on another tab also appears as a Cash-out expense, linked back to where it was made.
 * - An expense can hold several items, each with its amount; the total prints on the receipt.
 * - Printed rims are loaded to folding by Younus's labourers ("Younus ka khata"), paid per rim.
 * - The truck loading keeps front and back photos of the truck and other files.
 * - Plates given to a processor can be returned.
 */
return new class extends Migration
{
    /**
     * The four materials that get their own tab, with their Urdu names.
     *
     * @var array<string, string>
     */
    private array $tabs = ['Petrol' => 'پیٹرول', 'Diesel' => 'ڈیزل', 'Glue' => 'گلو', 'Ink' => 'سیاہی'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_categories', function (Blueprint $table) {
            $table->boolean('for_machines')->default(false)->after('tracks_stock');
            $table->boolean('show_as_tab')->default(false)->after('for_machines');
        });
        DB::table('inventory_categories')->where('name', 'like', 'Machine part%')->update(['for_machines' => true]);

        $now = now();
        foreach ($this->tabs as $name => $ur) {
            $existing = DB::table('inventory_categories')->whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            $existing
                ? DB::table('inventory_categories')->where('id', $existing->id)->update(['show_as_tab' => true, 'tracks_stock' => true, 'is_active' => true])
                : DB::table('inventory_categories')->insert([
                    'name' => $name, 'name_ur' => $ur, 'tracks_stock' => true, 'show_as_tab' => true, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
        }

        Schema::table('inventory_issues', function (Blueprint $table) {
            $table->foreignId('party_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('inventory_category_id')->nullable()->after('machine_id')->constrained()->nullOnDelete();
            $table->nullableMorphs('source');
        });

        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });
        // Each existing expense becomes a receipt with one item.
        foreach (DB::table('expenses')->get(['id', 'reason', 'amount', 'created_at']) as $expense) {
            DB::table('expense_items')->insert([
                'expense_id' => $expense->id, 'description' => $expense->reason, 'amount' => $expense->amount,
                'created_at' => $expense->created_at, 'updated_at' => $expense->created_at,
            ]);
        }

        Schema::create('rim_loadings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->date('loaded_on');
            $table->decimal('rims', 10, 2);
            $table->decimal('rate_per_rim', 10, 2);
            $table->unsignedInteger('amount');
            $table->string('loaded_by')->default('Younus');
            $table->date('paid_on')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('dispatches', function (Blueprint $table) {
            $table->string('truck_front')->nullable()->after('truck_no');
            $table->string('truck_back')->nullable()->after('truck_front');
            $table->json('files')->nullable()->after('truck_back');
        });

        Schema::table('plate_jobs', function (Blueprint $table) {
            $table->unsignedInteger('returned_plates')->default(0)->after('plates');
            $table->date('returned_on')->nullable()->after('returned_plates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plate_jobs', function (Blueprint $table) {
            $table->dropColumn(['returned_plates', 'returned_on']);
        });
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn(['truck_front', 'truck_back', 'files']);
        });
        Schema::dropIfExists('rim_loadings');
        Schema::dropIfExists('expense_items');
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropMorphs('source');
            $table->dropConstrainedForeignId('inventory_category_id');
        });
        Schema::table('inventory_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('party_id');
        });
        Schema::table('inventory_categories', function (Blueprint $table) {
            $table->dropColumn(['for_machines', 'show_as_tab']);
        });
    }
};
