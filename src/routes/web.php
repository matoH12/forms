<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormController as AdminFormController;
use App\Http\Controllers\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\Admin\WorkflowController as AdminWorkflowController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\FormCategoryController as AdminFormCategoryController;
use App\Http\Controllers\Admin\EmailTemplateController as AdminEmailTemplateController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Public\FormController as PublicFormController;
use App\Http\Controllers\UserSettingsController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [PublicFormController::class, 'index'])->name('home');
Route::get('/forms/{slug}', [PublicFormController::class, 'show'])->name('forms.show');

// Form submission with rate limiting (10 submissions per minute per IP)
Route::post('/forms/{slug}/submit', [PublicFormController::class, 'submit'])
    ->name('forms.submit')
    ->middleware('throttle:10,1');

// Auth routes (rate limited to prevent brute-force attacks)
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])
        ->name('login')
        ->middleware('throttle:5,1'); // 5 attempts per minute per IP
    Route::get('/callback', [AuthController::class, 'callback'])
        ->name('callback')
        ->middleware('throttle:10,1'); // 10 callbacks per minute per IP
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
    Route::get('/logged-out', [AuthController::class, 'loggedOut'])->name('logged-out');
});

// Alias for Laravel's default login route name
Route::get('/login', fn() => redirect('/auth/login'))->name('login');

// Approval routes (public with token, rate limited to prevent brute-force)
Route::prefix('approvals')->name('approvals.')->group(function () {
    // GET with token in URL (for email links) - adds Referrer-Policy header
    Route::get('/{token}', [App\Http\Controllers\ApprovalController::class, 'show'])
        ->name('show')
        ->middleware('throttle:10,1');
    // POST actions accept token in request body (more secure)
    Route::post('/approve', [App\Http\Controllers\ApprovalController::class, 'approve'])
        ->name('approve')
        ->middleware('throttle:5,1');
    Route::post('/reject', [App\Http\Controllers\ApprovalController::class, 'reject'])
        ->name('reject')
        ->middleware('throttle:5,1');
});

// User authenticated routes
Route::middleware(['auth'])->group(function () {
    Route::get('/my/submissions', [PublicFormController::class, 'mySubmissions'])->name('my.submissions');
    Route::get('/my/submissions/{submission}', [PublicFormController::class, 'mySubmissionShow'])->name('my.submissions.show');

    // Profile & Settings
    Route::get('/profile/settings', [UserSettingsController::class, 'show'])->name('profile.settings');
    Route::put('/profile/settings', [UserSettingsController::class, 'update'])->name('profile.settings.update');
    Route::post('/profile/settings/theme', [UserSettingsController::class, 'updateTheme'])->name('profile.settings.theme');
    Route::post('/profile/settings/language', [UserSettingsController::class, 'updateLanguage'])->name('profile.settings.language');
    Route::post('/profile/settings/form-notification/{formId}', [UserSettingsController::class, 'toggleFormNotification'])->name('profile.settings.form-notification');
});

