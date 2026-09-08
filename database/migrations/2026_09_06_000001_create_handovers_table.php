<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. hv-2041
            $table->foreignId('application_id')->nullable()->constrained('adoption_applications')->nullOnDelete();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Adopter snapshot/cache if user not registered yet
            $table->string('adopter_name')->nullable();
            $table->string('adopter_phone')->nullable();
            $table->string('adopter_email')->nullable();
            $table->string('adopter_address')->nullable();
            $table->string('adopter_distance')->nullable();

            $table->timestamp('approved_at')->nullable();

            // Release details
            $table->string('release_method')->nullable(); // 'pickup', 'delivery'
            $table->date('release_date')->nullable();
            $table->string('release_time')->nullable();
            $table->string('staff_name')->nullable();
            $table->string('courier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('proof_name')->nullable();
            $table->text('proof_url')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamp('released_at')->nullable();

            // Adopter response / confirmation
            $table->string('adopter_outcome')->nullable(); // 'received', 'not_received'
            $table->timestamp('adopter_confirmed_at')->nullable();
            $table->text('adopter_note')->nullable();

            // Follow up & history
            $table->unsignedInteger('reopen_count')->default(0);
            $table->text('reopen_reason')->nullable();
            $table->json('reminders')->nullable(); // array of {at, channel}
            $table->json('history')->nullable();   // array of {at, label, actor}

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handovers');
    }
};
