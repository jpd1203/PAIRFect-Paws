<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AnimalController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FlaggedCasesController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    // Throttled: 5 attempts / minute per IP+email to slow credential stuffing
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated adopter routes
|--------------------------------------------------------------------------
| 'auth' -> must be logged in. 'verified' can be added once email
| verification is enabled. CSRF protection is applied globally by
| Laravel's VerifyCsrfToken middleware (already in the web group).
*/
Route::middleware(['auth'])->group(function () {

    Route::get('/', [AnimalController::class, 'index'])->name('home');

    // ---- Browse Pets ----
    Route::get('/pets', [AnimalController::class, 'index'])->name('animal.index');
    Route::get('/pets/{pet}/modal', [AnimalController::class, 'show'])->name('animal.show');

    // ---- Pet Recommendation ----
    Route::get('/recommendation', [RecommendationController::class, 'intake'])->name('recommendation.intake');
    Route::post('/recommendation', [RecommendationController::class, 'startMatching'])->name('recommendation.start');
    Route::get('/recommendation/results', [RecommendationController::class, 'results'])->name('recommendation.results');
    Route::post('/recommendation/recompute', [RecommendationController::class, 'recompute'])->name('recommendation.recompute');

    // ---- My Application ----
    Route::get('/application', [ApplicationController::class, 'index'])->name('application.index');
    Route::get('/application/apply/{pet}', [ApplicationController::class, 'apply'])->name('application.apply');
    Route::post('/application/submit', [ApplicationController::class, 'submit'])
        ->middleware('throttle:6,1')
        ->name('application.submit');

    // ---- My Check-ins (post-adoption monitoring) ----
    Route::get('/post-adoption/check-ins', [MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/post-adoption/reports/{report}/modal', [MonitoringController::class, 'showReport'])->name('monitoring.report.show');

    // ---- Flagged Cases module: submit report, overdue notice, flagged notice ----
    Route::get('/post-adoption/submit-report', [FlaggedCasesController::class, 'submitReport'])->name('flagged.submitReport');
    Route::post('/post-adoption/submit-report/preview', [FlaggedCasesController::class, 'previewReport'])
        ->middleware('throttle:report-submit')
        ->name('flagged.previewReport');
    Route::post('/post-adoption/submit-report/confirm', [FlaggedCasesController::class, 'confirmSubmit'])
        ->middleware('throttle:report-submit')
        ->name('flagged.confirmSubmit');
    Route::get('/post-adoption/overdue-notice', [FlaggedCasesController::class, 'overdueNotice'])->name('flagged.overdueNotice');
    Route::get('/post-adoption/flagged-notice', [FlaggedCasesController::class, 'flaggedNotice'])->name('flagged.flaggedNotice');

    // ---- Account / profile settings ----
    Route::get('/account/settings', [AccountController::class, 'settings'])->name('account.settings');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
});
