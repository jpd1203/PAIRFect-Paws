<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_adoption_logs', function (Blueprint $table) {
            $table->string('resolution_outcome')->nullable();
            $table->date('return_date')->nullable();
            $table->text('return_reason')->nullable();
            $table->text('return_condition')->nullable();
            $table->foreignId('return_handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('post_adoption_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_handled_by_user_id');
            $table->dropColumn(['resolution_outcome', 'return_date', 'return_reason', 'return_condition']);
        });
    }
};
