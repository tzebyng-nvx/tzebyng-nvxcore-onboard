<?php

declare(strict_types=1);

use App\Http\Controllers\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/api/player.php';
require __DIR__.'/api/admin.php';

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Gateway callbacks
Route::post(
    '/payment/callback',
    [PaymentCallbackController::class, 'handle']
)->name('payment.callback');
