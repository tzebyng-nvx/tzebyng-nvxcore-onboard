<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| Non-browser clients identify their tenant with the X-Tenant header. Its
| value must be the central tenant ID, not a user-controlled domain value.
| Keep all tenant API endpoints inside this group so tenancy is initialized
| before authentication or any tenant database query is performed.
|
*/
Route::middleware([
    'api',
    InitializeTenancyByRequestData::class,
])->group(function () {
    // AUTHENTICATION ROUTES
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware([
    'api',
    'auth:api',
    InitializeTenancyByRequestData::class,
])->group(function () {
    // AUTHENTICATION ROUTES
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);

    // LOCAL TESTING ONLY, DO NOT USE IN PRODUCTION
    Route::get('/tenant-context', fn () => response()->json([
        'tenant_id' => tenant('id'),
    ]));
});
