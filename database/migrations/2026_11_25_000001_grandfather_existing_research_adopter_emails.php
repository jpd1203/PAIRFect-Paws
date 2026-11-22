<?php

use App\Enums\Role;
use App\Support\ResearchMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $connection = DB::connection();
        $isResearchDatabase = ($connection->getDriverName() === 'mysql'
            && str_starts_with((string) $connection->getDatabaseName(), 'pairfect_research_'))
            || ResearchMode::ready();

        if (! $isResearchDatabase) {
            return;
        }

        DB::table('users')
            ->where('role', Role::Adopter->value)
            ->where('is_active', true)
            ->whereNull('email_verified_at')
            ->update([
                'email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    public function down(): void
    {
        // Intentionally a no-op so verified accounts are never unverified on rollback.
    }
};
