<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handover_notifications', function (Blueprint $table) {
            $table->foreignId('handover_id')->nullable()->change();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });

        DB::table('handover_notifications')->where('read', true)->whereNull('read_at')
            ->update(['read_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        if (DB::table('handover_notifications')->whereNull('handover_id')->exists()) {
            throw new RuntimeException('Cannot roll back notification columns while non-handover notices exist. Archive them before rolling back.');
        }

        Schema::table('handover_notifications', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'dedupe_key']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['user_id', 'read_at']);
            $table->dropColumn(['entity_type', 'entity_id', 'dedupe_key', 'read_at']);
            $table->foreignId('handover_id')->nullable(false)->change();
        });
    }
};
