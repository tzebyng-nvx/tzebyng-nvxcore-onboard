<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
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
        ->group(function () {
            Route::post('logout', 'logout')->name('player.logout');
            Route::post('refresh', 'refresh')->name('player.refresh');
            Route::post('me', 'me')->name('player.me');
        });

    Route::prefix('payment')->controller(PaymentController::class)
        ->group(function () {
            Route::get('general-info', 'getGeneralInfo')->name('player.general-info');
            Route::get('currency', 'getCurrency')->name('player.currency');
            Route::get('deposit-bank-list', 'getDepositBankList')->name('player.deposit-bank-list');
        });

    Route::get('/tenant-context', fn () => response()->json([
        'tenant_id' => tenant('id'),
    ]))->name('player.tenant-context');
});
