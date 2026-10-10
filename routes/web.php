<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HandoverConfirmationController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\TimeTravelController;
use App\Http\Middleware\EnsureVerificationLinkMatchesUser;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── Public Routes ────────────────────────────────────────────────────────────

$landingPage = function () {
    $featuredPets = Pet::fullyAssessed()->where('availability_status', 'Available')
        ->where('is_archived', false)
        ->latest('id')
        ->take(12)
        ->get();

    $availablePetsCount = Pet::fullyAssessed()->where('availability_status', 'Available')
        ->where('is_archived', false)
        ->count();

    return view('landing', compact('featuredPets', 'availablePetsCount'));
};

Route::get('/', $landingPage)->name('landing');
Route::get('/home', $landingPage)->name('home');

Route::view('/donate', 'donate')->name('donate');
Route::view('/terms-and-conditions', 'legal.terms')->name('legal.terms');
Route::view('/privacy-notice', 'legal.privacy')->name('legal.privacy');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');
Route::get('/access-denied', [AuthController::class, 'accessDenied'])->name('access-denied');

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

// The signed link identifies the account, so it must remain usable after the
// recipient has logged out or opens the email in a different browser.
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', EnsureVerificationLinkMatchesUser::class, 'throttle:6,1'])
    ->name('verification.verify');

Route::prefix('locations')->name('locations.')->group(function () {
    Route::get('/regions', [LocationController::class, 'regions'])->name('regions');
    Route::get('/provinces', [LocationController::class, 'provinces'])->name('provinces');
    Route::get('/cities-municipalities', [LocationController::class, 'localities'])->name('localities');
    Route::get('/barangays', [LocationController::class, 'barangays'])->name('barangays');
});

// Public pet catalog
Route::get('/pets', [PetController::class, 'index'])->name('pets.index');
Route::get('/pets/{pet}/modal', [PetController::class, 'modal'])->name('pets.modal');
Route::get('/pets/{pet}', [PetController::class, 'show'])->name('pets.show');

// Signed-in adopters may explore recommendations before email verification.
// Formal application routes below remain protected by the verified middleware.
Route::middleware(['auth', 'adopter'])->group(function () {
    Route::get('/onboarding/assessment', [RecommendationController::class, 'onboarding'])->name('recommendation.onboarding');
    Route::post('/onboarding/assessment/skip', [RecommendationController::class, 'skipOnboarding'])->name('recommendation.onboarding.skip');
});

Route::get('/recommendation', [RecommendationController::class, 'intake'])->name('recommendation.intake');
Route::post('/recommendation/start', [RecommendationController::class, 'start'])->name('recommendation.start');
Route::get('/recommendation/results', [RecommendationController::class, 'results'])->name('recommendation.results');
Route::post('/recommendation/recompute', [RecommendationController::class, 'recompute'])->name('recommendation.recompute');

