<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handover_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id')->constrained('handovers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind'); // prepared, released, reminder, completed, issue_logged, reopened
            $table->string('title');
            $table->text('body');
            $table->json('channels')->nullable(); // ['In-app', 'Email', 'SMS', 'Push']
            $table->string('action_label')->nullable();
            $table->string('action_url')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_notifications');
    }
};