// Admin routes - base access requires 'viewer' role (checked by 'admin' middleware)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    // Dashboard & Audit Logs - viewer+ can access
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/monthly-report', [DashboardController::class, 'monthlyReport'])->name('monthly-report');
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{auditLog}', [AdminAuditLogController::class, 'show'])->name('audit-logs.show');

    // Forms - admin+ only for create (must be before {form} routes to avoid conflict)
    Route::get('/forms/create', [AdminFormController::class, 'create'])->name('forms.create')->middleware('role:admin');
    Route::post('/forms', [AdminFormController::class, 'store'])->name('forms.store')->middleware('role:admin');

    // Forms - viewer can view
    Route::get('/forms', [AdminFormController::class, 'index'])->name('forms.index');
    Route::get('/forms/{form}', [AdminFormController::class, 'show'])->name('forms.show');
    Route::get('/forms/{form}/versions', [AdminFormController::class, 'versions'])->name('forms.versions');
    Route::get('/forms/{form}/versions/{version}', [AdminFormController::class, 'showVersion'])->name('forms.versions.show');
    Route::get('/forms/{form}/submissions', [AdminFormController::class, 'submissions'])->name('forms.submissions');

    // Forms - admin+ only for edit/delete/export
    Route::middleware('role:admin')->group(function () {
        Route::get('/forms/{form}/edit', [AdminFormController::class, 'edit'])->name('forms.edit');
        Route::put('/forms/{form}', [AdminFormController::class, 'update'])->name('forms.update');
        Route::delete('/forms/{form}', [AdminFormController::class, 'destroy'])->name('forms.destroy');
        Route::post('/forms/{form}/versions/{version}/restore', [AdminFormController::class, 'restoreVersion'])->name('forms.versions.restore');
        Route::delete('/forms/{form}/submissions/{submission}', [AdminFormController::class, 'deleteSubmission'])->name('forms.submissions.destroy');
        // SECURITY: Export requires admin role to prevent data exfiltration
        Route::get('/forms/{form}/submissions/export', [AdminFormController::class, 'exportSubmissions'])->name('forms.submissions.export');
    });

    // Form submissions approve/reject - approver+ only
    Route::middleware('role:approver')->group(function () {
        Route::post('/forms/{form}/submissions/{submission}/approve', [AdminFormController::class, 'approveSubmission'])->name('forms.submissions.approve');
        Route::post('/forms/{form}/submissions/{submission}/reject', [AdminFormController::class, 'rejectSubmission'])->name('forms.submissions.reject');
    });

    // Submissions - viewer can view
    Route::get('/submissions', [AdminSubmissionController::class, 'index'])->name('submissions.index');
    Route::get('/submissions/{submission}', [AdminSubmissionController::class, 'show'])->name('submissions.show');
    Route::get('/submissions/{submission}/workflow-status', [AdminSubmissionController::class, 'getWorkflowStatus'])->name('submissions.workflow-status');
    // SECURITY: Export requires admin role to prevent data exfiltration
    Route::get('/submissions/export', [AdminSubmissionController::class, 'export'])->name('submissions.export')->middleware('role:admin');

    // Submissions - approver+ for approve/reject
    Route::middleware('role:approver')->group(function () {
        Route::post('/submissions/bulk-approve', [AdminSubmissionController::class, 'bulkApprove'])->name('submissions.bulk-approve');
        Route::post('/submissions/bulk-reject', [AdminSubmissionController::class, 'bulkReject'])->name('submissions.bulk-reject');
        Route::post('/submissions/{submission}/approve', [AdminSubmissionController::class, 'approve'])->name('submissions.approve');
        Route::post('/submissions/{submission}/reject', [AdminSubmissionController::class, 'reject'])->name('submissions.reject');
        Route::post('/submissions/{submission}/comments', [AdminSubmissionController::class, 'addComment'])->name('submissions.comments.store');
        Route::put('/submissions/{submission}/comments/{comment}', [AdminSubmissionController::class, 'updateComment'])->name('submissions.comments.update');
        Route::delete('/submissions/{submission}/comments/{comment}', [AdminSubmissionController::class, 'deleteComment'])->name('submissions.comments.destroy');
    });

    // Submissions - admin+ for delete and workflow management
    Route::middleware('role:admin')->group(function () {
        Route::post('/submissions/bulk-delete', [AdminSubmissionController::class, 'bulkDelete'])->name('submissions.bulk-delete');
        Route::delete('/submissions/{submission}', [AdminSubmissionController::class, 'destroy'])->name('submissions.destroy');
        Route::delete('/submissions/{submission}/file', [AdminSubmissionController::class, 'deleteFile'])->name('submissions.delete-file');
        // Workflow control
        Route::post('/submissions/{submission}/workflow-executions/{execution}/stop', [AdminSubmissionController::class, 'stopWorkflowExecution'])->name('submissions.workflow.stop');
        Route::post('/submissions/{submission}/workflow-restart', [AdminSubmissionController::class, 'restartWorkflow'])->name('submissions.workflow.restart');
    });

    // Workflows - admin+ only
    Route::middleware('role:admin')->group(function () {
        Route::resource('workflows', AdminWorkflowController::class);
        Route::get('/workflows/{workflow}/executions', [AdminWorkflowController::class, 'executions'])->name('workflows.executions');
        Route::get('/workflows/{workflow}/versions', [AdminWorkflowController::class, 'versions'])->name('workflows.versions');
        Route::get('/workflows/{workflow}/versions/{version}', [AdminWorkflowController::class, 'showVersion'])->name('workflows.versions.show');
        Route::post('/workflows/{workflow}/versions/{version}/restore', [AdminWorkflowController::class, 'restoreVersion'])->name('workflows.versions.restore');

        // Form Categories
        Route::resource('categories', AdminFormCategoryController::class);
        Route::post('/categories/update-order', [AdminFormCategoryController::class, 'updateOrder'])->name('categories.update-order');

        // Email Templates
        Route::resource('email-templates', AdminEmailTemplateController::class);
        Route::get('/email-templates/{email_template}/preview', [AdminEmailTemplateController::class, 'preview'])->name('email-templates.preview');

        // Announcements
        Route::resource('announcements', AdminAnnouncementController::class)->except(['show']);
    });

    // Users, Settings - super_admin only
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        // Settings - read only (no rate limit needed)
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');

        // Settings updates - rate limited (10/min to prevent abuse)
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/settings/mail', [AdminSettingsController::class, 'updateMail'])->name('settings.mail.update');
            Route::post('/settings/keycloak', [AdminSettingsController::class, 'updateKeycloak'])->name('settings.keycloak.update');
            Route::post('/settings/branding', [AdminSettingsController::class, 'updateBranding'])->name('settings.branding.update');
            Route::post('/settings/logo', [AdminSettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
            Route::delete('/settings/logo', [AdminSettingsController::class, 'deleteLogo'])->name('settings.logo.delete');
            Route::post('/settings/backup/settings', [AdminSettingsController::class, 'updateBackupSettings'])->name('settings.backup.settings');
        });

        // Connection tests - stricter rate limit (5/min to prevent brute-force/DoS)
        Route::middleware('throttle:5,1')->group(function () {
            Route::post('/settings/mail/test', [AdminSettingsController::class, 'testMail'])->name('settings.mail.test');
            Route::post('/settings/keycloak/test', [AdminSettingsController::class, 'testKeycloak'])->name('settings.keycloak.test');
            Route::post('/settings/backup/test-ftp', [AdminSettingsController::class, 'testFtpConnection'])->name('settings.backup.test-ftp');
            Route::post('/settings/backup/test-s3', [AdminSettingsController::class, 'testS3Connection'])->name('settings.backup.test-s3');
        });

        // API Tokens - rate limited (5/min)
        Route::post('/settings/api-tokens', [AdminSettingsController::class, 'createApiToken'])
            ->name('settings.api-tokens.store')
            ->middleware('throttle:5,1');
        Route::delete('/settings/api-tokens/{token}', [AdminSettingsController::class, 'deleteApiToken'])
            ->name('settings.api-tokens.destroy')
            ->middleware('throttle:10,1');

        // Backup & Restore - stricter rate limit (3/min for expensive operations)
        Route::middleware('throttle:3,1')->group(function () {
            Route::post('/settings/backup', [AdminSettingsController::class, 'createBackup'])->name('settings.backup.create');
            Route::post('/settings/restore', [AdminSettingsController::class, 'restoreBackup'])->name('settings.backup.restore');
            Route::post('/settings/backup/run', [AdminSettingsController::class, 'runBackupNow'])->name('settings.backup.run');
        });

        // Backup read operations - light rate limit (20/min)
        Route::middleware('throttle:20,1')->group(function () {
            Route::get('/settings/backup/local', [AdminSettingsController::class, 'getLocalBackups'])->name('settings.backup.local');
            Route::get('/settings/backup/local/{filename}', [AdminSettingsController::class, 'downloadLocalBackup'])->name('settings.backup.download');
        });

        Route::delete('/settings/backup/local/{filename}', [AdminSettingsController::class, 'deleteLocalBackup'])
            ->name('settings.backup.delete')
            ->middleware('throttle:10,1');
    });
});
