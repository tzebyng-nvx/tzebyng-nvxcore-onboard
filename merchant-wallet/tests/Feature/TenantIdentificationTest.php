<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

beforeEach(function (): void {
    // These tests cover identification only. Database bootstrapping is covered
    // by tenant provisioning/isolation tests once tenant migrations exist.
    config()->set('tenancy.bootstrappers', []);

    Tenant::withoutEvents(function (): void {
        $firstTenant = Tenant::create(['id' => 'merchant-a']);
        $firstTenant->domains()->create(['domain' => 'merchant-a.merchant-wallet.test']);

        $secondTenant = Tenant::create(['id' => 'merchant-b']);
        $secondTenant->domains()->create(['domain' => 'merchant-b.merchant-wallet.test']);
    });

    Route::middleware(InitializeTenancyByDomain::class)
        ->get('/_test/domain-tenant', fn () => response()->json(['tenant_id' => tenant('id')]));

    Route::middleware(InitializeTenancyByRequestData::class)
        ->get('/api/_test/header-tenant', fn () => response()->json(['tenant_id' => tenant('id')]));
});

test('a tenant is resolved from its full domain, including a subdomain', function (): void {
    $this->getJson('http://merchant-a.merchant-wallet.test/_test/domain-tenant')
        ->assertOk()
        ->assertJsonPath('tenant_id', 'merchant-a');
});

test('a tenant is resolved from the X-Tenant header for API clients', function (): void {
    $this->getJson('/api/_test/header-tenant', ['X-Tenant' => 'merchant-b'])
        ->assertOk()
        ->assertJsonPath('tenant_id', 'merchant-b');
});
