<?php

use App\Enums\RoleName;
use App\Models\Admin;
use App\Models\User;
use App\Services\AccessControlService;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Form Request validation
|--------------------------------------------------------------------------
|
| Focused coverage of the Form Request rule sets — proving that bad input is
| rejected with the expected 422 validation errors before any business logic or
| gateway call runs. Happy-path behaviour for these endpoints lives in
| DepositTest, WithdrawalMoneyCorrectnessTest and AdminUserManagementTest.
|
*/

beforeEach(function () {
    createTenantWithSchema('validation-test');

    $this->player = User::create([
        'name' => 'Validation Player',
        'email' => 'player@validation-test.com',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);

    $this->admin = Admin::create([
        'name' => 'Validation Admin',
        'email' => 'admin@validation-test.com',
        'password' => 'secret123',
    ]);
    app(AccessControlService::class)->assign($this->admin, RoleName::TenantAdmin);
});

function actingPlayer(): TestCase
{
    return test()
        ->actingAs(test()->player, 'api')
        ->withHeaders(['X-Tenant' => 'validation-test']);
}

function actingTenantAdmin(): TestCase
{
    return test()
        ->actingAs(test()->admin, 'admin')
        ->withHeaders(['X-Tenant' => 'validation-test']);
}

// --- Deposit (TransactionCreateDepositRequest) -----------------------------

test('deposit rejects a request with no fields', function () {
    actingPlayer()->postJson('/api/payment/deposit', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount', 'currency', 'bank_id', 'payment_method']);
});

test('deposit rejects a non-numeric amount', function () {
    actingPlayer()->postJson('/api/payment/deposit', [
        'amount' => 'lots',
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
        'payment_method' => 'online_banking',
    ])->assertStatus(422)->assertJsonValidationErrors('amount');
});

test('deposit rejects a zero or negative amount', function (float $amount) {
    actingPlayer()->postJson('/api/payment/deposit', [
        'amount' => $amount,
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
        'payment_method' => 'online_banking',
    ])->assertStatus(422)->assertJsonValidationErrors('amount');
})->with([0, -1, -0.01]);

// --- Withdrawal (TransactionCreateWithdrawalRequest) -----------------------

test('withdrawal rejects a request with no fields', function () {
    actingPlayer()->postJson('/api/payment/withdraw', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'amount', 'currency', 'bank_id', 'holder_name', 'account_no',
        ]);
});

test('withdrawal rejects a zero or negative amount', function (float $amount) {
    actingPlayer()->postJson('/api/payment/withdraw', [
        'amount' => $amount,
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
        'holder_name' => 'Jane Doe',
        'account_no' => '1234567890',
    ])->assertStatus(422)->assertJsonValidationErrors('amount');
})->with([0, -5]);

test('withdrawal requires the payout destination fields', function () {
    actingPlayer()->postJson('/api/payment/withdraw', [
        'amount' => 50,
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
    ])->assertStatus(422)->assertJsonValidationErrors(['holder_name', 'account_no']);
});

// --- Admin create user (StoreUserRequest) ----------------------------------

test('admin create user rejects a request with no fields', function () {
    actingTenantAdmin()->postJson('/api/admin/users', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'phone_number', 'password']);
});

test('admin create user rejects a malformed email', function () {
    actingTenantAdmin()->postJson('/api/admin/users', [
        'name' => 'New User',
        'email' => 'not-an-email',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

test('admin create user rejects a duplicate email', function () {
    actingTenantAdmin()->postJson('/api/admin/users', [
        'name' => 'Dup User',
        'email' => $this->player->email,
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

test('admin create user rejects a password shorter than six characters', function () {
    actingTenantAdmin()->postJson('/api/admin/users', [
        'name' => 'Short Pw',
        'email' => 'shortpw@validation-test.com',
        'phone_number' => '0123456789',
        'password' => '123',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

// --- Admin update user (UpdateUserRequest) ---------------------------------

test('admin update user allows keeping the same email (unique ignores self)', function () {
    actingTenantAdmin()->putJson("/api/admin/users/{$this->player->id}", [
        'email' => $this->player->email,
    ])->assertOk();
});

test('admin update user rejects an email already taken by another user', function () {
    $other = User::create([
        'name' => 'Other', 'email' => 'other@validation-test.com',
        'phone_number' => '0123456789', 'password' => 'secret123',
    ]);

    actingTenantAdmin()->putJson("/api/admin/users/{$this->player->id}", [
        'email' => $other->email,
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

test('admin update user rejects a too-short password when provided', function () {
    actingTenantAdmin()->putJson("/api/admin/users/{$this->player->id}", [
        'password' => '123',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});
