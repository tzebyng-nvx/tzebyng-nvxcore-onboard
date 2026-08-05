<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentGatewaySettingsController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

Route::middleware([
    'api',
    InitializeTenancyByRequestData::class,
])->group(function () {
    Route::post('admin/login', fn () => app(AuthController::class, ['guard' => 'admin'])->login())
        ->name('admin.login');
});

Route::middleware([
    'api',
    'auth:admin',
    InitializeTenancyByRequestData::class,
])->group(function () {
    // Auth
    Route::post('admin/logout', fn () => app(AuthController::class, ['guard' => 'admin'])->logout())
        ->name('admin.logout');
    Route::post('admin/refresh', fn () => app(AuthController::class, ['guard' => 'admin'])->refresh())
        ->name('admin.refresh');
    Route::post('admin/me', fn () => app(AuthController::class, ['guard' => 'admin'])->me())
        ->name('admin.me');

    // Payment Gateway Settings
    Route::get(
        'admin/payment-gateway-settings',
        [PaymentGatewaySettingsController::class, 'show']
    );
    Route::put(
        'admin/payment-gateway-settings',
        [PaymentGatewaySettingsController::class, 'update']
    );
});
