<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            // Admin-facing fields not present on the adopter-side Pet migration
            $table->unsignedTinyInteger('assessment_count')->default(0)->after('temperament');
            $table->timestamp('last_assessed_at')->nullable()->after('assessment_count');
            $table->string('last_assessed_by')->nullable()->after('last_assessed_at');
            $table->text('notes')->nullable()->after('last_assessed_by');
            $table->string('vaccination_record_status')->default('Incomplete')->after('notes'); // Complete | Incomplete
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn(['assessment_count', 'last_assessed_at', 'last_assessed_by', 'notes', 'vaccination_record_status']);
        });
    }
};
