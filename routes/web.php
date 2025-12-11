<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Payment routes (web)
Route::get('/payment/success', [\App\Http\Controllers\PaymentController::class, 'success']);
Route::get('/payment/cancel', [\App\Http\Controllers\PaymentController::class, 'cancel']);
Route::post('/payment/webhook', [\App\Http\Controllers\PaymentController::class, 'webhook']);

