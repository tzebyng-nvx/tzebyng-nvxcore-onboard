<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantRequest;
use App\Models\Tenant;
use App\Services\PlatformTenantService;
use Illuminate\Http\JsonResponse;

class PlatformTenantController extends Controller
{
    public function __construct(
        protected PlatformTenantService $platformTenantService
    ) {}

    /**
     * List every tenant with its domain and per-tenant deposit/withdrawal totals.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->platformTenantService->listWithTotals(),
        ]);
    }

    /**
     * Create a tenant (+ domain + initial tenant admin).
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->platformTenantService->create($request->validated());

        return response()->json([
            'id' => $tenant->getTenantKey(),
            'message' => 'Tenant created.',
        ], 201);
    }

    /**
     * Delete a tenant and its database.
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->platformTenantService->delete($tenant);

        return response()->json(['message' => 'Tenant deleted.']);
    }
}
