<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->string('applicant_first_name')->nullable()->after('pet_id');
            $table->string('applicant_last_name')->nullable()->after('applicant_first_name');
            $table->string('applicant_email')->nullable()->after('applicant_last_name');
            $table->string('applicant_phone')->nullable()->after('applicant_email');
            $table->text('applicant_address')->nullable()->after('applicant_phone');
            $table->string('physical_activity_level')->nullable()->after('income_range');
            $table->string('time_availability')->nullable()->after('physical_activity_level');
            $table->string('prior_pet_experience')->nullable()->after('time_availability');
            $table->string('household_composition')->nullable()->after('prior_pet_experience');

            $table->string('document_disk')->default('local')->after('document_path');
            $table->string('document_original_name')->nullable()->after('document_disk');
            $table->string('document_mime_type')->nullable()->after('document_original_name');
            $table->string('document_verification_status')->default('Pending')->after('document_mime_type')->index();
            $table->string('document_type')->nullable()->after('document_verification_status');
            $table->longText('ocr_extracted_text')->nullable()->after('document_type');
            $table->decimal('ocr_confidence', 5, 4)->nullable()->after('ocr_extracted_text');
            $table->decimal('document_match_score', 5, 4)->nullable()->after('ocr_confidence');
            $table->json('document_verification_reasons')->nullable()->after('document_match_score');
            $table->timestamp('document_uploaded_at')->nullable()->after('document_verification_reasons');
            $table->timestamp('document_verified_at')->nullable()->after('document_uploaded_at');
            $table->unsignedInteger('document_reupload_count')->default(0)->after('document_verified_at');
        });

        DB::table('adoption_applications')->whereNotNull('document_path')->update([
            'document_disk' => 'public',
            'document_verification_status' => 'LegacyReview',
            'document_uploaded_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->dropIndex(['document_verification_status']);
            $table->dropColumn([
                'applicant_first_name', 'applicant_last_name', 'applicant_email',
                'applicant_phone', 'applicant_address', 'physical_activity_level',
                'time_availability', 'prior_pet_experience', 'household_composition',
                'document_disk', 'document_original_name', 'document_mime_type',
                'document_verification_status', 'document_type', 'ocr_extracted_text',
                'ocr_confidence', 'document_match_score', 'document_verification_reasons',
                'document_uploaded_at', 'document_verified_at', 'document_reupload_count',
            ]);
        });
    }
};
