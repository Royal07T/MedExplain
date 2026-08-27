<?php

use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminDepartmentController;
use App\Http\Controllers\Api\V1\Admin\AdminStaffController;
use App\Http\Controllers\Api\V1\Admin\AdminInventoryController;
use App\Http\Controllers\Api\V1\Admin\AdminBillingController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\Clinician\ClinicianDashboardController;
use App\Http\Controllers\Api\V1\ClinicianController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HealthQueryController;
use App\Http\Controllers\Api\V1\LabController;
use App\Http\Controllers\Api\V1\LabOrderController;
use App\Http\Controllers\Api\V1\MedicationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Patient\PatientDashboardController;
use App\Http\Controllers\Api\V1\PatientContextController;
use App\Http\Controllers\Api\V1\PartnerConsentController;
use App\Http\Controllers\Api\V1\PartnerDataController;
use App\Http\Controllers\Api\V1\PartnerOAuthController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminAIController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminHealthController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminOrganizationController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminUserController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // ─── Public ───────────────────────────────────────────
    Route::get('api-docs', [\App\Http\Controllers\Api\V1\ApiDocsController::class, 'openapi']);

    Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
                ->middleware('throttle:6,1');
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    // Partner OAuth — public, credential-gated.
    Route::post('partner/oauth/token', [PartnerOAuthController::class, 'token'])
        ->middleware('throttle:partner-oauth');

    // ─── All authenticated routes ──────────────────────────
    Route::middleware('auth:sanctum')->group(function (): void {

        // User profile (all roles)
        Route::get('user', [UserController::class, 'show']);
        Route::put('user', [UserController::class, 'update']);
        Route::post('user/avatar', [UserController::class, 'updateAvatar']);

        // Notifications (all roles)
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

        // Plan (all roles)
        Route::get('plan', [PlanController::class, 'show']);
        Route::post('plan/upgrade', [PlanController::class, 'upgrade']);
        Route::post('plan/cancel', [PlanController::class, 'cancel']);

        // Partner consents
        Route::prefix('partner')->group(function (): void {
            Route::get('consents', [PartnerConsentController::class, 'index']);
            Route::post('consents/{partner}', [PartnerConsentController::class, 'grant'])->middleware('throttle:api');
            Route::delete('consents/{partner}', [PartnerConsentController::class, 'revoke'])->middleware('throttle:api');
        });

        // ─── Patient context (clinician + nursing) ─────────
        Route::prefix('patient-context')->middleware('role:clinician,nursing_staff')->group(function (): void {
            Route::post('select', [PatientContextController::class, 'select']);
            Route::delete('/', [PatientContextController::class, 'clear']);
            Route::get('/', [PatientContextController::class, 'current']);
            Route::get('search', [PatientContextController::class, 'search']);
        });

        // ─── PATIENT WORKSPACE ─────────────────────────────
        Route::prefix('patient')->middleware('role:patient')->group(function (): void {
            Route::get('dashboard', [PatientDashboardController::class, 'index']);
        });

        // ─── CLINICIAN WORKSPACE ───────────────────────────
        Route::prefix('clinician')->middleware('role:clinician')->group(function (): void {
            Route::get('dashboard', [ClinicianDashboardController::class, 'index']);

            Route::get('patients', [ClinicianController::class, 'patients']);
            Route::post('patients', [ClinicianController::class, 'grantAccess'])->middleware('throttle:api');
            Route::get('patients/{patient}/record', [ClinicianController::class, 'record'])->middleware('throttle:api');

            Route::post('lab-orders', [LabOrderController::class, 'store'])->middleware('throttle:api');
            Route::get('lab-orders', [LabOrderController::class, 'index']);
            Route::get('lab-orders/{id}', [LabOrderController::class, 'show']);
            Route::post('lab-orders/{id}/status', [LabOrderController::class, 'updateStatus']);
        });

        // ─── NURSING WORKSPACE ─────────────────────────────
        Route::prefix('nursing')->middleware('role:nursing_staff')->group(function (): void {
            Route::get('dashboard', [\App\Http\Controllers\Api\V1\Nursing\NursingDashboardController::class, 'index']);
        });

        // ─── ADMIN WORKSPACE ───────────────────────────────
        Route::prefix('admin')->middleware('role:admin,super_admin')->group(function (): void {
            Route::get('dashboard', [AdminDashboardController::class, 'index']);

            Route::get('departments', [AdminDepartmentController::class, 'index']);
            Route::post('departments', [AdminDepartmentController::class, 'store'])->middleware('throttle:api');
            Route::get('departments/{department}', [AdminDepartmentController::class, 'show']);
            Route::put('departments/{department}', [AdminDepartmentController::class, 'update']);
            Route::delete('departments/{department}', [AdminDepartmentController::class, 'destroy']);

            Route::get('staff', [AdminStaffController::class, 'index']);
            Route::post('staff/{userId}/departments/{departmentId}', [AdminStaffController::class, 'assign'])->middleware('throttle:api');
            Route::delete('staff/{userId}/departments/{departmentId}', [AdminStaffController::class, 'remove'])->middleware('throttle:api');

            Route::get('inventory', [AdminInventoryController::class, 'index']);
            Route::post('inventory', [AdminInventoryController::class, 'store'])->middleware('throttle:api');
            Route::get('inventory/{item}', [AdminInventoryController::class, 'show']);
            Route::put('inventory/{item}', [AdminInventoryController::class, 'update']);
            Route::delete('inventory/{item}', [AdminInventoryController::class, 'destroy']);

            Route::get('billing', [AdminBillingController::class, 'index']);
            Route::post('billing', [AdminBillingController::class, 'store'])->middleware('throttle:api');
            Route::get('billing/{invoice}', [AdminBillingController::class, 'show']);
            Route::post('billing/{invoice}/status', [AdminBillingController::class, 'updateStatus'])->middleware('throttle:api');
        });

        // ─── SUPER ADMIN WORKSPACE ─────────────────────────
        Route::prefix('superadmin')->middleware('role:super_admin')->group(function (): void {
            Route::get('dashboard', [SuperAdminDashboardController::class, 'index']);

            Route::get('organizations', [SuperAdminOrganizationController::class, 'index']);
            Route::post('organizations', [SuperAdminOrganizationController::class, 'store'])->middleware('throttle:api');
            Route::get('organizations/{organization}', [SuperAdminOrganizationController::class, 'show']);
            Route::put('organizations/{organization}', [SuperAdminOrganizationController::class, 'update']);

            Route::get('users', [SuperAdminUserController::class, 'index']);
            Route::get('users/{user}', [SuperAdminUserController::class, 'show']);
            Route::put('users/{user}', [SuperAdminUserController::class, 'update']);

            Route::get('ai/config', [SuperAdminAIController::class, 'config']);
            Route::get('ai/usage', [SuperAdminAIController::class, 'usage']);

            Route::get('system/health', [SuperAdminHealthController::class, 'index']);
        });

        // ─── Shared authenticated routes (role-scoped) ─────
        Route::middleware('throttle:api')->group(function (): void {
            // Documents
            Route::get('documents', [DocumentController::class, 'index']);
            Route::get('documents/{document}', [DocumentController::class, 'show']);
            Route::get('documents/{document}/analysis', [DocumentController::class, 'analysis']);
            Route::delete('documents/{document}', [DocumentController::class, 'destroy']);
            Route::post('documents', [DocumentController::class, 'store'])->middleware('throttle:documents');

            // Labs
            Route::get('labs/names', [LabController::class, 'names']);
            Route::get('labs/trends', [LabController::class, 'trends']);

            // Health
            Route::get('health/timeline', [HealthController::class, 'timeline']);
            Route::get('health/record', [HealthController::class, 'record']);

            // Medications
            Route::get('medications', [MedicationController::class, 'index']);

            // AI Query
            Route::post('health/query', [HealthQueryController::class, 'store'])
                ->middleware('throttle:health-query');
        });

        // ─── Doctor workspace (legacy — moved inside auth) ──
        Route::prefix('doctor')->group(function (): void {
            Route::get('triage-queue', [\App\Http\Controllers\Api\V1\DoctorWorkspaceController::class, 'triageQueue'])
                ->middleware('role:clinician');
            Route::get('assigned-patients', [\App\Http\Controllers\Api\V1\DoctorWorkspaceController::class, 'assignedPatients'])
                ->middleware('role:clinician');
        });
    });

    // ─── Partner data access (separate auth) ───────────────
    Route::middleware(['auth:sanctum', 'partner', 'partner-scope:health_record:read', 'throttle:partner'])
        ->prefix('partner')
        ->group(function (): void {
            Route::get('patients', [PartnerDataController::class, 'patients']);
            Route::get('patients/{patient}/record', [PartnerDataController::class, 'record']);
        });
});
