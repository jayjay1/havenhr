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
        Schema::create('interviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('job_application_id');
            $table->uuid('interviewer_id');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('location', 500);
            $table->string('interview_type', 20);
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamp('candidate_reminder_sent_at')->nullable();
            $table->timestamp('interviewer_reminder_sent_at')->nullable();
            $table->timestamps();

            $table->foreign('job_application_id')
                ->references('id')->on('job_applications')
                ->cascadeOnDelete();
            $table->foreign('interviewer_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();

            $table->index('job_application_id');
            $table->index('interviewer_id');
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
