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
        Schema::create('scorecard_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('scorecard_id');
            $table->text('question_text');
            $table->string('category', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('rating');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('scorecard_id')
                ->references('id')->on('scorecards')
                ->cascadeOnDelete();

            $table->index('scorecard_id');
            $table->index(['scorecard_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorecard_criteria');
    }
};
