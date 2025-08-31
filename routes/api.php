<?php

use App\Http\Controllers\OrderController;

Route::post('/orders', [OrderController::class, 'store']);
