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
        Schema::create('scorecards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('interview_id');
            $table->uuid('submitted_by');
            $table->unsignedSmallInteger('overall_rating');
            $table->string('overall_recommendation', 20);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->foreign('interview_id')
                ->references('id')->on('interviews')
                ->cascadeOnDelete();
            $table->foreign('submitted_by')
                ->references('id')->on('users')
                ->cascadeOnDelete();

            $table->unique(['interview_id', 'submitted_by']);

            $table->index('interview_id');
            $table->index('submitted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorecards');
    }
};
