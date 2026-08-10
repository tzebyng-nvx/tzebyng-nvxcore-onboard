<?php

use App\Enums\RoleName;
use App\Models\Admin;
use App\Models\User;
use App\Services\AccessControlService;

beforeEach(function () {
    createTenantWithSchema('access-test');
});

test('an admin with the tenant-admin role reaches the back office', function () {
    $admin = Admin::create([
        'name' => 'Role Admin',
        'email' => 'admin@access-test.test',
        'password' => 'secret123',
    ]);
    app(AccessControlService::class)->assign($admin, RoleName::TenantAdmin);

    $this->actingAs($admin, 'admin')
        ->withHeaders(['X-Tenant' => 'access-test'])
        ->getJson('/api/admin/payments/general-info')
        ->assertOk();
});

test('an admin without the tenant-admin role is forbidden from the back office', function () {
    $admin = Admin::create([
        'name' => 'No-Role Admin',
        'email' => 'norole@access-test.test',
        'password' => 'secret123',
    ]);

    $this->actingAs($admin, 'admin')
        ->withHeaders(['X-Tenant' => 'access-test'])
        ->getJson('/api/admin/payments/general-info')
        ->assertStatus(403);
});

test('self-registration grants the end-user role', function () {
    $this->withHeaders(['X-Tenant' => 'access-test'])
        ->postJson('/api/register', [
            'name' => 'New Player',
            'email' => 'new@access-test.test',
            'phone_number' => '0123456789',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertOk();

    $user = User::where('email', 'new@access-test.test')->firstOrFail();
    expect($user->hasRole(RoleName::EndUser->value))->toBeTrue();
});
