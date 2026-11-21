<?php

use App\Models\Pet;
use App\Models\ResearchPlacement;
use App\Models\User;
use App\Services\ReservationQueueService;
use App\Support\ResearchMode;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['research.enabled' => true]);
if (! $app->environment('testing') || DB::getDriverName() !== 'mysql' || DB::connection()->getConfig('host') !== '127.0.0.1'
    || DB::getDatabaseName() !== env('PAIRFECT_DISPOSABLE_MYSQL_DB') || ! ResearchMode::ready()) {
    throw new RuntimeException('Research race process requires disposable local MySQL.');
}
[$script, $action, $actor, $target, $ready] = $argv;
$user = User::findOrFail($actor);
$queue = app(ReservationQueueService::class);
touch($ready);
try {
    match ($action) {
        'apply' => $queue->submitResearch($user, Pet::findOrFail($target)),
        'continue' => $queue->approveResearch($user, ResearchPlacement::findOrFail($target)),
    };
    echo 'ok';
} catch (HttpExceptionInterface $e) {
    echo (string) $e->getStatusCode();
}
