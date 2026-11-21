<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ResearchMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class EnrollResearchParticipant extends Command
{
    protected $signature = 'research:enroll {user_id}';

    protected $description = 'Enroll a consenting, verified adopter in the isolated research database';

    public function handle(): int
    {
        if (! ResearchMode::ready()) {
            $this->error('Research environment not enabled or unsafe database name.');

            return self::FAILURE;
        }
        $id = filter_var($this->argument('user_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $user = $id ? User::find($id) : null;
        if (! $user?->isAdopter() || ! $user->is_active || ! $user->hasVerifiedEmail()) {
            $this->error('Requires an active, verified adopter.');

            return self::FAILURE;
        }
        DB::table('research_participants')->insertOrIgnore(['user_id' => $user->id, 'enrolled_at' => now()]);
        $this->info('Research enrollment active.');

        return self::SUCCESS;
    }
}
