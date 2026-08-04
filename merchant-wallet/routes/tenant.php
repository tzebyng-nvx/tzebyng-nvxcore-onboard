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
    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'tenantId' => tenant('id'),
            'title' => 'Tenant Demo',
        ]);
    })->name('tenant.home');

    Route::get('/login', function () {
        return Inertia::render('Login', [
            'tenantId' => tenant('id'),
            'title' => 'Tenant Login',
        ]);
    })->name('tenant.login');

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard', [
            'tenantId' => tenant('id'),
            'title' => 'Tenant Dashboard',
        ]);
    })->name('tenant.dashboard');

    Route::get('/deposit', function () {
        return Inertia::render('Deposit', [
            'tenantId' => tenant('id'),
            'title' => 'Tenant Deposit',
        ]);
    })->name('tenant.dashboard');
});
