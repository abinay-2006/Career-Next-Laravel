<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Employer\EmployerController;
use App\Http\Controllers\JobSeeker\JobSeekerController;
use App\Http\Controllers\JobSeeker\JobSeekerSkillController;
use App\Http\Controllers\Admin\ManageUsersController;
use App\Http\Controllers\Admin\ManageJobsController;
use App\Http\Controllers\Admin\ManageDocumentsController;
use App\Http\Controllers\Employer\EmployerJobController;
use App\Http\Controllers\Employer\WalkinController;
use App\Http\Controllers\Employer\EmployerApplicationController;
use App\Http\Controllers\Employer\FollowUpMessageController;
use App\Http\Controllers\JobSeeker\EducationController;
use App\Http\Controllers\JobSeeker\JobSearchController;
use App\Http\Controllers\Employer\JobQualityController;
use App\Http\Controllers\JobSeeker\JobSeekerWalkinController;
use App\Http\Controllers\JobSeeker\JobApplicationController;
use App\Http\Controllers\Employer\EmployerProfileController;
use App\Http\Controllers\JobSeeker\JobSeekerProfileController;
use App\Http\Controllers\JobSeeker\ApplicationHistoryController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\Admin\ManageNewsController;
use App\Http\Controllers\Admin\ManageFeedbackController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\DashboardAnalyticsController;
use App\Http\Controllers\JobSeeker\ResumeBuilderController;
use App\Http\Controllers\JobSeeker\SavedJobController;
use App\Http\Controllers\JobSeeker\CareerPreferencesController;
use App\Http\Controllers\JobSeeker\JobComparisonController;
use App\Http\Controllers\JobSeeker\ApplicationFollowUpController;
// Route

// Home Page Route
Route::get('/', [HomeController::class, 'index']);

