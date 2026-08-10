<?php

use App\Enums\RoleName;
use App\Models\Admin;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\PaymentGateway\Contracts\PaymentGatewayContract;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayBankListDto;
use Tests\TestCase;

beforeEach(function () {
    createTenantWithSchema('admin-users');

    $this->admin = Admin::create([
        'name' => 'BO Admin',
        'email' => 'admin@admin-users.test',
        'password' => 'secret123',
    ]);
    app(AccessControlService::class)->assign($this->admin, RoleName::TenantAdmin);
});

function actingAdmin(): TestCase
{
    return test()
        ->actingAs(test()->admin, 'admin')
        ->withHeaders(['X-Tenant' => 'admin-users']);
}

test('admin can list users', function () {
    User::create([
        'name' => 'Existing', 'email' => 'existing@admin-users.test',
        'phone_number' => '0123456789', 'password' => 'secret123',
    ]);

    actingAdmin()->getJson('/api/admin/users')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can create a user (with wallet + end-user role)', function () {
    actingAdmin()->postJson('/api/admin/users', [
        'name' => 'Created User',
        'email' => 'created@admin-users.test',
        'phone_number' => '0111111111',
        'password' => 'secret123',
    ])->assertStatus(201);

    $user = User::where('email', 'created@admin-users.test')->firstOrFail();
    expect($user->hasRole(RoleName::EndUser->value))->toBeTrue()
        ->and($user->fresh())->not->toBeNull();
});

test('admin can update a user', function () {
    $user = User::create([
        'name' => 'Old Name', 'email' => 'up@admin-users.test',
        'phone_number' => '0123456789', 'password' => 'secret123',
    ]);

    actingAdmin()->putJson("/api/admin/users/{$user->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('name', 'New Name');
});

test('admin can delete a user', function () {
    $user = User::create([
        'name' => 'Del', 'email' => 'del@admin-users.test',
        'phone_number' => '0123456789', 'password' => 'secret123',
    ]);

    actingAdmin()->deleteJson("/api/admin/users/{$user->id}")->assertOk();
    expect(User::find($user->id))->toBeNull();
});

test('a non tenant-admin cannot manage users', function () {
    $plain = Admin::create([
        'name' => 'Plain', 'email' => 'plain@admin-users.test', 'password' => 'secret123',
    ]);

    test()->actingAs($plain, 'admin')
        ->withHeaders(['X-Tenant' => 'admin-users'])
        ->getJson('/api/admin/users')
        ->assertStatus(403);
});

test('admin can sync the gateway bank list', function () {
    $this->mock(PaymentGatewayContract::class)
        ->shouldReceive('getBankList')
        ->twice()
        ->andReturn(new PaymentGatewayBankListDto(status: true, data: [['id' => 'BANK1', 'name' => 'Test Bank']]));

    actingAdmin()->getJson('/api/admin/bank-list/sync')
        ->assertOk()
        ->assertJsonStructure(['deposit', 'withdraw']);
});
