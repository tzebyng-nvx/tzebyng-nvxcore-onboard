<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
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
});
