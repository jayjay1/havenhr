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
        Schema::create('job_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('candidate_id');
            $table->uuid('job_posting_id');
            $table->uuid('resume_id');
            $table->json('resume_snapshot');
            $table->string('status')->default('submitted');
            $table->timestamp('applied_at');
            $table->timestamp('updated_at')->nullable();

            $table->foreign('candidate_id')->references('id')->on('candidates')->cascadeOnDelete();
            $table->foreign('resume_id')->references('id')->on('resumes');
            $table->unique(['candidate_id', 'job_posting_id']);
            $table->index('job_posting_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
