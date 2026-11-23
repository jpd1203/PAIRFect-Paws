<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_checkins', function (Blueprint $table) {
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_outcome', 32)->nullable();
            $table->timestamp('welfare_evaluated_at')->nullable();
            $table->json('welfare_case')->nullable();
            $table->index(['is_flagged', 'resolved_at'], 'research_checkins_open_flags');
        });
    }

    public function down(): void
    {
        Schema::table('research_checkins', function (Blueprint $table) {
            $table->dropIndex('research_checkins_open_flags');
            $table->dropColumn(['is_flagged', 'resolved_at', 'resolution_outcome', 'welfare_evaluated_at', 'welfare_case']);
        });
    }
};
