<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table) {
            // Interview scheduling
            $table->date('interview_date')->nullable()->after('status');
            $table->time('interview_time')->nullable()->after('interview_date');
            $table->string('conducted_by')->nullable()->after('interview_time');
            $table->text('interview_notes')->nullable()->after('conducted_by');
            $table->text('decision_remarks')->nullable()->after('interview_notes');

            // Snapshot of the Pet Recommendation compatibility result, if the
            // applicant used that feature before applying (null otherwise)
            $table->json('compatibility_result')->nullable()->after('decision_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->dropColumn([
                'interview_date', 'interview_time', 'conducted_by',
                'interview_notes', 'decision_remarks', 'compatibility_result',
            ]);
        });
    }
};
