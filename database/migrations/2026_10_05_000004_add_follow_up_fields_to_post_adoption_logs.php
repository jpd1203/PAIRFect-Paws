<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_adoption_logs', function (Blueprint $table) {
            $table->text('follow_up_notes')->nullable();
            $table->timestamp('follow_up_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('post_adoption_logs', function (Blueprint $table) {
            $table->dropColumn(['follow_up_notes', 'follow_up_submitted_at']);
        });
    }
};
