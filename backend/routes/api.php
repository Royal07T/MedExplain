<?php

use App\Http\Controllers\Api\V1\ApiDocsController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\ClinicianController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HealthQueryController;
use App\Http\Controllers\Api\V1\LabController;
use App\Http\Controllers\Api\V1\MedicationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PartnerConsentController;
use App\Http\Controllers\Api\V1\PartnerDataController;
use App\Http\Controllers\Api\V1\PartnerOAuthController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public OpenAPI specification for the HealthTech API platform.
    Route::get('api-docs', [ApiDocsController::class, 'openapi']);

    Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:auth');

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:auth');

        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:auth');

        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:auth');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
                ->middleware('throttle:6,1');

            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('user', [UserController::class, 'show']);
        Route::put('user', [UserController::class, 'update']);
        Route::post('user/avatar', [UserController::class, 'updateAvatar']);

        Route::middleware('throttle:api')->group(function (): void {
            Route::get('documents', [DocumentController::class, 'index']);
            Route::get('documents/{document}', [DocumentController::class, 'show']);
            Route::get('documents/{document}/analysis', [DocumentController::class, 'analysis']);
            Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

            Route::get('labs/names', [LabController::class, 'names']);
            Route::get('labs/trends', [LabController::class, 'trends']);
            Route::get('health/timeline', [HealthController::class, 'timeline']);
            Route::get('health/record', [HealthController::class, 'record']);

            Route::get('medications', [MedicationController::class, 'index']);
            Route::post('health/query', [HealthQueryController::class, 'store'])
                ->middleware('throttle:health-query');

            Route::get('plan', [PlanController::class, 'show']);
            Route::post('plan/upgrade', [PlanController::class, 'upgrade']);
            Route::post('plan/cancel', [PlanController::class, 'cancel']);

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        });

        Route::post('documents', [DocumentController::class, 'store'])
            ->middleware('throttle:documents');

        Route::middleware('role:clinician')->prefix('clinician')->group(function (): void {
            Route::get('patients', [ClinicianController::class, 'patients']);
            Route::post('patients', [ClinicianController::class, 'grantAccess'])
                ->middleware('throttle:api');
            Route::get('patients/{patient}/record', [ClinicianController::class, 'record'])
                ->middleware('throttle:api');
        });

        Route::prefix('partner')->group(function (): void {
            Route::get('consents', [PartnerConsentController::class, 'index']);
            Route::post('consents/{partner}', [PartnerConsentController::class, 'grant'])
                ->middleware('throttle:api');
            Route::delete('consents/{partner}', [PartnerConsentController::class, 'revoke'])
                ->middleware('throttle:api');
        });
    });

    // Partner OAuth token endpoint — public, credential-gated.
    Route::post('partner/oauth/token', [PartnerOAuthController::class, 'token'])
        ->middleware('throttle:partner-oauth');

    // Partner-scoped data access — requires an active partner bearer token
    // with the appropriate scope, plus per-partner rate limiting.
    Route::middleware(['auth:sanctum', 'partner', 'partner-scope:health_record:read', 'throttle:partner'])
        ->prefix('partner')
        ->group(function (): void {
            Route::get('patients', [PartnerDataController::class, 'patients']);
            Route::get('patients/{patient}/record', [PartnerDataController::class, 'record']);
        });
});
