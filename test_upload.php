<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Pet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

$user = User::where('email', 'adopter@example.com')->first();
Auth::login($user);

$pet = Pet::first();

// Create a fake file
$file = UploadedFile::fake()->create('document.pdf', 100);

$request = \Illuminate\Http\Request::create('/applications', 'POST', [
    'pet_id' => $pet->id,
    'motivation_statement' => 'I love this dog so much!',
    'housing_type' => 'House',
    'income_range' => '$30k - $50k',
], [], [
    'document' => $file
]);

// Handle via Kernel
$response = $kernel->handle($request);
echo "Status Code: " . $response->getStatusCode() . "\n";
if ($response->isRedirection()) {
    echo "Redirect: " . $response->headers->get('Location') . "\n";
    if (session()->has('errors')) {
        echo "Errors: " . json_encode(session('errors')->toArray(), JSON_PRETTY_PRINT) . "\n";
    }
    if (session()->has('success')) {
        echo "Success: " . session('success') . "\n";
    }
} else {
    echo $response->getContent();
}
