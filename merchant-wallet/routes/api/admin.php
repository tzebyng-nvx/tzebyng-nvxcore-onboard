<?php

declare(strict_types=1);

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminUserController;
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

    // Back-office data + settings — gated by the tenant-admin role (access model).
    Route::middleware('role:tenant-admin,admin')->group(function () {
        // Dashboard data
        Route::get(
            'admin/payments/general-info',
            [AdminDashboardController::class, 'generalInfo']
        )->name('admin.payments.general-info');
        Route::get(
            'admin/transactions',
            [AdminDashboardController::class, 'transactions']
        )->name('admin.transactions');

        // Gateway merchant float balance
        Route::get(
            'admin/payments/float-balance',
            [PaymentGatewaySettingsController::class, 'floatBalance']
        )->name('admin.payments.float-balance');

        // Payment Gateway Settings
        Route::get(
            'admin/payment-gateway-settings',
            [PaymentGatewaySettingsController::class, 'show']
        );
        Route::put(
            'admin/payment-gateway-settings',
            [PaymentGatewaySettingsController::class, 'update']
        );

        // Bank list sync
        Route::get(
            'admin/bank-list/sync',
            [PaymentGatewaySettingsController::class, 'syncBankList']
        )->name('admin.bank-list.sync');

        // User management (tenant back office)
        Route::get('admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    });
});
