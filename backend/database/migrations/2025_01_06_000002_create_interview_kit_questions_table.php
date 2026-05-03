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
        Schema::create('interview_kit_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('interview_kit_id');
            $table->text('text');
            $table->string('category', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('scoring_rubric')->nullable();
            $table->timestamps();

            $table->foreign('interview_kit_id')
                ->references('id')->on('interview_kits')
                ->cascadeOnDelete();

            $table->index('interview_kit_id');
            $table->index(['interview_kit_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_kit_questions');
    }
};
