<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handovers', function (Blueprint $table): void {
            $table->text('tracking_url')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('handovers', function (Blueprint $table): void {
            $table->dropColumn('tracking_url');
        });
    }
};
