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
        Schema::create('stage_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('job_application_id');
            $table->uuid('from_stage_id');
            $table->uuid('to_stage_id');
            $table->uuid('moved_by');
            $table->timestamp('moved_at');

            $table->foreign('job_application_id')->references('id')->on('job_applications')->cascadeOnDelete();
            $table->foreign('from_stage_id')->references('id')->on('pipeline_stages');
            $table->foreign('to_stage_id')->references('id')->on('pipeline_stages');
            $table->foreign('moved_by')->references('id')->on('users');

            $table->index(['job_application_id', 'moved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_transitions');
    }
};
