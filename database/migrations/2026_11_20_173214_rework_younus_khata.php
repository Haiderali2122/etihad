<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Younus ka khata is entered on its own page: any work he does (rims into the godown, to and from the presses, to
 * folding, truck loading, anything typed), written freely, with a quantity, unit and rate; a work order only when
 * it belongs to one. He is paid weekly (Sunday to Saturday): advances during the week, the rest on Saturday.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rim_loadings', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->change();
            $table->string('work_type', 80)->nullable()->after('kind');
            $table->text('details')->nullable()->after('work_type');
            $table->string('unit', 20)->default('rims')->after('rims');
            $table->foreignId('paper_receipt_id')->nullable()->after('work_order_id')->constrained()->nullOnDelete();
        });

        Schema::create('younus_payments', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12); // advance during the week, or the Saturday payment
            $table->date('paid_on')->index();
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('younus_payments');
        Schema::table('rim_loadings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paper_receipt_id');
            $table->dropColumn(['work_type', 'details', 'unit']);
        });
    }
};
