<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->json('reschedule_options')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->string('reschedule_status')->nullable();
            $table->timestamp('reschedule_requested_at')->nullable();
            $table->timestamp('reschedule_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->dropColumn([
                'reschedule_options', 'reschedule_reason', 'reschedule_status',
                'reschedule_requested_at', 'reschedule_reviewed_at',
            ]);
        });
    }
};
