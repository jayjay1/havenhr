<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->uuid('pipeline_stage_id')->nullable()->after('resume_snapshot');

            $table->foreign('pipeline_stage_id')->references('id')->on('pipeline_stages');
            $table->foreign('job_posting_id')->references('id')->on('job_postings');

            $table->index('pipeline_stage_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropForeign(['pipeline_stage_id']);
            $table->dropForeign(['job_posting_id']);
            $table->dropIndex(['pipeline_stage_id']);
            $table->dropColumn('pipeline_stage_id');
        });
    }
};
