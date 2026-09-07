<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
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
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // PRD §44.7 — login throttled per identity/IP.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(20)->by($this->subjectKey($request));
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($this->subjectKey($request));
        });
    }

    private function subjectKey(Request $request): string
    {
        $user = $request->user();

        return (string) ($user !== null ? $user->getAuthIdentifier() : $request->ip());
    }
}