// ─── Authenticated Routes ─────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::middleware(['admin', 'verified'])->group(function () {
        Route::get('/timetravel', [TimeTravelController::class, 'index'])->name('time-travel.index');
        Route::post('/timetravel', [TimeTravelController::class, 'store'])->name('time-travel.store');
        Route::delete('/timetravel', [TimeTravelController::class, 'destroy'])->name('time-travel.destroy');
    });

    // Account settings
    Route::get('/account/settings', [AccountController::class, 'show'])->name('account.settings');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])
        ->middleware('verified')
        ->name('account.profile.update');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])
        ->middleware('verified')
        ->name('account.password.update');

    Route::get('/admin/handover/{handover}/release-proof', [Admin\HandoverController::class, 'releaseProof'])
        ->name('admin.handover.release-proof');

    // ─── Adopter Routes ───────────────────────────────────────────────────────

    Route::middleware('adopter')->group(function () {

        // Browse pets (adopter dashboard sidebar)
        Route::get('/animal', function (Request $request) {
            $query = Pet::fullyAssessed()->whereIn('availability_status', ['Available', 'Soft-Reserved']);

            if ($request->filled('species') && $request->species !== 'All Species') {
                $query->where('species', $request->species);
            }

            if ($request->filled('age') && $request->age !== 'All Ages' && $request->age !== 'all') {
                $age = $request->age;
                if ($age === 'Young') {
                    $query->where('age', '<=', 24);
                } elseif ($age === 'Adult') {
                    $query->whereBetween('age', [25, 84]);
                } elseif ($age === 'Senior') {
                    $query->where('age', '>=', 85);
                }
            }

            $pets = $query->latest()->get();

            if ($request->ajax()) {
                return view('animal._pet-grid', compact('pets'));
            }

            return view('animal.index', [
                'pets' => $pets,
                'speciesFilter' => $request->species ?? 'All Species',
                'ageFilter' => $request->age ?? 'All Ages',
            ]);
        })->name('animal.index');

        Route::middleware('verified')->group(function () {
            // My Application
            Route::get('/application', [ApplicationController::class, 'index'])->name('application.index');
            Route::post('/applications/{application}/reschedule', [ApplicationController::class, 'requestReschedule'])
                ->name('applications.reschedule.request');

            // Apply for adoption
            Route::get('/apply/{pet}', [ApplicationController::class, 'create'])->name('application.apply');
            Route::post('/application/submit', [ApplicationController::class, 'store'])->name('application.submit');
            Route::post('/applications/{application}/document', [ApplicationController::class, 'replaceDocument'])->name('applications.document.replace');

            // Legacy application routes
            Route::get('/applications/create/{pet}', [ApplicationController::class, 'create'])->name('applications.create');
            Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
            Route::get('/applications/mine', [ApplicationController::class, 'mine'])->name('applications.mine');

            // Post-Adoption Monitoring
            Route::get('/monitoring', [MonitoringController::class, 'myCheckins'])->name('monitoring.index');
            Route::get('/monitoring/my-checkins', [MonitoringController::class, 'myCheckins'])->name('monitoring.my-checkins');
            Route::get('/monitoring/submit-report', [MonitoringController::class, 'reportDue'])->name('monitoring.submit-report');
            Route::get('/monitoring/overdue-notice', [MonitoringController::class, 'overdueNotice'])->name('monitoring.overdue-notice');
            Route::get('/monitoring/flagged-notice', [MonitoringController::class, 'flaggedNotice'])->name('monitoring.flagged-notice');
            Route::get('/monitoring/reports/{log}/modal', [MonitoringController::class, 'showModal'])->name('monitoring.modal');
            Route::get('/monitoring/reports/{log}/create', [MonitoringController::class, 'createReport'])->name('monitoring.create');
            Route::post('/monitoring/reports/{log}/capture-challenge', [MonitoringController::class, 'issueCaptureChallenge'])
                ->middleware('throttle:10,1')
                ->name('monitoring.capture-challenge');
            Route::post('/monitoring/reports/{log}', [MonitoringController::class, 'submitReport'])
                ->middleware('throttle:3,1')
                ->name('monitoring.submit');
            Route::post('/monitoring/reports/{log}/follow-up', [MonitoringController::class, 'submitFollowUp'])
                ->name('monitoring.follow-up');
        });

    });

    // ─── Staff Routes (Volunteer + Admin) ────────────────────────────────────

    Route::middleware(['staff', 'verified'])->prefix('admin')->name('admin.')->group(function () {

        // Dashboard
        Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Admin logout (sidebar uses admin.logout)
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Animal management
        Route::get('/animals', [Admin\AnimalController::class, 'index'])->name('animals.index');
        Route::post('/animals', [PetController::class, 'store'])->name('animals.store');
        Route::put('/animals/{pet}', [PetController::class, 'update'])->name('animals.update');
        Route::post('/animals/{pet}/archive', [PetController::class, 'archive'])->name('animals.archive');
        Route::post('/animals/{pet}/restore', [PetController::class, 'restore'])->name('animals.restore');

        // Pet CRUD (staff-only part)
        Route::get('/pets/create', [PetController::class, 'create'])->name('pets.create');
        Route::post('/pets', [PetController::class, 'store'])->name('pets.store');
        Route::get('/pets/{pet}/edit', [PetController::class, 'edit'])->name('pets.edit');
        Route::put('/pets/{pet}', [PetController::class, 'update'])->name('pets.update');

        // Behavioral assessments
        Route::get('/assessments/record', [Admin\AssessmentController::class, 'index'])->name('assessments.record');
        Route::get('/animals/{pet}/assessment-summary', [Admin\AssessmentController::class, 'summary'])->name('assessments.summary');
        Route::get('/assessments/create/{pet}', [Admin\AssessmentController::class, 'create'])->name('assessments.create');
        Route::post('/assessments/{pet}', [Admin\AssessmentController::class, 'store'])->name('assessments.store');

        // Compatibility
        Route::get('/compatibility', [Admin\CompatibilityController::class, 'index'])->name('compatibility.index');
        Route::get('/pets/{pet}/ranked-applicants', [Admin\CompatibilityController::class, 'forPet'])->name('pets.ranked-applicants');

        // Applications queue
        Route::get('/applications', [Admin\ApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/report.csv', [Admin\AdoptionApplicationsReportController::class, 'export'])->name('applications.export');
        Route::post('/applications/{application}/interview', [Admin\ApplicationController::class, 'scheduleInterview'])->name('applications.interview');
        Route::post('/applications/{application}/reschedule/decline', [Admin\ApplicationController::class, 'declineReschedule'])
            ->name('applications.reschedule.decline');
        Route::post('/applications/{application}/notes', [Admin\ApplicationController::class, 'saveNotes'])->name('applications.notes');
        Route::post('/applications/{application}/queue-outcome', [Admin\ApplicationController::class, 'queueOutcome'])->name('applications.queue-outcome');
        Route::get('/applications/{application}/document', [Admin\AdoptionProfileController::class, 'document'])->name('applications.document');
        Route::get('/applications/{application}/document-verification', [Admin\AdoptionProfileController::class, 'verification'])->name('applications.document-verification');
        Route::post('/applications/{application}/document-ocr-retry', [Admin\ApplicationController::class, 'retryDocumentOcr'])->name('applications.document-ocr-retry');
        Route::post('/applications/{application}/document-decision', [Admin\ApplicationController::class, 'documentDecision'])->name('applications.document-decision');
        
        Route::post('/applications/schedule', [Admin\ApplicationController::class, 'scheduleSelectedInterview'])->name('applications.schedule');

        // Adoption Profiles
        Route::get('/adopter-profiles', [Admin\AdoptionProfileController::class, 'index'])->name('adopter-profiles.index');
        Route::get('/adopter-profiles/{user}/history', [Admin\AdoptionProfileController::class, 'history'])->name('adopter-profiles.history');
        Route::get('/adopter-profiles/{application}/document', [Admin\AdoptionProfileController::class, 'document'])->name('adopter-profiles.document');
        // Legacy name used by old views
        Route::get('/adoption-profiles', [Admin\AdoptionProfileController::class, 'index'])->name('adoption-profiles.index');
        Route::get('/adoption-profiles/{user}/history', [Admin\AdoptionProfileController::class, 'history'])->name('adoption-profiles.history');
        Route::get('/adoption-profiles/{application}/document', [Admin\AdoptionProfileController::class, 'document'])->name('adoption-profiles.document');

        // Handover & Release
        Route::get('/applications/{application}/identity-verification', [Admin\IdentityVerificationController::class, 'show'])->name('applications.identity');
        Route::post('/applications/{application}/identity-verification', [Admin\IdentityVerificationController::class, 'store'])->name('applications.identity.store');
        Route::post('/handover/{handover}/schedule', [\App\Http\Controllers\HandoverScheduleController::class, 'propose'])->name('handover.schedule');
        Route::post('/handover/{handover}/reschedule/review', [\App\Http\Controllers\HandoverScheduleController::class, 'review'])->name('handover.reschedule.review');
        Route::get('/handover', [Admin\HandoverController::class, 'index'])->name('handover.index');
        Route::get('/handover/{handover}', [Admin\HandoverController::class, 'show'])->name('handover.show');
        Route::post('/handover/{handover}/mark-released', [Admin\HandoverController::class, 'markReleased'])->name('handover.release');
        Route::post('/handover/{handover}/reminder', [Admin\HandoverController::class, 'sendReminder'])->name('handover.reminder');
        Route::post('/handover/{handover}/reopen', [Admin\HandoverController::class, 'reopen'])->name('handover.reopen');

        // Monitoring
        Route::get('/monitoring', [Admin\MonitoringController::class, 'index'])->name('monitoring.index');
        Route::get('/monitoring/flagged', [Admin\MonitoringController::class, 'flagged'])->name('monitoring.flagged');
        Route::get('/monitoring/{log}/photo', [Admin\MonitoringController::class, 'photo'])->name('monitoring.photo');
        Route::get('/monitoring/{log}/video', [Admin\MonitoringController::class, 'video'])->name('monitoring.video');
        Route::post('/monitoring/{log}/reminder', [Admin\MonitoringController::class, 'sendReminder'])->name('monitoring.reminder');
        Route::post('/monitoring/{log}/flag', [Admin\MonitoringController::class, 'flag'])->name('monitoring.flag');

        // Audit Logs
        Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])->middleware('admin')->name('audit-logs.index');
        Route::get('/audit-logs/export', [Admin\AuditLogController::class, 'export'])->middleware('admin')->name('audit-logs.export');

        // ─── Admin-only routes ────────────────────────────────────────────────
        Route::middleware('admin')->group(function () {
            Route::get('/post-adoption-demo', [Admin\PostAdoptionWebDemoController::class, 'index'])->name('post-adoption-demo.index');
            Route::post('/post-adoption-demo', [Admin\PostAdoptionWebDemoController::class, 'activate'])->middleware('throttle:12,1')->name('post-adoption-demo.activate');
            Route::post('/post-adoption-demo/logs/{log}/reminder', [Admin\PostAdoptionWebDemoController::class, 'reminder'])->middleware('throttle:10,1')->name('post-adoption-demo.reminder');
            Route::post('/post-adoption-demo/logs/{log}/resolve', [Admin\PostAdoptionWebDemoController::class, 'resolve'])->name('post-adoption-demo.resolve');
            Route::delete('/post-adoption-demo/{application}', [Admin\PostAdoptionWebDemoController::class, 'reset'])->name('post-adoption-demo.reset');
            Route::post('/applications/{application}/decision', [Admin\ApplicationController::class, 'decision'])->name('applications.decide');
            Route::post('/applications/{application}/override', [Admin\ApplicationController::class, 'overridePrimary'])->name('applications.override');
            Route::post('/monitoring/flagged/{log}/resolve', [Admin\MonitoringController::class, 'resolve'])->name('monitoring.resolve');

            // Archive pet
            Route::post('/pets/{pet}/archive', [PetController::class, 'archive'])->name('pets.archive');

            // Volunteer & staff management
            Route::get('/volunteers', [Admin\VolunteerController::class, 'index'])->name('volunteers.index');
            Route::get('/volunteers/create', [Admin\VolunteerController::class, 'create'])->name('volunteers.create');
            Route::post('/volunteers', [Admin\VolunteerController::class, 'store'])->name('volunteers.store');
            Route::match(['post', 'patch'], '/volunteers/{user}', [Admin\VolunteerController::class, 'update'])->name('volunteers.update');
        });
    });
});

