<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The expense head is mitti ka tel (earthen oil), not "meethi ka tail". Renaming the row keeps every expense
 * already filed under it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('expense_heads')->where('name', 'Mitti ka tel (earthen oil)')->exists()) {
            return;
        }

        DB::table('expense_heads')->where('name', 'Meethi ka tail')
            ->update(['name' => 'Mitti ka tel (earthen oil)', 'name_ur' => 'مٹی کا تیل', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('expense_heads')->where('name', 'Mitti ka tel (earthen oil)')
            ->update(['name' => 'Meethi ka tail', 'name_ur' => 'میٹھا تیل', 'updated_at' => now()]);
    }
};
