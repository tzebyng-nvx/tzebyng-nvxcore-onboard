<?php

use Illuminate\Support\Facades\Route;

foreach (array_values(config('tenancy.central_domains')) as $index => $domain) {
    $isCanonical = $index === 0;

    Route::domain($domain)->group(function () use ($isCanonical) {
        // Central root: choose Platform Admin vs a tenant workspace.
        $landing = Route::inertia('/', 'platform.Landing');

        $login = Route::inertia('/login', 'platform.Login');

        // No server-side auth guard: this is a JWT SPA, the token lives in
        // localStorage. The page verifies the token client-side and redirects
        // to /login if missing/expired (same pattern as the player dashboard).
        $dashboard = Route::inertia('/dashboard', 'platform.Dashboard');

        if ($isCanonical) {
            $landing->name('platform.landing');
            $login->name('platform.login');
            $dashboard->name('platform.dashboard');
        }
    });
}
