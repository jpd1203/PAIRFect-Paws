<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('post_adoption_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_in_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('milestone');

            $table->string('health_status');
            $table->string('eating_and_drinking');
            $table->string('behavior');
            $table->string('living_conditions');
            $table->boolean('vet_visit')->default(false);
            $table->text('concerns')->nullable();
            $table->string('photo_path')->nullable();

            $table->date('report_date');
            $table->boolean('flagged')->default(false);
            $table->string('flag_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_adoption_reports');
    }
};
