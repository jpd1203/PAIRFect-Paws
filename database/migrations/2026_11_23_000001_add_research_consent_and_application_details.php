<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_study_consents', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('version', 30);
            $table->timestamp('accepted_at');
            $table->primary(['user_id', 'version']);
        });
        Schema::table('research_placements', function (Blueprint $table) {
            $table->text('application_details')->nullable();
            $table->string('document_verification_status')->nullable();
            $table->json('document_verification_reasons')->nullable();
            $table->timestamp('document_consent_at')->nullable();
            $table->string('document_consent_version', 30)->nullable();
            $table->unsignedInteger('document_reupload_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('research_placements', fn (Blueprint $table) => $table->dropColumn([
            'application_details', 'document_verification_status', 'document_verification_reasons', 'document_consent_at', 'document_consent_version', 'document_reupload_count',
        ]));
        Schema::dropIfExists('research_study_consents');
    }
};
