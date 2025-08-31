<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TelemetryServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void { \App\Support\Telemetry::init(); }
}
