<?php

use App\Http\Controllers\OrderController;

Route::post('/orders', [OrderController::class, 'store']);

Route::post('/orders', [OrderController::class,'store'])->middleware('idempotency');

Route::get('/metrics', \App\Http\Controllers\MetricsController::class);
