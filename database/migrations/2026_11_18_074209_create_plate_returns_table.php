<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plates come back from a processor in parts: each return is kept with its date and number of plates (the job's
 * returned total is their sum). Plate jobs also record the plate size.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plate_jobs', function (Blueprint $table) {
            $table->string('plate_size', 40)->nullable()->after('work_order_id');
        });

        Schema::create('plate_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plate_job_id')->constrained()->cascadeOnDelete();
            $table->date('returned_on');
            $table->unsignedInteger('plates');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Returns recorded before as a single total become one return on the date last recorded.
        foreach (DB::table('plate_jobs')->where('returned_plates', '>', 0)->get() as $job) {
            DB::table('plate_returns')->insert([
                'plate_job_id' => $job->id, 'returned_on' => $job->returned_on ?? $job->job_on, 'plates' => $job->returned_plates,
                'created_by' => $job->created_by, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plate_returns');
        Schema::table('plate_jobs', fn (Blueprint $table) => $table->dropColumn('plate_size'));
    }
};
