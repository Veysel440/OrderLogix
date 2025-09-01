<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            $key = $request->user()?->id
                ?: $request->attributes->get('api_key_id')
                    ?: $request->ip();
            return [
                Limit::perMinute((int) env('RL_API_PER_MIN', 60))->by($key),
                Limit::perHour((int) env('RL_API_PER_HOUR', 1000))->by($key),
            ];
        });

        RateLimiter::for('ops', function ($request) {
            $key = $request->attributes->get('api_key_id') ?: $request->ip();
            return Limit::perMinute((int) env('RL_OPS_PER_MIN', 20))->by($key);
        });
    }
}
