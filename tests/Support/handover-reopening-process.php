<?php

/** Independent, guarded disposable-MySQL HTTP process for reopening contention. */
use App\Models\HandoverEmailOutbox;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (! $app->environment('testing') || DB::getDriverName() !== 'mysql' || ! env('PAIRFECT_DISPOSABLE_MYSQL_DB')
    || DB::getDatabaseName() !== env('PAIRFECT_DISPOSABLE_MYSQL_DB') || config('database.connections.mysql.host') !== '127.0.0.1') {
    throw new RuntimeException('This process requires the explicitly disposable MySQL test database.');
}
$input = json_decode(base64_decode($argv[1], true), true, flags: JSON_THROW_ON_ERROR);
config(['app.debug' => false, 'mail.default' => 'array', 'queue.default' => 'database', 'session.driver' => 'array',
    'filesystems.handover_receipts_disk' => 'local', 'filesystems.disks.local.root' => $input['storage_root']]);
Carbon::setTestNow(CarbonImmutable::parse($input['clock']));
CarbonImmutable::setTestNow(CarbonImmutable::parse($input['clock']));
DB::statement('SET SESSION innodb_lock_wait_timeout = 25');
if ($input['action'] === 'reopen') {
    $request = Request::create('/admin/handover/'.$input['handover_id'].'/reopen', 'POST', ['reason' => 'Synthetic concurrent reopening'], server: ['HTTP_ACCEPT' => 'application/json']);
} elseif ($input['action'] === 'receipt') {
    $request = Request::create('/confirm/'.$input['handover_id'], 'POST', ['outcome' => 'received'],
        files: ['receipt_proof' => new UploadedFile($input['receipt_path'], 'receipt.jpg', 'image/jpeg', null, true)], server: ['HTTP_ACCEPT' => 'application/json']);
} else {
    throw new RuntimeException('Unsupported test action.');
}
$app->instance('request', $request);
Auth::guard()->setUser(User::findOrFail($input['user_id']));
if ($directory = $input['pause_directory'] ?? null) {
    Event::listen('eloquent.creating: '.HandoverEmailOutbox::class, function () use ($directory, $input): void {
        touch($directory.'/ready');
        $deadline = microtime(true) + 25;
        while (! is_file($directory.'/continue')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Disposable test barrier timeout.');
            }
            usleep(20_000);
        }
        if ($input['fail_outbox']) {
            DB::statement('INSERT INTO phase10b_missing_fault_table (id) VALUES (1)');
        }
    });
}
if ($started = $input['started_file'] ?? null) {
    touch($started);
}
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
echo json_encode(['status' => $response->getStatusCode(), 'transaction_level' => DB::transactionLevel()], JSON_THROW_ON_ERROR).PHP_EOL;
