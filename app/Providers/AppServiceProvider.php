<?php

namespace App\Providers;

use App\Services\Eneo\EneoClientInterface;
use App\Services\Eneo\MockEneoClient;
use App\Services\Gateway\GatewayClientInterface;
use App\Services\Gateway\MockGatewayClient;
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
        $this->app->singleton(EneoClientInterface::class, MockEneoClient::class);
        $this->app->singleton(GatewayClientInterface::class, MockGatewayClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $key = optional($request->user())->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(30)->by($key);
        });
    }
}
