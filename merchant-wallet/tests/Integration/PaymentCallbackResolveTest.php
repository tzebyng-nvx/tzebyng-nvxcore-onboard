<?php

use App\Enums\PaymentTransactionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\PaymentTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaymentCallbackService;
use Illuminate\Support\Str;

/**
 * Exercises the full resolution path shared by the live callback and the
 * reconciliation feature: central payment_transactions + tenant transactions
 * + wallet balance, inside one DB transaction.
 */
beforeEach(function () {
    $tenant = createTenantWithSchema('cb-test');
    tenancy()->initialize($tenant);

    $this->user = User::create([
        'name' => 'Wallet Owner',
        'email' => 'owner@cb-test.com',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);

    $this->wallet = Wallet::create([
        'user_id' => $this->user->id,
        'currency' => 'MYR',
    ]);

    $this->orderId = (string) Str::uuid();

    $this->transaction = Transaction::create([
        'id' => $this->orderId,
        'user_id' => $this->user->id,
        'type' => TransactionType::Deposit,
        'amount' => '100.00',
        'currency' => 'MYR',
        'status' => TransactionStatus::Pending,
        'payment_transaction_id' => (string) Str::uuid(),
        'bank_id' => 'BANK1',
    ]);

    $this->paymentTransaction = PaymentTransaction::create([
        'tenant_id' => $tenant->id,
        'order_id' => $this->orderId,
        'status' => PaymentTransactionStatus::Pending,
    ]);
});

afterEach(function () {
    tenancy()->end();
});

test('a completed deposit credits the wallet and marks everything resolved', function () {
    app(PaymentCallbackService::class)->resolve($this->paymentTransaction, 'completed');

    expect($this->wallet->fresh()->balance)->toBe('100.00')
        ->and($this->transaction->fresh()->status)->toBe(TransactionStatus::Success)
        ->and($this->paymentTransaction->fresh()->status)->toBe(PaymentTransactionStatus::Completed);
});

test('a failed deposit leaves the wallet untouched and marks it failed', function () {
    app(PaymentCallbackService::class)->resolve($this->paymentTransaction, 'failed');

    expect($this->wallet->fresh()->balance)->toBe('0.00')
        ->and($this->transaction->fresh()->status)->toBe(TransactionStatus::Failed)
        ->and($this->paymentTransaction->fresh()->status)->toBe(PaymentTransactionStatus::Failed);
});

test('an already-completed payment is not credited twice', function () {
    // First resolution credits the wallet.
    app(PaymentCallbackService::class)->resolve($this->paymentTransaction, 'completed');
    expect($this->wallet->fresh()->balance)->toBe('100.00');

    // A duplicate callback for the same order must be ignored.
    app(PaymentCallbackService::class)->resolve($this->paymentTransaction->fresh(), 'completed');
    expect($this->wallet->fresh()->balance)->toBe('100.00');
});
