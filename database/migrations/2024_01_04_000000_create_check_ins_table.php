<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('milestone'); // ThreeDay | ThreeWeek | ThreeMonth  (3-3-3 framework)
            $table->date('due_date');
            $table->unsignedTinyInteger('status')->default(0); // 0 Upcoming,1 Pending,2 Submitted,3 Overdue
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
