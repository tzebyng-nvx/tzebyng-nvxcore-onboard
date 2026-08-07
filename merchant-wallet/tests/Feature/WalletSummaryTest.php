<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;

beforeEach(function () {
    createTenantWithSchema('wallet-test');

    $this->user = User::create([
        'name' => 'Wallet User',
        'email' => 'wallet@wallet-test.com',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);

    Wallet::create(['user_id' => $this->user->id, 'currency' => 'MYR']);

    $make = function (TransactionType $type, TransactionStatus $status, string $amount) {
        Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => 'MYR',
            'status' => $status,
            'payment_transaction_id' => (string) Str::uuid(),
            'bank_id' => 'BANK1',
        ]);
    };

    // Successful money in/out plus noise that must be excluded from totals.
    $make(TransactionType::Deposit, TransactionStatus::Success, '100.00');
    $make(TransactionType::Deposit, TransactionStatus::Success, '50.00');
    $make(TransactionType::Deposit, TransactionStatus::Pending, '999.00');
    $make(TransactionType::Withdrawal, TransactionStatus::Success, '30.00');
    $make(TransactionType::Withdrawal, TransactionStatus::Failed, '888.00');
});

test('summary returns balance and only successful totals', function () {
    $response = $this->actingAs($this->user, 'api')
        ->withHeaders(['X-Tenant' => 'wallet-test'])
        ->getJson('/api/wallet/summary');

    $response->assertOk()
        ->assertJson([
            'currency' => 'MYR',
            'total_in' => '150.00',
            'total_out' => '30.00',
        ]);
});

test('summary auto-creates a wallet for a user without one', function () {
    $fresh = User::create([
        'name' => 'No Wallet',
        'email' => 'nowallet@wallet-test.com',
        'phone_number' => '0100000000',
        'password' => 'secret123',
    ]);

    $this->actingAs($fresh, 'api')
        ->withHeaders(['X-Tenant' => 'wallet-test'])
        ->getJson('/api/wallet/summary')
        ->assertOk()
        ->assertJson(['total_in' => '0', 'total_out' => '0']);

    expect(Wallet::where('user_id', $fresh->id)->exists())->toBeTrue();
});

test('the wallet endpoints require authentication', function () {
    $this->withHeaders(['X-Tenant' => 'wallet-test'])
        ->getJson('/api/wallet/summary')
        ->assertUnauthorized();
});
