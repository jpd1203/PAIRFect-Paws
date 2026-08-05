<?php

use App\Http\Controllers\Admin\AdopterProfileController;
use App\Http\Controllers\Admin\AnimalController as AdminAnimalController;
use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\AssessmentController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CompatibilityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FlaggedCasesController as AdminFlaggedCasesController;
use App\Http\Controllers\Admin\ManageFundsController;
use App\Http\Controllers\Admin\MonitoringController as AdminMonitoringController;
use App\Http\Controllers\Admin\VolunteerController;
use App\Http\Controllers\Auth\StaffLoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {

    // ---- Staff auth (separate from adopter login) ----
    Route::middleware('guest')->group(function () {
        Route::get('/login', [StaffLoginController::class, 'create'])->name('login');
        Route::post('/login', [StaffLoginController::class, 'store'])
            ->middleware('throttle:login')->name('login.store');
    });
    Route::post('/logout', [StaffLoginController::class, 'destroy'])
        ->middleware(['auth', 'staff'])->name('logout');

    Route::middleware(['auth', 'staff'])->group(function () {

        Route::get('/', DashboardController::class)->name('dashboard');

        // ---- Animal Records ----
        Route::get('/animals', [AdminAnimalController::class, 'index'])->name('animals.index');
        Route::post('/animals', [AdminAnimalController::class, 'store'])->name('animals.store');
        Route::post('/animals/{animal}', [AdminAnimalController::class, 'update'])->name('animals.update');
        Route::post('/animals/{animal}/delete', [AdminAnimalController::class, 'destroy'])->name('animals.destroy');

        // ---- Pet Assessment ----
        Route::get('/assessments', [AssessmentController::class, 'record'])->name('assessments.record');
        Route::get('/animals/{animal}/assess', [AssessmentController::class, 'create'])->name('assessments.create');
        Route::post('/animals/{animal}/assess', [AssessmentController::class, 'store'])->name('assessments.store');
        Route::get('/animals/{animal}/assessment-summary', [AssessmentController::class, 'summary'])->name('assessments.summary');

        // ---- Applications ----
        Route::get('/applications', [AdminApplicationController::class, 'index'])->name('applications.index');
        Route::post('/applications/schedule-interview', [AdminApplicationController::class, 'scheduleInterview'])->name('applications.schedule');
        Route::post('/applications/{application}/notes', [AdminApplicationController::class, 'saveNotes'])->name('applications.notes');
        Route::post('/applications/{application}/decision', [AdminApplicationController::class, 'decide'])->name('applications.decide');
        Route::get('/applications/{application}/history', [AdminApplicationController::class, 'history'])->name('applications.history');
        Route::get('/applications/document/{application}', [AdminApplicationController::class, 'document'])->name('applications.document');

        // ---- Compatibility ----
        Route::get('/compatibility', [CompatibilityController::class, 'index'])->name('compatibility.index');
        Route::get('/compatibility/{application}/breakdown', [CompatibilityController::class, 'breakdown'])->name('compatibility.breakdown');

        // ---- Adopter Profiles ----
        Route::get('/adopter-profiles', [AdopterProfileController::class, 'index'])->name('adopter-profiles.index');
        Route::get('/adopter-profiles/{application}', [AdopterProfileController::class, 'show'])->name('adopter-profiles.show');
        Route::get('/adopter-profiles/export', [AdopterProfileController::class, 'export'])->name('adopter-profiles.export');
        Route::get('/adopter-profiles/document/{application}', [AdopterProfileController::class, 'document'])->name('adopter-profiles.document');

        // ---- Post-Adoption Monitoring ----
        Route::get('/monitoring', [AdminMonitoringController::class, 'index'])->name('monitoring.index');
        Route::post('/monitoring/{checkIn}/reminder', [AdminMonitoringController::class, 'sendReminder'])->name('monitoring.reminder');
        Route::post('/monitoring/{checkIn}/flag', [AdminMonitoringController::class, 'flagAndNotify'])->name('monitoring.flag');

        // ---- Flagged Cases ----
        Route::get('/flagged-cases', [AdminFlaggedCasesController::class, 'index'])->name('flagged-cases.index');
        Route::post('/flagged-cases/{flaggedCase}/intervention', [AdminFlaggedCasesController::class, 'markIntervention'])->name('flagged-cases.intervention');
        Route::post('/flagged-cases/{flaggedCase}/escalate', [AdminFlaggedCasesController::class, 'escalate'])->name('flagged-cases.escalate');
        Route::post('/flagged-cases/{flaggedCase}/reminder', [AdminFlaggedCasesController::class, 'sendReminder'])->name('flagged-cases.reminder');
        Route::post('/flagged-cases/{flaggedCase}/resolve', [AdminFlaggedCasesController::class, 'resolve'])->name('flagged-cases.resolve');

        // ---- Volunteers ----
        Route::get('/volunteers', [VolunteerController::class, 'index'])->name('volunteers.index');
        Route::post('/volunteers', [VolunteerController::class, 'store'])->name('volunteers.store');
        Route::post('/volunteers/{volunteer}', [VolunteerController::class, 'update'])->name('volunteers.update');

        // ---- Audit Logs ----
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');

        // ---- Manage Funds ----
        Route::get('/funds', [ManageFundsController::class, 'index'])->name('funds.index');
        Route::post('/funds', [ManageFundsController::class, 'store'])->name('funds.store');
    });
});
