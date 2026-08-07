<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // ---------------------------------------------------------------
    // Player pages
    // ---------------------------------------------------------------
    $playerPages = [
        '' => ['component' => 'player/Login',         'title' => 'Tenant Login', 'name' => 'home'],
        'login' => ['component' => 'player/Login',     'title' => 'Tenant Login',     'name' => 'login'],
        'register' => ['component' => 'player/Register', 'title' => 'Create Account', 'name' => 'register'],
        'dashboard' => ['component' => 'player.Dashboard', 'title' => 'Tenant Dashboard', 'name' => 'dashboard'],
        'deposit' => ['component' => 'player.Deposit',   'title' => 'Tenant Deposit',   'name' => 'deposit'],
        'withdraw' => ['component' => 'player.Withdraw',   'title' => 'Tenant Withdraw',   'name' => 'withdraw'],
        'transaction' => ['component' => 'player.Transaction',   'title' => 'Tenant Transaction',   'name' => 'transaction'],
    ];

    foreach ($playerPages as $uri => $page) {
        Route::get("/{$uri}", fn () => Inertia::render($page['component'], [
            'tenantId' => tenant('id'),
            'title' => $page['title'],
        ]))->name("tenant.{$page['name']}");
    }

    // ---------------------------------------------------------------
    // Admin pages — same tenant domain, separate prefix + page folder
    // ---------------------------------------------------------------
    $adminPages = [
        'login' => ['component' => 'admin.Login',     'title' => 'Admin Login',     'name' => 'login'],
        'dashboard' => ['component' => 'admin.Dashboard', 'title' => 'Admin Dashboard', 'name' => 'dashboard'],
        'gateway-settings' => ['component' => 'admin.GatewaySettings', 'title' => 'Gateway Settings', 'name' => 'gateway-settings'],
    ];

    Route::prefix('admin')->group(function () use ($adminPages) {
        foreach ($adminPages as $uri => $page) {
            Route::get("/{$uri}", fn () => Inertia::render($page['component'], [
                'tenantId' => tenant('id'),
                'title' => $page['title'],
            ]))->name("tenant.admin.{$page['name']}");
        }
    });
});
