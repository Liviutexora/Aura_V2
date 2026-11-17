<?php

namespace App\Providers;

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
        // Authentication endpoints use Laravel's numeric throttle middleware.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('admin-password-reset', function (Request $request): Limit {
            $adminIdentifier = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(5)->by($adminIdentifier.'|'.$request->ip());
        });
    }
}
