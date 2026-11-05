<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handovers', function (Blueprint $table): void {
            $table->unsignedInteger('delivery_clearance_version')->default(0);
            $table->string('delivery_address_status', 20)->nullable();
            $table->foreignId('delivery_address_verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('delivery_address_verified_at')->nullable();
            $table->string('delivery_address_fingerprint', 64)->nullable();
            $table->string('delivery_contact_method', 30)->nullable();
            $table->foreignId('delivery_contacted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('delivery_contacted_at')->nullable();
            $table->boolean('delivery_recipient_available')->nullable();
            $table->unsignedInteger('delivery_readiness_schedule_version')->nullable();
            $table->string('delivery_readiness_schedule_fingerprint', 64)->nullable();
            $table->string('delivery_readiness_destination_fingerprint', 64)->nullable();
            $table->string('delivery_readiness_method', 20)->nullable();
            $table->unsignedInteger('delivery_readiness_release_attempt')->nullable();
            $table->text('delivery_readiness_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('handovers', function (Blueprint $table): void {
            $table->dropForeign(['delivery_address_verified_by_user_id']);
            $table->dropForeign(['delivery_contacted_by_user_id']);
            $table->dropColumn([
                'delivery_clearance_version', 'delivery_address_status', 'delivery_address_verified_by_user_id',
                'delivery_address_verified_at', 'delivery_address_fingerprint', 'delivery_contact_method',
                'delivery_contacted_by_user_id', 'delivery_contacted_at', 'delivery_recipient_available',
                'delivery_readiness_schedule_version', 'delivery_readiness_schedule_fingerprint',
                'delivery_readiness_destination_fingerprint', 'delivery_readiness_method',
                'delivery_readiness_release_attempt', 'delivery_readiness_notes',
            ]);
        });
    }
};
