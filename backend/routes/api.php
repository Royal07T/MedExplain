<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
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

        Route::middleware('throttle:api')->group(function (): void {
            Route::get('documents', [DocumentController::class, 'index']);
            Route::get('documents/{document}', [DocumentController::class, 'show']);
            Route::delete('documents/{document}', [DocumentController::class, 'destroy']);
        });

        Route::post('documents', [DocumentController::class, 'store'])
            ->middleware('throttle:documents');
    });
});