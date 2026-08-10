<?php

use App\Enums\RoleName;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Support\Str;

beforeEach(function () {
    createTenantWithSchema('admin-dash');

    $this->admin = Admin::create([
        'name' => 'Dash Admin',
        'email' => 'admin@admin-dash.test',
        'password' => 'secret123',
    ]);
    app(AccessControlService::class)->assign($this->admin, RoleName::TenantAdmin);

    $this->user = User::create([
        'name' => 'Player',
        'email' => 'player@admin-dash.test',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);

    // 2 successful deposits, 1 pending withdrawal.
    foreach ([
        [TransactionType::Deposit, TransactionStatus::Success],
        [TransactionType::Deposit, TransactionStatus::Success],
        [TransactionType::Withdrawal, TransactionStatus::Pending],
    ] as [$type, $status]) {
        Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'type' => $type,
            'amount' => '50.00',
            'currency' => 'MYR',
            'status' => $status,
            'payment_transaction_id' => (string) Str::uuid(),
            'bank_id' => 'BANK1',
        ]);
    }
});

test('general-info returns tenant-wide payment counts', function () {
    $this->actingAs($this->admin, 'admin')
        ->withHeaders(['X-Tenant' => 'admin-dash'])
        ->getJson('/api/admin/payments/general-info')
        ->assertOk()
        ->assertJson([
            'total_payments' => 3,
            'total_pending' => 1,
            'total_successful' => 2,
            'currency' => 'MYR',
        ]);
});

test('admin transactions lists every user transaction with email', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->withHeaders(['X-Tenant' => 'admin-dash'])
        ->getJson('/api/admin/transactions')
        ->assertOk();

    $response->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.user_email', 'player@admin-dash.test')
        ->assertJsonStructure(['data' => [['id', 'merchant_order_id', 'user_email', 'type', 'amount', 'status', 'created_at']]]);
});

test('admin transactions can be filtered by type', function () {
    $this->actingAs($this->admin, 'admin')
        ->withHeaders(['X-Tenant' => 'admin-dash'])
        ->getJson('/api/admin/transactions?type=withdrawal')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'withdrawal');
});

test('the dashboard endpoints reject an unauthenticated request', function () {
    $this->withHeaders(['X-Tenant' => 'admin-dash'])
        ->getJson('/api/admin/payments/general-info')
        ->assertStatus(401);
});
