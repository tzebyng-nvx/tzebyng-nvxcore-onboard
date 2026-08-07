<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    createTenantWithSchema('txn-test');

    $this->user = User::create([
        'name' => 'Filter User',
        'email' => 'filter@txn-test.com',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);

    $make = function (TransactionType $type, TransactionStatus $status) {
        Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'type' => $type,
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => $status,
            'payment_transaction_id' => (string) Str::uuid(),
            'bank_id' => 'BANK1',
        ]);
    };

    $make(TransactionType::Deposit, TransactionStatus::Success);
    $make(TransactionType::Deposit, TransactionStatus::Pending);
    $make(TransactionType::Withdrawal, TransactionStatus::Success);
    $make(TransactionType::Withdrawal, TransactionStatus::Failed);
});

function getTransactions(array $query = []): TestResponse
{
    return test()
        ->actingAs(test()->user, 'api')
        ->withHeaders(['X-Tenant' => 'txn-test'])
        ->getJson('/api/transactions?'.http_build_query($query));
}

test('without filters it returns all of the user transactions', function () {
    getTransactions()
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

test('it filters by type', function () {
    $response = getTransactions(['type' => 'deposit'])->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and(collect($response->json('data'))->pluck('type')->unique()->all())
        ->toBe(['deposit']);
});

test('it filters by status', function () {
    $response = getTransactions(['status' => 'success'])->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and(collect($response->json('data'))->pluck('status')->unique()->all())
        ->toBe(['success']);
});

test('it filters by type and status together', function () {
    $response = getTransactions(['type' => 'withdrawal', 'status' => 'failed'])->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.type'))->toBe('withdrawal')
        ->and($response->json('data.0.status'))->toBe('failed');
});

test('it rejects an invalid status filter', function () {
    getTransactions(['status' => 'not-a-status'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});
