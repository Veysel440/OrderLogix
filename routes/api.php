<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PulseStreamController;
use App\Http\Controllers\PulseExportController;

Route::middleware(['throttle:api'])->group(function () {
    Route::post('/orders', [OrderController::class, 'store'])->middleware('idempotency');
});

Route::get('/metrics',  MetricsController::class)->middleware(['ability:metrics.read','throttle:ops']);
Route::get('/healthz',  HealthController::class)->middleware(['ability:health.read','throttle:ops']);

Route::prefix('pulse')->middleware(['ability:pulse.read','throttle:ops'])->group(function () {
    Route::get('/stream',   PulseStreamController::class);
    Route::get('/events',  [PulseExportController::class, 'events']);
    Route::get('/snapshot',[PulseExportController::class, 'snapshot']);
});
