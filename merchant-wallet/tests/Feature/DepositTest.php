<?php

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayGenerateOrdersDto;
use App\Services\PaymentGateway\PaymentGatewayService;

beforeEach(function () {
    createTenantWithSchema('deposit-test');

    $this->user = User::create([
        'name' => 'Deposit User',
        'email' => 'deposit@deposit-test.com',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);
});

function depositPayload(array $overrides = []): array
{
    return array_merge([
        'amount' => 100,
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
        'payment_method' => 'online_banking',
    ], $overrides);
}

test('a successful deposit persists a pending transaction with the gateway payment id', function () {
    $this->mock(PaymentGatewayService::class)
        ->shouldReceive('createDeposit')
        ->once()
        ->andReturn(new PaymentGatewayGenerateOrdersDto(
            status: true,
            p_url: 'https://gateway.test/pay/abc',
            payment_id: 'PAY-123',
            message: null,
        ));

    $response = $this->actingAs($this->user, 'api')
        ->withHeaders(['X-Tenant' => 'deposit-test'])
        ->postJson('/api/payment/deposit', depositPayload());

    $response->assertOk()->assertJson(['p_url' => 'https://gateway.test/pay/abc']);

    $txn = Transaction::where('user_id', $this->user->id)->first();
    expect($txn)->not->toBeNull()
        ->and($txn->status)->toBe(TransactionStatus::Pending)
        ->and($txn->payment_id)->toBe('PAY-123');
});

test('a rejected deposit marks the transaction failed and returns 422', function () {
    $this->mock(PaymentGatewayService::class)
        ->shouldReceive('createDeposit')
        ->once()
        ->andReturn(new PaymentGatewayGenerateOrdersDto(
            status: false,
            p_url: null,
            payment_id: null,
            message: 'Gateway declined',
        ));

    $response = $this->actingAs($this->user, 'api')
        ->withHeaders(['X-Tenant' => 'deposit-test'])
        ->postJson('/api/payment/deposit', depositPayload());

    $response->assertStatus(422)->assertJson(['message' => 'Gateway declined']);

    $txn = Transaction::where('user_id', $this->user->id)->first();
    expect($txn->status)->toBe(TransactionStatus::Failed);
});

test('deposit validates the payment method', function () {
    $this->actingAs($this->user, 'api')
        ->withHeaders(['X-Tenant' => 'deposit-test'])
        ->postJson('/api/payment/deposit', depositPayload(['payment_method' => 'crypto']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_method');
});
