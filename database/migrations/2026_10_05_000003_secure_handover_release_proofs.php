<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handovers', function (Blueprint $table): void {
            $table->string('proof_path')->nullable();
            $table->foreignId('release_recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('release_handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('handovers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('release_recorded_by_user_id');
            $table->dropConstrainedForeignId('release_handled_by_user_id');
            $table->dropColumn('proof_path');
        });
    }
};
