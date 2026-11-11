<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->string('legacy_physical_size')->nullable();
            $table->string('size_classification_status')->default('legacy_unclassified');
            $table->string('size_rule_version')->nullable();
        });
        // History snapshot only. Do not change any existing size, age, weight or score.
        DB::table('pets')->whereNotNull('physical_size')->update(['legacy_physical_size' => DB::raw('physical_size')]);
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->dropColumn(['legacy_physical_size', 'size_classification_status', 'size_rule_version']);
        });
    }
};
