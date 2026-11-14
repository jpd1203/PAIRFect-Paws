<?php

/** Independent HTTP-kernel process for disposable-MySQL atomic release tests only. */
use App\Models\HandoverEmailOutbox;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (! $app->environment('testing') || DB::getDriverName() !== 'mysql'
    || ! env('PAIRFECT_DISPOSABLE_MYSQL_DB') || DB::getDatabaseName() !== env('PAIRFECT_DISPOSABLE_MYSQL_DB')
    || config('database.connections.mysql.host') !== '127.0.0.1') {
    throw new RuntimeException('Independent release process requires the explicitly disposable test database.');
}
$input = json_decode(base64_decode($argv[1], true), true, flags: JSON_THROW_ON_ERROR);
config(['queue.default' => 'database', 'mail.default' => 'array', 'session.driver' => 'array', 'app.debug' => false]);
Carbon::setTestNow(CarbonImmutable::parse($input['clock']));
CarbonImmutable::setTestNow(CarbonImmutable::parse($input['clock']));
DB::statement('SET SESSION innodb_lock_wait_timeout = 25');
$request = Request::create('/admin/handover/'.$input['handover_id'].'/mark-released', 'POST', $input['data'], server: ['HTTP_ACCEPT' => 'application/json']);
$app->instance('request', $request);
Auth::guard()->setUser(User::findOrFail($input['staff_id']));
if ($directory = $input['pause_directory'] ?? null) {
    Event::listen('eloquent.creating: '.HandoverEmailOutbox::class, function () use ($directory, $input): void {
        touch($directory.'/ready');
        $deadline = microtime(true) + 25;
        while (! is_file($directory.'/continue')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Timed out waiting for the disposable test barrier.');
            }
            usleep(20_000);
        }
        if ($input['fail_outbox']) {
            DB::statement('INSERT INTO phase10_missing_fault_table (id) VALUES (1)');
        }
    });
}
if ($started = $input['started_file'] ?? null) {
    touch($started);
}
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
echo json_encode(['status' => $response->getStatusCode(), 'transaction_level' => DB::transactionLevel(), 'location' => $response->headers->get('Location'), 'errors' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR).PHP_EOL;