// Login and Registration Routes
Route::get('/login', [LoginController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');

Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');


Route::get('/forgot', [PasswordResetController::class, 'show'])->name('forgot');
Route::post('/forgot', [PasswordResetController::class, 'sendOtp'])
    ->middleware('throttle:password-recovery-send')
    ->name('forgot.send');
Route::get('/forgot/verify', [PasswordResetController::class, 'showOtp'])->name('forgot.verify');
Route::post('/forgot/verify', [PasswordResetController::class, 'verifyOtp'])->middleware('throttle:password-recovery-verify')->name('forgot.verify.submit');
Route::get('/forgot/{token}', [PasswordResetController::class, 'showReset'])->name('forgot.token');
Route::post('/forgot/{token}', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:6,1,forgot-reset')->name('forgot.reset');
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{news}', [NewsController::class, 'show'])->name('news.show');

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
            ->middleware('throttle:6,1')->name('verify.store');

        Route::post('/resend-otp', [RegisterController::class, 'resendOtp'])
            ->middleware('throttle:3,1')->name('resend-otp');
    });

// Admin Routes

Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {

    Route::get('/', [AdminController::class, 'dashboard'])
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
    Route::resource('news', ManageNewsController::class)->except(['create', 'edit'])->names('news');
    Route::get('/feedback', [ManageFeedbackController::class, 'index'])->name('feedback.index');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('logs.index');
});
//Employer Routes
Route::prefix('employer')
    ->name('employer.')
    ->middleware('role:employer')
    ->group(function () {

        Route::get('/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
        Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:5,10')->name('feedback.store');

        Route::get('/', [EmployerController::class, 'index'])
            ->name('dashboard');
        Route::get('/analytics', [DashboardAnalyticsController::class, 'employer'])->name('analytics');

        //Job Routes
        Route::get('/jobs', [EmployerJobController::class, 'index'])
            ->name('jobs.index');

        Route::post('/jobs', [EmployerJobController::class, 'store'])
            ->name('jobs.store');

        Route::put('/jobs/{id}', [EmployerJobController::class, 'update'])
            ->name('jobs.update');

        Route::delete('/jobs/{id}', [EmployerJobController::class, 'destroy'])
            ->name('jobs.destroy');

        // Live scoring for the job form. The employer types, this answers with the
        // report, and nothing is written - see JobQualityService.
        Route::post('/jobs/quality-check', [JobQualityController::class, 'check'])
            ->middleware('throttle:120,1')
            ->name('jobs.qualityCheck');

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

        // Follow-up messages an applicant chose to deliver from the Follow-up Coach.
        // Read-only here: the employer may only record that they looked.
        Route::get('/follow-up-messages', [FollowUpMessageController::class, 'index'])
            ->name('followupmessages.index');

        Route::post('follow-up-messages/{message}/seen', [FollowUpMessageController::class, 'seen'])
            ->whereNumber('message')->middleware('throttle:60,1')->name('followupmessages.seen');

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
        Route::get('/analytics', [DashboardAnalyticsController::class, 'jobSeeker'])->name('analytics');
        Route::get('/resume-builder', [ResumeBuilderController::class, 'index'])->name('resumes.index');
        Route::get('/my-resumes', [ResumeBuilderController::class, 'myResumes'])->name('my-resumes.index');
        Route::get('/my-resumes/{resume}', [ResumeBuilderController::class, 'show'])->name('my-resumes.show');
        Route::get('/my-resumes/{resume}/preview', [ResumeBuilderController::class, 'previewSaved'])->name('my-resumes.preview');
        Route::post('/resume-builder', [ResumeBuilderController::class, 'store'])->name('resumes.store');
        Route::post('/resume-builder/new', [ResumeBuilderController::class, 'create'])->name('resumes.create');
        Route::post('/resume-builder/preview', [ResumeBuilderController::class, 'preview'])->name('resumes.preview');
        Route::get('/resume-builder/{resume}/download', [ResumeBuilderController::class, 'download'])->name('resumes.download');
        Route::delete('/resume-builder/{resume}', [ResumeBuilderController::class, 'destroy'])->name('resumes.destroy');
        Route::get('/applications', [ApplicationHistoryController::class, 'index'])->name('applications.index');
        Route::get('/applications/{application}', [ApplicationHistoryController::class, 'show'])->name('applications.show');

        // Application Follow-up Coach: advice and drafts only, nothing is sent for the seeker.
        Route::get('/follow-up-coach', [ApplicationFollowUpController::class, 'index'])->name('followup.index');
        Route::post('/follow-up-coach/preferences', [ApplicationFollowUpController::class, 'intervalSave'])->name('followup.preferences');
        Route::post('/follow-up-coach/{application}/message', [ApplicationFollowUpController::class, 'message'])
            ->whereNumber('application')->middleware('throttle:60,1')->name('followup.message');
        // Deliberate delivery of the edited draft to the employer's own pages. Nothing is emailed.
        Route::post('/follow-up-coach/{application}/send', [ApplicationFollowUpController::class, 'send'])
            ->whereNumber('application')->middleware('throttle:10,1')->name('followup.send');
        Route::post('/follow-up-coach/{application}/activity', [ApplicationFollowUpController::class, 'activity'])
            ->whereNumber('application')->middleware('throttle:30,1')->name('followup.activity');
        Route::post('/follow-up-coach/{application}/prep', [ApplicationFollowUpController::class, 'prep'])
            ->whereNumber('application')->middleware('throttle:60,1')->name('followup.prep');
        Route::get('/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
        Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:5,10')->name('feedback.store');

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

        // Skills and software the seeker knows
        Route::put('/profile/skills', [JobSeekerSkillController::class, 'update'])
            ->name('profile.skills.update');


        // Saved Jobs
        Route::get('/saved-jobs', [SavedJobController::class, 'index'])
            ->name('saved-jobs.index');

        Route::post('/saved-jobs/{job}', [SavedJobController::class, 'store'])
            ->whereNumber('job')
            ->name('saved-jobs.store');

        Route::post('/saved-jobs/{job}/remove', [SavedJobController::class, 'destroy'])
            ->whereNumber('job')
            ->name('saved-jobs.destroy');

        // Career Preferences (optional)
        Route::get('/career-preferences', [CareerPreferencesController::class, 'edit'])
            ->name('preferences.edit');

        Route::put('/career-preferences', [CareerPreferencesController::class, 'update'])
            ->name('preferences.update');

        // Job Offer Comparison Lab (selection lives in the session)
        Route::get('/compare-jobs', [JobComparisonController::class, 'index'])
            ->name('compare.index');

        Route::post('/compare-jobs/clear', [JobComparisonController::class, 'clear'])
            ->name('compare.clear');

        Route::post('/compare-jobs/priorities', [JobComparisonController::class, 'weights'])
            ->name('compare.weights');

        Route::post('/compare-jobs/{job}', [JobComparisonController::class, 'add'])
            ->whereNumber('job')
            ->name('compare.add');

        Route::post('/compare-jobs/{job}/remove', [JobComparisonController::class, 'remove'])
            ->whereNumber('job')
            ->name('compare.remove');

        Route::post('/compare-jobs/{job}/replace', [JobComparisonController::class, 'replace'])
            ->whereNumber('job')
            ->name('compare.replace');

        Route::post('/compare-jobs/{job}/move', [JobComparisonController::class, 'move'])
            ->whereNumber('job')
            ->name('compare.move');
    });
