<?php

use App\Http\Controllers\StripeController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Models\User;

Route::view('/', 'welcome')
    ->middleware(['auth', 'verified']);

Route::middleware(['auth', 'verified', 'paid'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('/products', [StripeController::class, 'productList'])->name('products');
Route::get('/checkout/{priceId}', [StripeController::class, 'checkout'])->name('checkout');
Route::get('/success', [StripeController::class, 'success'])->name('success');
Route::get('/cancel', [StripeController::class, 'cancel'])->name('cancel');
Route::post('/api/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);
Route::get('/billing', [StripeController::class, 'billingPortal'])
    ->name('billing')
    ->middleware('auth');

require __DIR__.'/auth.php';

