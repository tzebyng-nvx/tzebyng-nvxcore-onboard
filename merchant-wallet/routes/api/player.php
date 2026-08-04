<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

Route::middleware([
    'api',
    InitializeTenancyByRequestData::class,
])->group(function () {
    Route::post('login', fn () => app(AuthController::class, ['guard' => 'api'])->login())
        ->name('player.login');
});

Route::middleware([
    'api',
    'auth:api',
    InitializeTenancyByRequestData::class,
])->group(function () {
    Route::controller(AuthController::class)
        ->middleware('api')
        ->group(function () {
            Route::post('logout', 'logout')->name('player.logout');
            Route::post('refresh', 'refresh')->name('player.refresh');
            Route::post('me', 'me')->name('player.me');
        });

    Route::get('/tenant-context', fn () => response()->json([
        'tenant_id' => tenant('id'),
    ]))->name('player.tenant-context');
});