// ─── Handover & Adopter Confirmation Link Routes ──────────────────────────────
Route::middleware(['auth', 'adopter', 'verified'])->group(function () {
    Route::post('/adopter/{handover}/schedule/confirm', [\App\Http\Controllers\HandoverScheduleController::class, 'confirm'])->name('adopter.handover.schedule.confirm');
    Route::post('/adopter/{handover}/reschedule', [\App\Http\Controllers\HandoverScheduleController::class, 'requestReschedule'])->name('adopter.handover.reschedule');
    Route::get('/adopter', [HandoverConfirmationController::class, 'userHandoverRedirect'])->name('adopter.handover.my');
    Route::get('/confirm/{handover}', [HandoverConfirmationController::class, 'confirmView'])->name('adopter.confirm');
    Route::post('/confirm/{handover}', [HandoverConfirmationController::class, 'submitConfirmation'])->name('adopter.confirm.submit');
    Route::get('/adopter/{handover}', [HandoverConfirmationController::class, 'statusView'])->name('adopter.handover.status');
    Route::get('/adopter/{handover}/notifications', [HandoverConfirmationController::class, 'notificationsView'])->name('adopter.handover.notifications');
    Route::post('/adopter/notifications/{notification}/read', [HandoverConfirmationController::class, 'markRead'])->name('adopter.handover.notification.read');
    Route::post('/adopter/{handover}/notifications/read-all', [HandoverConfirmationController::class, 'markAllRead'])->name('adopter.handover.notifications.read-all');
});

Route::get('/handover/{handover}/receipt-proof', [HandoverConfirmationController::class, 'receiptProof'])
    ->middleware(['auth', 'verified'])
    ->name('handover.receipt-proof');

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [\App\Http\Controllers\InAppNotificationController::class, 'index'])->name('index');
    Route::get('/{notification}/open', [\App\Http\Controllers\InAppNotificationController::class, 'open'])->name('open');
    Route::post('/{notification}/read', [\App\Http\Controllers\InAppNotificationController::class, 'markRead'])->name('read');
    Route::post('/read-all', [\App\Http\Controllers\InAppNotificationController::class, 'markAllRead'])->name('read-all');
});
