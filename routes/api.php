<?php

use Illuminate\Support\Facades\Route;
use ESolution\BNIPayment\Http\Controllers\BniCallbackController;
use ESolution\BNIPayment\Http\Controllers\PaymentNotificationController;

Route::group([
    'prefix' => config('bni.routes.prefix'),
    'middleware' => config('bni.routes.middleware')
], function () {
    Route::post('/bni/va/callback', [BniCallbackController::class, 'va'])->name('bni.va.callback');
    Route::post('/bni/qris/callback', [BniCallbackController::class, 'qris'])->name('bni.qris.callback');
    Route::post('/bni/va/payment-notification', [PaymentNotificationController::class, 'receive'])->name('bni.va.payment_notification');
});

// Mock endpoints
Route::post('/bni/va/mock-create', [\ESolution\BNIPayment\Http\Controllers\MockController::class, 'createVa']);
Route::put('/bni/va/mock-update', [\ESolution\BNIPayment\Http\Controllers\MockController::class, 'updateVa']);
Route::post('/bni/va/mock-inquiry', [\ESolution\BNIPayment\Http\Controllers\MockController::class, 'inquiryVa']);
