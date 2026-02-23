<?php

use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\SubmissionApiController;
use App\Http\Controllers\Api\WorkflowController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\ExportImportController;
use Illuminate\Support\Facades\Route;

// Public API routes
Route::prefix('v1')->group(function () {
    // Public forms
    Route::get('/forms/{slug}', [FormController::class, 'show']);
    Route::post('/forms/{slug}/submit', [FormController::class, 'submit']);

    // Approval actions - token in request body (rate limited to prevent brute-force)
    Route::post('/approvals/approve', [ApprovalController::class, 'approve'])
        ->middleware('throttle:5,1');
    Route::post('/approvals/reject', [ApprovalController::class, 'reject'])
        ->middleware('throttle:5,1');
});

// System API Token authenticated routes (for external integrations)
// SECURITY: Middleware validates token + checks ability based on route
Route::prefix('v1')->group(function () {
    // REST API for submissions (external integrations like CMDB)
    // These routes require 'submissions:read' ability
    Route::get('/submissions/approved', [SubmissionApiController::class, 'approved'])
        ->middleware('system.api.token:submissions:read');
    Route::get('/submissions', [SubmissionApiController::class, 'index'])
        ->middleware('system.api.token:submissions:read');
    Route::get('/submissions/{submission}', [SubmissionApiController::class, 'show'])
        ->middleware('system.api.token:submissions:read');
    Route::get('/forms', [SubmissionApiController::class, 'forms'])
        ->middleware('system.api.token:forms:read');

    // Import endpoints (for migrating data from old system)
    // SECURITY: Requires 'submissions:import' ability + rate limited
    Route::post('/submissions/import', [SubmissionApiController::class, 'import'])
        ->middleware(['system.api.token:submissions:import', 'throttle:10,1']);
    Route::post('/submissions/import/batch', [SubmissionApiController::class, 'importBatch'])
        ->middleware(['system.api.token:submissions:import', 'throttle:2,1']); // Stricter limit for batch
});

// Authenticated user routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', function () {
        return request()->user();
    });

    // My submissions
    Route::get('/my/submissions', [SubmissionController::class, 'mySubmissions']);
    Route::get('/my/forms', [FormController::class, 'myForms']);
});

// Admin API routes - base access requires 'viewer' role (checked by 'admin' middleware)
Route::prefix('v1/admin')->name('api.admin.')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Stats - viewer+ can access
    Route::get('/stats', [App\Http\Controllers\Api\StatsController::class, 'index']);

    // Submissions - viewer can view
    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
    Route::get('/forms/{form}/submissions', [SubmissionController::class, 'byForm']);

    // Forms - viewer can view
    Route::get('/forms', [FormController::class, 'index']);
    Route::get('/forms/{form}', [FormController::class, 'show']);

    // Forms - admin+ for create/edit/delete
    Route::middleware('role:admin')->group(function () {
        Route::post('/forms', [FormController::class, 'store']);
        Route::put('/forms/{form}', [FormController::class, 'update']);
        Route::delete('/forms/{form}', [FormController::class, 'destroy']);
        Route::post('/forms/{form}/duplicate', [FormController::class, 'duplicate']);
    });

    // Submissions - admin+ for delete
    Route::delete('/submissions/{submission}', [SubmissionController::class, 'destroy'])
        ->middleware('role:admin');

    // Workflows - admin+ only
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('workflows', WorkflowController::class);
        Route::post('/workflows/{workflow}/toggle', [WorkflowController::class, 'toggle']);
        Route::get('/workflows/{workflow}/executions', [WorkflowController::class, 'executions']);
        Route::post('/workflows/{workflow}/test', [WorkflowController::class, 'test']);
    });

    // Export - admin+ only
    Route::prefix('export')->middleware('role:admin')->group(function () {
        Route::get('/categories', [ExportImportController::class, 'exportCategories']);
        Route::get('/forms', [ExportImportController::class, 'exportForms']);
        Route::get('/forms/{form}', [ExportImportController::class, 'exportForm']);
        Route::get('/workflows', [ExportImportController::class, 'exportWorkflows']);
        Route::get('/workflows/{workflow}', [ExportImportController::class, 'exportWorkflow']);
    });

    // Import - super_admin only (can overwrite existing data)
    Route::prefix('import')->middleware('role:super_admin')->group(function () {
        Route::post('/categories', [ExportImportController::class, 'importCategories']);
        Route::post('/forms', [ExportImportController::class, 'importForms']);
        Route::post('/workflows', [ExportImportController::class, 'importWorkflows']);
    });
});
