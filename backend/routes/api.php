<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateApplicationController;
use App\Http\Controllers\CandidateAuthController;
use App\Http\Controllers\CandidateProfileController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\EmployerApplicationController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\PublicJobController;
use App\Http\Controllers\PublicResumeController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public endpoints
    Route::post('/register', [TenantController::class, 'register']);

    // Auth endpoints (public)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
        Route::post('/password/reset', [AuthController::class, 'resetPassword']);
    });

    // All protected endpoints use our custom auth middleware
    Route::middleware(['havenhr.auth', 'tenant.resolve'])->group(function () {
        // Auth - current user profile
        Route::get('/auth/me', [AuthController::class, 'me']);

        // User management
        Route::get('/users', [UserController::class, 'index'])->middleware('rbac:users.list');
        Route::post('/users', [UserController::class, 'store'])->middleware('rbac:users.create');
        Route::get('/users/{id}', [UserController::class, 'show'])->middleware('rbac:users.view');
        Route::put('/users/{id}', [UserController::class, 'update'])->middleware('rbac:users.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('rbac:users.delete');

        // Audit logs
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('rbac:audit_logs.view');

        // Role management
        Route::get('/roles', [RoleController::class, 'index'])->middleware('rbac:roles.list');
        Route::get('/roles/{id}', [RoleController::class, 'show'])->middleware('rbac:roles.view');

        // Role assignment
        Route::post('/users/{id}/roles', [RoleController::class, 'assignRole'])->middleware('rbac:manage_roles');
        Route::put('/users/{id}/roles', [RoleController::class, 'updateRole'])->middleware('rbac:manage_roles');

        // Pipeline stage management (tenant-scoped) — must be before /jobs/{id} to avoid route conflicts
        Route::get('/jobs/{jobId}/stages', [PipelineController::class, 'listStages'])->middleware('rbac:jobs.view');
        Route::post('/jobs/{jobId}/stages', [PipelineController::class, 'addStage'])->middleware('rbac:pipeline.manage');
        Route::put('/jobs/{jobId}/stages/reorder', [PipelineController::class, 'reorderStages'])->middleware('rbac:pipeline.manage');
        Route::patch('/jobs/{jobId}/stages/{stageId}', [PipelineController::class, 'updateStage'])->middleware('rbac:pipeline.manage');
        Route::delete('/jobs/{jobId}/stages/{stageId}', [PipelineController::class, 'removeStage'])->middleware('rbac:pipeline.manage');

        // Employer application endpoints (tenant-scoped) — must be before /jobs/{id}
        Route::get('/jobs/{jobId}/applications', [EmployerApplicationController::class, 'listForJob'])->middleware('rbac:applications.view');

        // Job posting management (tenant-scoped)
        Route::get('/jobs', [JobPostingController::class, 'index'])->middleware('rbac:jobs.list');
        Route::post('/jobs', [JobPostingController::class, 'store'])->middleware('rbac:jobs.create');
        Route::get('/jobs/{id}', [JobPostingController::class, 'show'])->middleware('rbac:jobs.view');
        Route::put('/jobs/{id}', [JobPostingController::class, 'update'])->middleware('rbac:jobs.update');
        Route::delete('/jobs/{id}', [JobPostingController::class, 'destroy'])->middleware('rbac:jobs.delete');
        Route::patch('/jobs/{id}/status', [JobPostingController::class, 'transitionStatus'])->middleware('rbac:jobs.update');

        // Interview CRUD (tenant-scoped)
        Route::post('/interviews', [InterviewController::class, 'store'])->middleware('rbac:applications.manage');
        Route::get('/interviews/{id}', [InterviewController::class, 'show'])->middleware('rbac:applications.view');
        Route::put('/interviews/{id}', [InterviewController::class, 'update'])->middleware('rbac:applications.manage');
        Route::patch('/interviews/{id}/cancel', [InterviewController::class, 'cancel'])->middleware('rbac:applications.manage');

        // Bulk pipeline operations (tenant-scoped) — must be before /applications/{appId} routes
        Route::post('/applications/bulk-move', [PipelineController::class, 'bulkMove'])->middleware('rbac:applications.manage');
        Route::post('/applications/bulk-reject', [PipelineController::class, 'bulkReject'])->middleware('rbac:applications.manage');

        // Application stage transitions (tenant-scoped)
        Route::post('/applications/{appId}/move', [PipelineController::class, 'moveApplication'])->middleware('rbac:applications.manage');
        Route::get('/applications/{appId}/transitions', [PipelineController::class, 'transitionHistory'])->middleware('rbac:applications.view');
        Route::get('/applications/{appId}/interviews', [InterviewController::class, 'listForApplication'])->middleware('rbac:applications.view');

        // Employer application detail and talent pool (tenant-scoped)
        Route::get('/applications/{id}', [EmployerApplicationController::class, 'show'])->middleware('rbac:applications.view');
        Route::get('/talent-pool', [EmployerApplicationController::class, 'talentPool'])->middleware('rbac:applications.view');

        // Dashboard metrics
        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);
        Route::get('/dashboard/applications-by-stage', [DashboardController::class, 'applicationsByStage']);
        Route::get('/dashboard/upcoming-interviews', [InterviewController::class, 'upcoming']);

        // Company settings
        Route::get('/company', [CompanyController::class, 'show']);
        Route::put('/company', [CompanyController::class, 'update'])->middleware('rbac:tenant.update');

        // Reports & Analytics
        Route::prefix('reports')->middleware('rbac:reports.view')->group(function () {
            Route::get('/overview', [ReportsController::class, 'overview']);
            Route::get('/time-to-hire', [ReportsController::class, 'timeToHire']);
            Route::get('/funnel', [ReportsController::class, 'funnel']);
            Route::get('/sources', [ReportsController::class, 'sources']);
            Route::get('/export/{type}', [ReportsController::class, 'export']);
        });
    });

    // Candidate auth endpoints
    Route::prefix('candidate/auth')->group(function () {
        // Public endpoints
        Route::post('/register', [CandidateAuthController::class, 'register']);
        Route::post('/login', [CandidateAuthController::class, 'login']);
        Route::post('/refresh', [CandidateAuthController::class, 'refresh']);

        // Protected endpoints (require candidate.auth middleware)
        Route::middleware('candidate.auth')->group(function () {
            Route::post('/logout', [CandidateAuthController::class, 'logout']);
            Route::get('/me', [CandidateAuthController::class, 'me']);
        });
    });

    // Candidate profile endpoints (all protected)
    Route::prefix('candidate/profile')->middleware('candidate.auth')->group(function () {
        // Profile
        Route::get('/', [CandidateProfileController::class, 'getProfile']);
        Route::put('/', [CandidateProfileController::class, 'updatePersonalInfo']);

        // Work history
        Route::post('/work-history', [CandidateProfileController::class, 'addWorkHistory']);
        Route::put('/work-history/reorder', [CandidateProfileController::class, 'reorderWorkHistory']);
        Route::put('/work-history/{id}', [CandidateProfileController::class, 'updateWorkHistory']);
        Route::delete('/work-history/{id}', [CandidateProfileController::class, 'deleteWorkHistory']);

        // Education
        Route::post('/education', [CandidateProfileController::class, 'addEducation']);
        Route::put('/education/reorder', [CandidateProfileController::class, 'reorderEducation']);
        Route::put('/education/{id}', [CandidateProfileController::class, 'updateEducation']);
        Route::delete('/education/{id}', [CandidateProfileController::class, 'deleteEducation']);

        // Skills
        Route::put('/skills', [CandidateProfileController::class, 'replaceSkills']);

        // Notification preferences
        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
    });

    // Candidate resume endpoints (all protected)
    Route::prefix('candidate/resumes')->middleware('candidate.auth')->group(function () {
        Route::get('/', [ResumeController::class, 'index']);
        Route::post('/', [ResumeController::class, 'store']);
        Route::get('/{id}', [ResumeController::class, 'show']);
        Route::put('/{id}', [ResumeController::class, 'update']);
        Route::delete('/{id}', [ResumeController::class, 'destroy']);
        Route::post('/{id}/finalize', [ResumeController::class, 'finalize']);
        Route::get('/{id}/versions', [ResumeController::class, 'listVersions']);
        Route::post('/{id}/versions/{versionId}/restore', [ResumeController::class, 'restoreVersion']);
        Route::post('/{id}/share', [ResumeController::class, 'share']);
        Route::post('/{id}/export-pdf', [ResumeController::class, 'exportPdf']);
    });

    // Candidate AI endpoints (all protected)
    Route::prefix('candidate/ai')->middleware('candidate.auth')->group(function () {
        Route::post('/summary', [AIController::class, 'summary']);
        Route::post('/bullets', [AIController::class, 'bullets']);
        Route::post('/skills', [AIController::class, 'skills']);
        Route::post('/ats-optimize', [AIController::class, 'atsOptimize']);
        Route::post('/improve', [AIController::class, 'improve']);
        Route::get('/jobs/{id}', [AIController::class, 'getJob']);
    });

    // Candidate application endpoints (protected)
    Route::prefix('candidate/applications')->middleware('candidate.auth')->group(function () {
        Route::post('/', [CandidateApplicationController::class, 'apply']);
        Route::get('/', [CandidateApplicationController::class, 'index']);
        Route::get('/{id}', [CandidateApplicationController::class, 'show']);
    });

    // Candidate interview endpoints (protected)
    Route::get('/candidate/interviews', [InterviewController::class, 'candidateInterviews'])->middleware('candidate.auth');

    // Public resume endpoint (no auth required)
    Route::get('/public/resumes/{token}', [PublicResumeController::class, 'show']);

    // Public job board endpoints (no auth required)
    Route::get('/public/jobs', [PublicJobController::class, 'index']);
    Route::get('/public/jobs/{slug}', [PublicJobController::class, 'show']);
});
