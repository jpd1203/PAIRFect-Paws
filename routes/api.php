<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdopterProfileController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckInMilestoneController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkApi'])->middleware('throttle:5,1');
Route::post('/reset-password', [PasswordResetController::class, 'resetApi'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('roles');
    });
    Route::put('/user/profile', [ProfileController::class, 'update']);

    // Admin Routes
    Route::middleware('role:Admin')->group(function () {
        Route::post('/admin/create-admin', [AdminController::class, 'createAdmin']);
        Route::post('/admin/create-volunteer', [AdminController::class, 'createVolunteer']);
        Route::post('/admin/users/{user}/toggle', [AdminController::class, 'toggleUserStatus']);
        Route::get('/admin/dashboard', [DashboardController::class, 'adminDashboard']);
    });

    // Volunteer Routes
    Route::middleware('role:Volunteer')->group(function () {
        Route::get('/volunteer/dashboard', [DashboardController::class, 'volunteerDashboard']);
    });

    Route::apiResource('pets', PetController::class)->except(['index', 'show']);
    Route::apiResource('applications', ApplicationController::class);
    Route::middleware('role:Admin,Volunteer')->group(function () {
        Route::patch('/applications/{application}/status', [ApplicationController::class, 'updateStatus']);
        Route::post('/applications/{application}/schedule-interview', [ApplicationController::class, 'scheduleInterview']);
    });
    Route::middleware('role:Admin')->group(function () {
        Route::post('/applications/{application}/override', [ApplicationController::class, 'override']);
    });
    Route::apiResource('documents', DocumentController::class);
    Route::apiResource('adopter-profiles', AdopterProfileController::class);
    Route::apiResource('check-ins', CheckInMilestoneController::class);
});

// Public pet routes
Route::get('/pets', [PetController::class, 'index']);
Route::get('/pets/{pet}', [PetController::class, 'show']);
