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
        Schema::create('resumes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('candidate_id');
            $table->string('title');
            $table->string('template_slug');
            $table->json('content');
            $table->boolean('is_complete')->default(false);
            $table->string('public_link_token')->nullable()->unique();
            $table->boolean('public_link_active')->default(false);
            $table->boolean('show_contact_on_public')->default(false);
            $table->timestamps();

            $table->foreign('candidate_id')->references('id')->on('candidates')->cascadeOnDelete();
            $table->index('candidate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
