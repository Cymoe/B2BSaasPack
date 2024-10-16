<?php

use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhook', [StripeWebhookController::class, 'handleWebhook']);

