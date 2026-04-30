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
        Schema::create('candidate_refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('candidate_id');
            $table->string('token_hash');
            $table->timestamp('expires_at');
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('candidate_id')->references('id')->on('candidates')->cascadeOnDelete();
            $table->index('token_hash');
            $table->index(['candidate_id', 'is_revoked']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_refresh_tokens');
    }
};
