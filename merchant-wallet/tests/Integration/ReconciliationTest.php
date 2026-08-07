<?php

use App\Enums\PaymentTransactionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\PaymentTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayCheckStatusDto;
use App\Services\PaymentGateway\PaymentGatewayService;
use App\Services\ReconcilePendingTransactionsService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = createTenantWithSchema('recon-test');

    $this->user = User::create([
        'name' => 'Recon User',
        'email' => 'recon@recon-test.com',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);

    $this->wallet = Wallet::create(['user_id' => $this->user->id, 'currency' => 'MYR']);

    $this->orderId = (string) Str::uuid();

    $this->transaction = Transaction::create([
        'id' => $this->orderId,
        'user_id' => $this->user->id,
        'type' => TransactionType::Deposit,
        'amount' => '75.00',
        'currency' => 'MYR',
        'status' => TransactionStatus::Pending,
        'payment_transaction_id' => (string) Str::uuid(),
        'bank_id' => 'BANK1',
    ]);

    $this->paymentTransaction = PaymentTransaction::create([
        'tenant_id' => $this->tenant->id,
        'order_id' => $this->orderId,
        'status' => PaymentTransactionStatus::Pending,
    ]);
});

afterEach(fn () => tenancy()->end());

function checkStatusDto(string $orderStatus): PaymentGatewayCheckStatusDto
{
    return new PaymentGatewayCheckStatusDto(
        status: true,
        order_status: $orderStatus,
        order_datetime: '2026-08-07 10:00:00',
        amount: 75.0,
        currency: 'MYR',
    );
}

test('a stale pending deposit reported completed is resolved and credited', function () {
    $this->mock(PaymentGatewayService::class)
        ->shouldReceive('checkStatus')
        ->once()
        ->with($this->orderId)
        ->andReturn(checkStatusDto('completed'));

    $summary = app(ReconcilePendingTransactionsService::class)->reconcile(0);

    expect($summary['resolved'])->toBe(1)
        ->and($this->wallet->fresh()->balance)->toBe('75.00')
        ->and($this->transaction->fresh()->status)->toBe(TransactionStatus::Success)
        ->and($this->paymentTransaction->fresh()->status)->toBe(PaymentTransactionStatus::Completed);
});

test('a stale pending deposit still pending at the gateway is skipped', function () {
    $this->mock(PaymentGatewayService::class)
        ->shouldReceive('checkStatus')
        ->once()
        ->andReturn(checkStatusDto('pending'));

    $summary = app(ReconcilePendingTransactionsService::class)->reconcile(0);

    expect($summary['skipped'])->toBe(1)
        ->and($this->wallet->fresh()->balance)->toBe('0.00')
        ->and($this->paymentTransaction->fresh()->status)->toBe(PaymentTransactionStatus::Pending);
});

test('a stale pending deposit reported failed is marked failed', function () {
    $this->mock(PaymentGatewayService::class)
        ->shouldReceive('checkStatus')
        ->once()
        ->andReturn(checkStatusDto('failed'));

    $summary = app(ReconcilePendingTransactionsService::class)->reconcile(0);

    expect($summary['resolved'])->toBe(1)
        ->and($this->transaction->fresh()->status)->toBe(TransactionStatus::Failed)
        ->and($this->paymentTransaction->fresh()->status)->toBe(PaymentTransactionStatus::Failed);
});
