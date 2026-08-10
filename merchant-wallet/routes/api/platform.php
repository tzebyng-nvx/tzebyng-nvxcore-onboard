<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlatformTenantController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'api',
])->group(function () {
    Route::post('platform/login', fn () => app(AuthController::class, ['guard' => 'platform-admin'])->login())
        ->name('platform.api.login');
});

Route::middleware([
    'api',
    'auth:platform-admin',
])->group(function () {
    Route::post('platform/logout', fn () => app(AuthController::class, ['guard' => 'platform-admin'])->logout())
        ->name('platform.api.logout');
    Route::post('platform/refresh', fn () => app(AuthController::class, ['guard' => 'platform-admin'])->refresh())
        ->name('platform.api.refresh');
    Route::post('platform/me', fn () => app(AuthController::class, ['guard' => 'platform-admin'])->me())
        ->name('platform.api.me');

    // Central back-office: tenant management + per-tenant totals
    Route::get('platform/tenants', [PlatformTenantController::class, 'index'])
        ->name('platform.api.tenants.index');
    Route::post('platform/tenants', [PlatformTenantController::class, 'store'])
        ->name('platform.api.tenants.store');
    Route::delete('platform/tenants/{tenant}', [PlatformTenantController::class, 'destroy'])
        ->name('platform.api.tenants.destroy');
});
