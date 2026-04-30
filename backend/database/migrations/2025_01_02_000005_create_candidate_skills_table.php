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
        Schema::create('candidate_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('candidate_id');
            $table->string('name');
            $table->string('category');
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('candidate_id')->references('id')->on('candidates')->cascadeOnDelete();
            $table->index('candidate_id');
            $table->unique(['candidate_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_skills');
    }
};
