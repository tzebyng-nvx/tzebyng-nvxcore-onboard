<?php

use Illuminate\Support\Facades\Route;

// Central (platform) routes. Scoped to the central domains so their URIs do not
// collide with the tenant routes, which register the same paths without a domain
// constraint in routes/tenant.php (the tenant routes load later and would
// otherwise overwrite these in the route collection).
//
// Names are attached only for the first (canonical) central domain to keep route
// names unique; the remaining domains register unnamed copies purely for matching.
foreach (array_values(config('tenancy.central_domains')) as $index => $domain) {
    $isCanonical = $index === 0;

    Route::domain($domain)->group(function () use ($isCanonical) {
        $welcome = Route::inertia('/', 'Welcome');
        $login = Route::inertia('/login', 'Login');

        Route::middleware('auth:platform-admin')->group(function () use ($isCanonical) {
            $dashboard = Route::inertia('/dashboard', 'Dashboard');

            if ($isCanonical) {
                $dashboard->name('platform.dashboard');
            }
        });

        if ($isCanonical) {
            $welcome->name('welcome');
            $login->name('platform.login');
        }
    });
}
