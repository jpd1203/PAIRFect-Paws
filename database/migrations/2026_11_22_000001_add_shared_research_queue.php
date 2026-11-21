<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('research_placements')->exists()) {
            throw new RuntimeException('Preserve the previous research batch. Initialize a new research schema for the shared queue.');
        }

        Schema::table('research_placements', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id', 'research_placements_account_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamp('approved_at')->nullable()->change();
            $table->float('compatibility_score')->nullable()->change();
            $table->string('algorithm_version')->nullable()->change();
            $table->string('status')->default('Pending');
            $table->boolean('is_primary_candidate')->default(false);
            $table->json('compatibility_result')->nullable();
            $table->timestamp('queue_promoted_at')->nullable();
            $table->timestamp('queue_closed_at')->nullable();
            $table->timestamps();
            $table->index(['pet_id', 'status']);
        });
    }

    public function down(): void
    {
        if (DB::table('research_placements')->exists()) {
            throw new RuntimeException('Cannot roll back a research batch containing applications.');
        }

        if (DB::getDriverName() === 'mysql') {
            // The composite index may have replaced InnoDB's implicit FK index.
            Schema::table('research_placements', fn (Blueprint $table) => $table->index('pet_id', 'research_placements_catalog_lookup'));
        }
        Schema::table('research_placements', function (Blueprint $table) {
            $table->dropForeign('research_placements_account_foreign');
            $table->foreign('user_id')->references('user_id')->on('research_participants')->cascadeOnDelete();
            $table->timestamp('approved_at')->nullable(false)->change();
            $table->decimal('compatibility_score', 8, 4)->nullable(false)->change();
            $table->string('algorithm_version')->nullable(false)->change();
            $table->dropIndex(['pet_id', 'status']);
            $table->dropColumn(['status', 'is_primary_candidate', 'compatibility_result', 'queue_promoted_at', 'queue_closed_at', 'created_at', 'updated_at']);
        });
    }
};
