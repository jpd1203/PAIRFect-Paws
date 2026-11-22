<?php

namespace App\Support;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ResearchMode
{
    public static function ready(): bool
    {
        if (config('research.enabled') !== true) {
            return false;
        }
        $db = DB::connection();

        return ($db->getDriverName() === 'mysql' && str_starts_with($db->getDatabaseName(), 'pairfect_research_'))
            || (app()->environment('testing') && $db->getDriverName() === 'mysql'
                && $db->getDatabaseName() === 'pairfect_phase15c_ci'
                && $db->getConfig('host') === '127.0.0.1'
                && config('research.disposable_database') === $db->getDatabaseName())
            || (app()->environment('testing') && $db->getDriverName() === 'sqlite' && $db->getDatabaseName() === ':memory:');
    }

    public const MILESTONES = ['3-day', '3-week', '3-month'];

    public static function allowsAdopter(?User $user): bool
    {
        return $user?->isAdopter() && $user->is_active && self::ready();
    }

    public static function catalogPet(Pet $pet): bool
    {
        return self::ready() && DB::table('research_catalog')->where('pet_id', $pet->id)->exists();
    }
}
