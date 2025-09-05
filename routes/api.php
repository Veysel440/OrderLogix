<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\OrderController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PulseStreamController;
use App\Http\Controllers\PulseExportController;


Route::post('/orders', [OrderController::class, 'store'])
    ->middleware('idempotency');

Route::get('/metrics', MetricsController::class)
    ->middleware(['ability:metrics.read', 'throttle:ops']);

Route::get('/healthz', HealthController::class)
    ->middleware(['ability:health.read', 'throttle:ops']);

Route::get('/pulse/stream', PulseStreamController::class)
    ->middleware(['ability:pulse.read', 'throttle:ops']);

Route::get('/pulse/events', [PulseExportController::class, 'events'])
    ->middleware(['ability:pulse.read', 'throttle:ops']);

Route::get('/pulse/snapshot', [PulseExportController::class, 'snapshot'])
    ->middleware(['ability:pulse.read', 'throttle:ops']);
