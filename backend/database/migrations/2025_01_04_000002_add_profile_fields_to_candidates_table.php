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
        Schema::table('candidates', function (Blueprint $table) {
            $table->text('professional_summary')->nullable()->after('portfolio_url');
            $table->string('github_url', 500)->nullable()->after('professional_summary');
            $table->boolean('is_profile_public')->default(false)->after('github_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['professional_summary', 'github_url', 'is_profile_public']);
        });
    }
};
