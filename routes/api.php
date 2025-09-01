<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\OrderController;

Route::post('/orders', [OrderController::class, 'store']);

Route::post('/orders', [OrderController::class,'store'])->middleware('idempotency');

Route::get('/metrics', \App\Http\Controllers\MetricsController::class);

Route::get('/healthz', \App\Http\Controllers\HealthController::class);

Route::get('/metrics', MetricsController::class)->middleware(['ability:metrics.read','throttle:ops']);
Route::get('/healthz', HealthController::class)->middleware(['ability:health.read','throttle:ops']);
