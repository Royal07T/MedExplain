<?php

namespace App\Providers;

use App\Models\ApiPartner;
use App\Services\FastApiClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FastApiClient::class, fn (): FastApiClient => FastApiClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters used by the API.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('documents', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('health-query', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('partner-oauth', function (Request $request) {
            return Limit::perMinute(10)->by($request->input('client_id', '').'|'.$request->ip());
        });

        RateLimiter::for('partner', function (Request $request) {
            $principal = $request->user();
            $quota = $principal instanceof ApiPartner ? $principal->quota_per_minute : 30;

            return Limit::perMinute($quota)->by($principal?->getKey() ?: $request->ip());
        });
    }
}
