<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     * These middleware are always active unless excluded from groups.
     *
     * @var array<string, class|string>
     */
    protected $middleware = [
        \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleDisguisedStrings::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\ValidateCSRF::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\TenantIsolation::class,
        \App\Http\Middleware\AddCorrelationId::class,
    ];

    /**
     * The application's middleware groups.
     *
     * @var array<string, array<string, class|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\RegisterBladeComponents::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendState::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':60',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * @var array<string, class|string>
     */
    protected $routeMiddleware = [
        'auth' => \Laravel\Sanctum\Http\Middleware\AuthenticateSanctum::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'csrf' => \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':generic',
        'role' => \App\Http\Middleware\EnsureUserRole::class,
        'partner' => \App\Http\Middleware\EnsureActivePartner::class,
        'partner-scope' => \App\Http\Middleware\EnsurePartnerScope::class,
        'tenant-isolation' => \App\Http\Middleware\TenantIsolation::class,
        'correlation-id' => \App\Http\Middleware\AddCorrelationId::class,
    ];
}
