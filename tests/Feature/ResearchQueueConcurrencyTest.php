<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\ResearchPlacement;
use App\Support\ResearchMode;
use Database\Seeders\ResearchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

final class ResearchQueueConcurrencyTest extends TestCase
{
    use BuildsMatchingFixtures;

    public function test_racing_submissions_and_completion_preserve_single_winner(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires independent connections and MySQL row locks.');
        }
        config(['research.enabled' => true]);
        $this->assertTrue(ResearchMode::ready());
        $this->assertSame('127.0.0.1', DB::connection()->getConfig('host'));
        $this->assertNotEmpty(env('PAIRFECT_DISPOSABLE_MYSQL_DB'));
        $this->assertSame(DB::getDatabaseName(), env('PAIRFECT_DISPOSABLE_MYSQL_DB'));
        // Never run inherited down migrations as test cleanup. The next
        // RefreshDatabase test must reinstall its own clean disposable schema.
        RefreshDatabaseState::$migrated = false;
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->seed(ResearchCatalogSeeder::class);
        $pet = Pet::firstOrFail();
        $a = $this->matchingUser();
        $b = $this->matchingUser();
        $this->completeMatchingProfile($a);
        $this->completeMatchingProfile($b);
        $this->assertSame(['ok', 'ok'], $this->race($pet, 'apply', [[$a->id, $pet->id], [$b->id, $pet->id]]));
        $primary = ResearchPlacement::where('status', 'PrimaryCandidate')->sole();
        $waitlisted = ResearchPlacement::where('status', 'Waitlisted')->sole();
        $this->assertSame('Soft-Reserved', $pet->fresh()->availability_status->value);
        $this->assertSame(['ok', '409', 'ok'], $this->race($pet, 'continue', [
            [$primary->user_id, $primary->id], [$waitlisted->user_id, $waitlisted->id], [$primary->user_id, $primary->id],
        ]));
        $this->assertSame(1, ResearchPlacement::where('status', 'Approved')->count());
        $this->assertSame(0, ResearchPlacement::whereNotNull('released_at')->count());
        $this->assertSame('Closed', $waitlisted->fresh()->status->value);
        $this->assertSame('Adopted', $pet->fresh()->availability_status->value);
    }

    private function race(Pet $pet, string $action, array $requests): array
    {
        $workers = [];
        DB::beginTransaction();
        try {
            Pet::whereKey($pet->id)->lockForUpdate()->firstOrFail();
            foreach ($requests as [$user, $target]) {
                $marker = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pairfect-race-'.Str::uuid();
                $process = new Process([PHP_BINARY, base_path('tests/Support/research-queue-process.php'), $action, $user, $target, $marker], base_path(), timeout: 25);
                $process->start();
                $workers[$marker] = $process;
            }
            $deadline = microtime(true) + 10;
            foreach ($workers as $marker => $process) {
                while (! is_file($marker) && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertFileExists($marker, $process->getErrorOutput());
                $this->assertTrue($process->isRunning());
            }
            // Prove each worker reached the InnoDB lock rather than merely starting its process.
            do {
                $waiting = DB::table('performance_schema.data_lock_waits')->distinct()->count('REQUESTING_THREAD_ID');
                if ($waiting < count($workers)) {
                    usleep(10000);
                }
            } while ($waiting < count($workers) && microtime(true) < $deadline);
            $this->assertSame(count($workers), $waiting);
            DB::commit();

            return array_values(array_map(function (Process $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput().$process->getOutput());

                return trim($process->getOutput());
            }, $workers));
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($workers as $marker => $process) {
                $process->stop();
                if (is_file($marker)) {
                    unlink($marker);
                }
            }
        }
    }
}
