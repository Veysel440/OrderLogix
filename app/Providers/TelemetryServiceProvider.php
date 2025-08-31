<?php declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TelemetryServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        if (!filter_var(env('OTEL_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        \App\Support\Telemetry::init();
    }
}
