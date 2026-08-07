<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Serve built assets / uploaded files directly if they exist as static files
if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_NAME'] !== '/index.php') {
    return false;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
