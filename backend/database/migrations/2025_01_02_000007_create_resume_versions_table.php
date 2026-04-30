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
        Schema::create('resume_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('resume_id');
            $table->json('content');
            $table->integer('version_number');
            $table->string('change_summary')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('resume_id')->references('id')->on('resumes')->cascadeOnDelete();
            $table->index('resume_id');
            $table->unique(['resume_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resume_versions');
    }
};
