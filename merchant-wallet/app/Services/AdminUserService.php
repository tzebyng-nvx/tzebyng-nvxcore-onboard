<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

/**
 * Tenant back-office management of end users. Runs in tenant context (the admin
 * routes initialize tenancy), so it operates on the tenant's users table.
 */
class AdminUserService
{
    public function __construct(
        protected WalletService $walletService,
        protected AccessControlService $accessControlService
    ) {}

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return User::query()->latest()->paginate($perPage);
    }

    /**
     * @param  array{name:string,email:string,phone_number:string,password:string}  $data
     */
    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'password' => Hash::make($data['password']),
        ]);

        $this->walletService->getOrCreateWallet($user->id);
        $this->accessControlService->assign($user, RoleName::EndUser);

        return $user;
    }

    /**
     * @param  array{name?:string,email?:string,phone_number?:string,password?:string}  $data
     */
    public function update(User $user, array $data): User
    {
        $attributes = array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
        ], fn ($value) => $value !== null);

        if (! empty($data['password'])) {
            $attributes['password'] = Hash::make($data['password']);
        }

        $user->update($attributes);

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
