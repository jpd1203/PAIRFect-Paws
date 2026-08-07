<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$credentials = ['email' => 'admin@pairfectpaws.com', 'password' => 'password'];
echo "Auth::attempt result: " . (\Illuminate\Support\Facades\Auth::attempt($credentials) ? 'true' : 'false') . "\n";
