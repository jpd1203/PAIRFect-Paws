<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlaggedCaseController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PetController;
use Illuminate\Support\Facades\Route;

// ─── Public Routes ────────────────────────────────────────────────────────────

Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',   [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register',[AuthController::class, 'register']);
Route::get('/access-denied', [AuthController::class, 'accessDenied'])->name('access-denied');

// Public pet catalog
Route::get('/pets',          [PetController::class, 'index'])->name('pets.index');
Route::get('/pets/{pet}',    [PetController::class, 'show'])->name('pets.show');

// ─── Authenticated Routes ─────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // ─── Adopter Routes ───────────────────────────────────────────────────────

    Route::middleware('adopter')->group(function () {

        // Adoption Applications
        Route::get('/applications/create/{pet}', [ApplicationController::class, 'create'])->name('applications.create');
        Route::post('/applications',             [ApplicationController::class, 'store'])->name('applications.store');
        Route::get('/applications/mine',         [ApplicationController::class, 'mine'])->name('applications.mine');

        // Post-Adoption Monitoring
        Route::get('/monitoring/my-checkins',              [MonitoringController::class, 'myCheckins'])->name('monitoring.my-checkins');
        Route::get('/monitoring/reports/{log}/create',     [MonitoringController::class, 'createReport'])->name('monitoring.create');
        Route::post('/monitoring/reports/{log}',           [MonitoringController::class, 'submitReport'])->name('monitoring.submit');

        // Flagged case notices
        Route::get('/flagged-cases/overdue-notice',  [FlaggedCaseController::class, 'overdueNotice'])->name('flagged-cases.overdue-notice');
        Route::get('/flagged-cases/flagged-notice',  [FlaggedCaseController::class, 'flaggedNotice'])->name('flagged-cases.flagged-notice');
    });

    // ─── Staff Routes (Volunteer + Admin) ────────────────────────────────────

    Route::middleware('staff')->prefix('admin')->name('admin.')->group(function () {

        // Dashboard
        Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Animal management (list — create/edit/archive via PetController below)
        Route::get('/animals', [Admin\AnimalController::class, 'index'])->name('animals.index');

        // Pet CRUD (staff-only part)
        Route::get('/pets/create',       [PetController::class, 'create'])->name('pets.create');
        Route::post('/pets',             [PetController::class, 'store'])->name('pets.store');
        Route::get('/pets/{pet}/edit',   [PetController::class, 'edit'])->name('pets.edit');
        Route::put('/pets/{pet}',        [PetController::class, 'update'])->name('pets.update');

        // Applications queue
        Route::get('/applications',                                  [Admin\ApplicationController::class, 'index'])->name('applications.index');
        Route::post('/applications/{application}/interview',         [Admin\ApplicationController::class, 'scheduleInterview'])->name('applications.interview');
        Route::post('/applications/{application}/decision',          [Admin\ApplicationController::class, 'decision'])->name('applications.decision');

        // Application status from adopter-side queue (staff accessible)
        Route::patch('/applications/{application}/status',          [ApplicationController::class, 'updateStatus'])->name('applications.status');
        Route::post('/applications/{application}/interview-adopter', [ApplicationController::class, 'scheduleInterview'])->name('applications.interview-adopter');

        // Adoption Profiles
        Route::get('/adoption-profiles',                              [Admin\AdoptionProfileController::class, 'index'])->name('adoption-profiles.index');
        Route::get('/adoption-profiles/{application}/document',      [Admin\AdoptionProfileController::class, 'document'])->name('adoption-profiles.document');

        // Monitoring
        Route::get('/monitoring',                          [Admin\MonitoringController::class, 'index'])->name('monitoring.index');
        Route::get('/monitoring/flagged',                  [Admin\MonitoringController::class, 'flagged'])->name('monitoring.flagged');
        Route::post('/monitoring/flagged/{log}/resolve',   [Admin\MonitoringController::class, 'resolve'])->name('monitoring.resolve');

        // Audit Logs
        Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');

        // ─── Admin-only routes ────────────────────────────────────────────────

        Route::middleware('admin')->group(function () {
            // Archive pet
            Route::post('/pets/{pet}/archive', [PetController::class, 'archive'])->name('pets.archive');

            // Volunteer & staff management
            Route::get('/volunteers', [Admin\VolunteerController::class, 'index'])->name('volunteers.index');
            Route::get('/volunteers/create', [Admin\VolunteerController::class, 'create'])->name('volunteers.create');
            Route::post('/volunteers', [Admin\VolunteerController::class, 'store'])->name('volunteers.store');
        });
    });
});

// Redirect root to pet catalog
Route::get('/', fn() => redirect()->route('pets.index'));

