<?php

use App\Exceptions\GatewayRejectedException;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/platform.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // spatie/laravel-permission middleware aliases for the access model.
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A rejected payment => 422, not 500. Rendered uniformly so controllers
        // stay thin and don't repeat try/catch branching.
        $exceptions->render(fn (InsufficientBalanceException|GatewayRejectedException $e) => response()->json([
            'status' => false,
            'message' => $e->getMessage(),
        ], 422));

        // An unknown/unprovisioned tenant domain should land on a friendly
        // "workspace not found" page rather than the raw tenancy exception.
        $exceptions->render(function (TenantCouldNotBeIdentifiedOnDomainException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Tenant not found for this domain.',
                ], 404);
            }

            // Strip the leading (tenant) label to reach the central parent
            // domain, e.g. acme1.merchant-wallet.test -> merchant-wallet.test.
            $centralDomains = config('tenancy.central_domains', []);
            $parts = explode('.', $request->getHost());
            $parent = count($parts) > 1 ? implode('.', array_slice($parts, 1)) : $request->getHost();
            $centralDomain = in_array($parent, $centralDomains, true) ? $parent : ($centralDomains[0] ?? $parent);

            $port = $request->getPort();
            $host = in_array($port, [80, 443, null], true) ? $centralDomain : "{$centralDomain}:{$port}";

            return Inertia::render('errors/TenantNotFound', [
                'host' => $request->getHost(),
                'centralUrl' => "{$request->getScheme()}://{$host}/",
            ])->toResponse($request)->setStatusCode(404);
        });
    })->create();
