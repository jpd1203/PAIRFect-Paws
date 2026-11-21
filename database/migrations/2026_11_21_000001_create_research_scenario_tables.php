<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_catalog', function (Blueprint $table) {
            $table->foreignId('pet_id')->primary()->constrained('pets')->restrictOnDelete();
        });
        Schema::create('research_participants', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->timestamp('enrolled_at');
        });
        Schema::create('research_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('research_participants', 'user_id')->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained('research_catalog', 'pet_id')->restrictOnDelete();
            $table->unique(['user_id', 'pet_id']);
            $table->decimal('compatibility_score', 8, 4);
            $table->string('algorithm_version');
            $table->timestamp('approved_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('received_at')->nullable();
        });
        Schema::create('research_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained('research_placements')->cascadeOnDelete();
            $table->string('milestone');
            $table->unique(['placement_id', 'milestone']);
            $table->json('answers');
            $table->timestamp('submitted_at');
        });
    }

    public function down(): void
    {
        foreach (['research_checkins', 'research_placements', 'research_participants', 'research_catalog'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
