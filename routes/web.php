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
use App\Http\Controllers\Admin\ManageDocumentsController;
use App\Http\Controllers\Employer\EmployerJobController;
use App\Http\Controllers\Employer\WalkinController;
use App\Http\Controllers\Employer\EmployerApplicationController;
use App\Http\Controllers\JobSeeker\EducationController;
use App\Http\Controllers\JobSeeker\JobSearchController;
use App\Http\Controllers\JobSeeker\JobSeekerWalkinController;
use App\Http\Controllers\JobSeeker\JobApplicationController;
use App\Http\Controllers\Employer\EmployerProfileController;
use App\Http\Controllers\JobSeeker\JobSeekerProfileController;

// Route

// Home Page Route
Route::get('/', [HomeController::class, 'index']);

// Login and Registration Routes
Route::get('/login', [LoginController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [LoginController::class, 'login']);

Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');


Route::get('/forgot', function () {
    return 'Forgot Password Page - Coming Soon';
})->name('forgot');

// ═══════════════════════════════════════════════════════════════
// Registration Routes
// ═══════════════════════════════════════════════════════════════

Route::prefix('register')
    ->name('register.')
    ->group(function () {

        // Step 1
        Route::get('/', [RegisterController::class, 'showRegistration'])
            ->name('index');

        Route::post('/account-type', [RegisterController::class, 'storeAccountType'])
            ->name('account-type');


        // Step 2
        Route::get('/details', [RegisterController::class, 'showDetails'])
            ->name('details');

        Route::post('/details', [RegisterController::class, 'storeDetails'])
            ->name('details.store');


        // Step 3
        Route::get('/profile', [RegisterController::class, 'showProfile'])
            ->name('profile');

        Route::post('/profile', [RegisterController::class, 'storeProfile'])
            ->name('profile.store');

        // Step 4 - Professional Details
        Route::get('/professional', [RegisterController::class, 'showProfessional'])
            ->name('professional');

        Route::post('/professional', [RegisterController::class, 'storeProfessional'])
            ->name('professional.store');

        // Step 5 - Review
        Route::get('/review', [RegisterController::class, 'showReview'])
            ->name('review');

        Route::post('/review', [RegisterController::class, 'storeReview'])
            ->name('review.store');

        // Step 6 - OTP Verification

        Route::get('/verify', [RegisterController::class, 'showVerify'])
            ->name('verify');

        Route::post('/verify', [RegisterController::class, 'verifyOtp'])
            ->name('verify.store');

        Route::post('/resend-otp', [RegisterController::class, 'resendOtp'])
            ->name('resend-otp');
    });

// Admin Routes

Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {

    Route::get('/', [AdminController::class, 'index'])
        ->name('index');

    Route::get('/dashboard', [AdminController::class, 'dashboard'])
        ->name('dashboard');

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

    // Manage Documents
    Route::get(
        '/manage-documents',
        [ManageDocumentsController::class, 'index']
    )->name('manage.documents');

    Route::get(
        '/manage-documents/view/{id}',
        [ManageDocumentsController::class, 'view']
    )->name('documents.view');

    Route::post('/manage-documents/{id}/approve', [ManageDocumentsController::class, 'approve'])
        ->name('documents.approve');

    Route::post('/manage-documents/{id}/reject', [ManageDocumentsController::class, 'reject'])
        ->name('documents.reject');
});
//Employer Routes
Route::prefix('employer')
    ->name('employer.')
    ->middleware('role:employer')
    ->group(function () {

        Route::get('/', [EmployerController::class, 'index'])
            ->name('dashboard');

        //Job Routes
        Route::get('/jobs', [EmployerJobController::class, 'index'])
            ->name('jobs.index');

        Route::post('/jobs', [EmployerJobController::class, 'store'])
            ->name('jobs.store');

        Route::put('/jobs/{id}', [EmployerJobController::class, 'update'])
            ->name('jobs.update');

        Route::delete('/jobs/{id}', [EmployerJobController::class, 'destroy'])
            ->name('jobs.destroy');

        //Walkin Routes
        Route::get('/walkins', [WalkinController::class, 'index'])
            ->name('walkins.index');

        Route::post('/walkins', [WalkinController::class, 'store'])
            ->name('walkins.store');

        Route::put('/walkins/{walkin}', [WalkinController::class, 'update'])
            ->name('walkins.update');

        Route::delete('/walkins/{walkin}', [WalkinController::class, 'destroy'])
            ->name('walkins.destroy');

        //Application Routes
        Route::get(
            '/applications',
            [EmployerApplicationController::class, 'index']
        )->name('applications.index');

        Route::get('applications/{application}', [EmployerApplicationController::class, 'show'])
            ->name('applications.show');

        Route::get('applications/{application}/resume', [EmployerApplicationController::class, 'viewResume'])
            ->name('applications.resume');

        Route::put('applications/{application}', [EmployerApplicationController::class, 'update'])
            ->name('applications.update');

        //Profile Routes
        Route::get('/profile', [EmployerProfileController::class, 'index'])
            ->name('profile.index');

        Route::put('/profile', [EmployerProfileController::class, 'update'])
            ->name('profile.update');
    });

Route::prefix('jobseeker')
    ->name('jobseeker.')
    ->middleware('role:jobseeker')
    ->group(function () {

        Route::get('/', [JobSeekerController::class, 'index'])
            ->name('index');

        //Education Routes
        Route::get('/education', [EducationController::class, 'index'])
            ->name('education');

        Route::post('/education', [EducationController::class, 'store'])
            ->name('education.store');

        Route::put('/education/{id}', [EducationController::class, 'update'])
            ->name('education.update');

        Route::delete('/education/{id}', [EducationController::class, 'destroy'])
            ->name('education.destroy');

        Route::post('/school', [EducationController::class, 'storeSchool'])
            ->name('school.store');

        Route::put('/school/{id}', [EducationController::class, 'updateSchool'])
            ->name('school.update');

        Route::delete('/school/{id}', [EducationController::class, 'destroySchool'])
            ->name('school.destroy');

        Route::post('/documents', [EducationController::class, 'uploadDocument'])
            ->name('documents.store');

        Route::get('/documents/{id}/view', [EducationController::class, 'viewDocument'])
            ->name('documents.view');

        Route::delete('/documents/{id}', [EducationController::class, 'deleteDocument'])
            ->name('documents.destroy');

        //Apply job
        // Job Search
        Route::get('/jobs', [JobSearchController::class, 'index'])
            ->name('jobs');

        // Job Details
        Route::get('/jobs/{job}', [JobSearchController::class, 'show'])
            ->name('jobs.show');

        // Apply for Job
        Route::post('/jobs/{job}/apply', [JobApplicationController::class, 'apply'])
            ->name('jobs.apply');
        
        // Walkin Jobs

        Route::get('/walkins', [JobSeekerWalkinController::class, 'index'])
            ->name('walkins');

        //Profile Routes
        Route::get(
            '/profile',
            [JobSeekerProfileController::class, 'index']
        )->name('profile.index');

        Route::put(
            '/profile',
            [JobSeekerProfileController::class, 'update']
        )->name('profile.update');

        // Resume Upload
        Route::put('/profile/resume', [JobSeekerProfileController::class, 'updateResume'])
            ->name('profile.resume.update');

        Route::delete('/profile/resume', [JobSeekerProfileController::class, 'removeResume'])
            ->name('profile.resume.remove');
    });
