<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flagged_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_in_id')->constrained()->cascadeOnDelete();
            $table->string('description');

            $table->boolean('is_escalated')->default(false);
            $table->text('escalation_reason')->nullable();

            $table->boolean('marked_for_intervention')->default(false);
            $table->string('intervention_type')->nullable();
            $table->text('intervention_notes')->nullable();

            $table->boolean('resolved')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flagged_cases');
    }
};
