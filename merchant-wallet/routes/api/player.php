<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

Route::middleware([
    'api',
    InitializeTenancyByRequestData::class,
])->group(function () {
    Route::post('login', fn () => app(AuthController::class, ['guard' => 'api'])->login())
        ->name('player.login');
    Route::post('register', fn () => app(AuthController::class, ['guard' => 'api'])->register())
        ->name('player.register');
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

            // Info Retrieval
            Route::get('general-info', 'getGeneralInfo')->name('player.general-info');
            Route::get('currency', 'getCurrency')->name('player.currency');
            Route::get('deposit-bank-list', 'getDepositBankList')->name('player.deposit-bank-list');

            // Actions
            Route::post('deposit', 'deposit')->name('player.deposit');
            Route::post('withdraw', 'withdraw')->middleware('throttle:withdraw')->name('player.withdraw');
        });

    Route::prefix('transactions')->controller(TransactionController::class)
        ->group(function () {
            Route::get('/', 'index')->name('player.transaction.index');
        });

    Route::prefix('wallet')->controller(WalletController::class)
        ->group(function () {
            Route::get('/', 'index')->name('player.wallet.index');
            Route::get('summary', 'summary')->name('player.wallet.summary');
        });

    Route::get('/tenant-context', fn () => response()->json([
        'tenant_id' => tenant('id'),
    ]))->name('player.tenant-context');
});
