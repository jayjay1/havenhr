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
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('candidate_id');
            $table->string('job_type');
            $table->json('input_data');
            $table->json('result_data')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('processing_duration_ms')->nullable();
            $table->timestamps();

            $table->foreign('candidate_id')->references('id')->on('candidates')->cascadeOnDelete();
            $table->index(['candidate_id', 'created_at']);
            $table->index(['candidate_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_jobs');
    }
};
