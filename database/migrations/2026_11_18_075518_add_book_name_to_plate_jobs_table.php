<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plates are made before the work order exists, so a plate job records which book it is for (typed or picked).
 * Jobs already linked to an order take that order's book name.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plate_jobs', function (Blueprint $table) {
            $table->string('book_name', 150)->nullable()->after('work_order_id');
        });

        $linked = DB::table('plate_jobs')
            ->join('work_orders', 'work_orders.id', '=', 'plate_jobs.work_order_id')
            ->join('book_formats', 'book_formats.id', '=', 'work_orders.book_format_id')
            ->join('books', 'books.id', '=', 'book_formats.book_id')
            ->get(['plate_jobs.id', 'books.name']);
        foreach ($linked as $row) {
            DB::table('plate_jobs')->where('id', $row->id)->update(['book_name' => $row->name]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plate_jobs', fn (Blueprint $table) => $table->dropColumn('book_name'));
    }
};
