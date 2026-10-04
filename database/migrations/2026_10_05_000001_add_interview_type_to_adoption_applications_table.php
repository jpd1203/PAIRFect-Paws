<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table): void {
            $table->string('interview_mode', 16)->nullable();
            $table->string('interview_meeting_url', 2048)->nullable();
            $table->text('interview_location')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table): void {
            $table->dropColumn(['interview_mode', 'interview_meeting_url', 'interview_location']);
        });
    }
};
