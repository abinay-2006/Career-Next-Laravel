<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Employer\EmployerController;
use App\Http\Controllers\JobSeeker\JobSeekerController;
use App\Http\Controllers\Admin\ManageUsersController;
use App\Http\Controllers\Admin\ManageJobsController;

// Route

// Home Page Route
Route::get('/', [HomeController::class, 'index']);

// Login and Registration Routes
Route::get('/login', [LoginController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [LoginController::class, 'login']);

Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');

Route::post('/register', [RegisterController::class, 'register'])
    ->name('register');

Route::get('/forgot', function () {
    return 'Forgot Password Page - Coming Soon';
})->name('forgot');

// Admin Routes

Route::prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [AdminController::class, 'index'])
        ->name('index');

    Route::get('/dashboard', [AdminController::class, 'dashboard'])
        ->name('dashboard');

    Route::get('/manage-documents', [AdminController::class, 'manageDocuments'])
        ->name('manageDocuments');

    //Manage Users
    Route::get('/manage-users', [ManageUsersController::class, 'index'])
        ->name('manageUsers');

    Route::post(
        '/manage-users/{type}/{id}/confirm',
        [ManageUsersController::class, 'confirm']
    )->name('users.confirm');

    Route::post(
        '/manage-users/{type}/{id}/suspend',
        [ManageUsersController::class, 'suspend']
    )->name('users.suspend');

    Route::delete(
        '/manage-users/{type}/{id}',
        [ManageUsersController::class, 'destroy']
    )->name('users.delete');

    //Manage Jobs
    Route::get('/manage-jobs', [ManageJobsController::class, 'index'])
        ->name('manage.jobs');

    Route::post('/manage-jobs/{id}/approve', [ManageJobsController::class, 'approve'])
        ->name('jobs.approve');

    Route::post('/manage-jobs/{id}/reject', [ManageJobsController::class, 'reject'])
        ->name('jobs.reject');
});

Route::get('/employer', [EmployerController::class, 'dashboard'])
    ->name('employer.dashboard');

Route::get('/jobseeker', [JobSeekerController::class, 'dashboard'])
    ->name('jobseeker.dashboard');
