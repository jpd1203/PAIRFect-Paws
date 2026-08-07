<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\CheckInMilestoneController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdopterProfileController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('roles');
    });
    Route::put('/user/profile', [App\Http\Controllers\ProfileController::class, 'update']);

    // Admin Routes
    Route::middleware('role:Admin')->group(function () {
        Route::post('/admin/create-admin', [App\Http\Controllers\AdminController::class, 'createAdmin']);
        Route::post('/admin/create-volunteer', [App\Http\Controllers\AdminController::class, 'createVolunteer']);
        Route::post('/admin/users/{user}/toggle', [App\Http\Controllers\AdminController::class, 'toggleUserStatus']);
        Route::get('/admin/dashboard', [App\Http\Controllers\DashboardController::class, 'adminDashboard']);
    });
    
    // Volunteer Routes
    Route::middleware('role:Volunteer')->group(function () {
        Route::get('/volunteer/dashboard', [App\Http\Controllers\DashboardController::class, 'volunteerDashboard']);
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
    Route::apiResource('donations', DonationController::class);
    Route::apiResource('adopter-profiles', AdopterProfileController::class);
    Route::apiResource('check-ins', CheckInMilestoneController::class);
});

// Public pet routes
Route::get('/pets', [PetController::class, 'index']);
Route::get('/pets/{pet}', [PetController::class, 'show']);
