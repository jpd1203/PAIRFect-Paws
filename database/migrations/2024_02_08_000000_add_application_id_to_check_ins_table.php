<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            // Links a monitoring check-in back to the approved application it
            // came from, so admin views can show applicant name / adopted date
            // without joining through user_id + pet_id guesswork.
            $table->foreignId('application_id')->nullable()->after('pet_id')
                ->constrained('adoption_applications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('application_id');
        });
    }
};
