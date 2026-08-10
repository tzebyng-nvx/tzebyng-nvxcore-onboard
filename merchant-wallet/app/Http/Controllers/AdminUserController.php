<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        protected AdminUserService $adminUserService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        return response()->json($this->adminUserService->paginate($perPage));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->adminUserService->create($request->validated());

        return response()->json($user, 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return response()->json(
            $this->adminUserService->update($user, $request->validated())
        );
    }

    public function destroy(User $user): JsonResponse
    {
        $this->adminUserService->delete($user);

        return response()->json(['message' => 'User deleted.']);
    }
}
