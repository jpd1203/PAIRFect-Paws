<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$log = App\Models\PostAdoptionLog::first();
echo 'Flagged: ' . ($log->flagged_for_review ? 'YES' : 'NO') . "\n";
