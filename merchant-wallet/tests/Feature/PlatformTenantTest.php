<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\PlatformAdmin;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    // createTenantWithSchema builds one tenant ('plat-test') + tenant tables.
    createTenantWithSchema('plat-test');

    $this->platformAdmin = PlatformAdmin::create([
        'name' => 'Platform Admin',
        'email' => 'platform@example.com',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);

    $this->user = User::create([
        'name' => 'Player',
        'email' => 'player@plat-test.test',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);

    foreach ([
        [TransactionType::Deposit, '100.00'],
        [TransactionType::Withdrawal, '30.00'],
    ] as [$type, $amount]) {
        Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => 'MYR',
            'status' => TransactionStatus::Success,
            'payment_transaction_id' => (string) Str::uuid(),
            'bank_id' => 'BANK1',
        ]);
    }
});

test('platform admin sees tenants with per-tenant totals', function () {
    $response = $this->actingAs($this->platformAdmin, 'platform-admin')
        ->getJson('/api/platform/tenants')
        ->assertOk();

    $tenant = collect($response->json('data'))->firstWhere('id', 'plat-test');

    expect($tenant)->not->toBeNull()
        ->and($tenant['user_count'])->toBe(1)
        ->and($tenant['total_in'])->toBe('100.00')
        ->and($tenant['total_out'])->toBe('30.00');
});

test('tenant listing requires platform admin auth', function () {
    $this->getJson('/api/platform/tenants')->assertStatus(401);
});

test('creating a tenant validates the payload', function () {
    $this->actingAs($this->platformAdmin, 'platform-admin')
        ->postJson('/api/platform/tenants', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id', 'domain', 'admin_email', 'admin_password']);
});
