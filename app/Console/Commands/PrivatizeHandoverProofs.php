<?php

namespace App\Console\Commands;

use App\Models\Handover;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeHandoverProofs extends Command
{
    protected $signature = 'handover:privatize-proofs {--execute : Copy verified proofs to private storage and remove their public copies}';

    protected $description = 'Safely move legacy public handover release proofs to private storage';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $execute = (bool) $this->option('execute');
        $moved = 0;
        $skipped = 0;
        $referenced = [];

        foreach (Handover::query()->whereNotNull('proof_url')->orderBy('id')->lazyById() as $handover) {
            $source = $handover->legacyPublicProofPath();
            if ($source === null) {
                $skipped++;

                continue;
            }
            $referenced[$source] = true;
            $destination = $handover->proof_path ?: 'handover-proofs/legacy-'.basename($source);

            if (! $public->exists($source) && ! $private->exists($destination)) {
                $skipped++;

                continue;
            }
            if (! $execute) {
                $moved++;

                continue;
            }

            if ($public->exists($source)) {
                $bytes = $public->get($source);
                if ($bytes === null || ($private->exists($destination) && hash('sha256', $private->get($destination)) !== hash('sha256', $bytes))) {
                    $skipped++;

                    continue;
                }
                if (! $private->exists($destination) && ! $private->put($destination, $bytes)) {
                    $skipped++;

                    continue;
                }
                if (hash('sha256', $private->get($destination)) !== hash('sha256', $bytes)) {
                    $skipped++;

                    continue;
                }
            }

            if ($handover->proof_path !== $destination) {
                $handover->update(['proof_path' => $destination]);
            }
            if ($public->exists($source) && ! $public->delete($source)) {
                $skipped++;

                continue;
            }
            if ($public->exists($source)) {
                $skipped++;

                continue;
            }

            $handover->update(['proof_url' => null]);
            $moved++;
        }

        $orphans = count(array_diff($public->allFiles('proofs'), array_keys($referenced)));
        $this->line(($execute ? 'Migrated' : 'Eligible').": {$moved}; skipped: {$skipped}; unreferenced public proof files: {$orphans}.");
        if ($skipped || $orphans) {
            $this->warn('Review skipped records and unreferenced files manually; they were not deleted.');
        }
        if (! $execute) {
            $this->comment('Dry run only. Run again with --execute after reviewing the counts.');
        }

        return ($skipped || $orphans) ? self::FAILURE : self::SUCCESS;
    }
}
