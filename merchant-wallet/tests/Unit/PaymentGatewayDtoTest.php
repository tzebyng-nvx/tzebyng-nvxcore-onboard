<?php

use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayBalanceDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayCheckStatusDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayGenerateOrdersDto;

test('check status dto maps a full gateway response', function () {
    $dto = PaymentGatewayCheckStatusDto::fromApi([
        'status' => true,
        'order_status' => 'completed',
        'order_datetime' => '2026-08-07 10:00:00',
        'amount' => '150.50',
        'currency' => 'MYR',
    ]);

    expect($dto->status)->toBeTrue()
        ->and($dto->order_status)->toBe('completed')
        ->and($dto->amount)->toBe(150.50)
        ->and($dto->currency)->toBe('MYR');
});

test('check status dto tolerates a pending response without order_status', function () {
    // The gateway omits order_status while an order is still pending; the dto
    // must not throw so reconciliation can treat it as "still pending".
    $dto = PaymentGatewayCheckStatusDto::fromApi([
        'status' => false,
    ]);

    expect($dto->status)->toBeFalse()
        ->and($dto->order_status)->toBeNull()
        ->and($dto->amount)->toBe(0.0)
        ->and($dto->currency)->toBe('');
});

test('balance dto maps the merchant float balance', function () {
    $dto = PaymentGatewayBalanceDto::fromApi([
        'status' => true,
        'balance' => '9999.99',
    ]);

    expect($dto->status)->toBeTrue()
        ->and($dto->balance)->toBe('9999.99');
});

test('generate orders dto maps success and failure shapes', function () {
    $success = PaymentGatewayGenerateOrdersDto::fromApi([
        'status' => true,
        'p_url' => 'https://gateway.test/pay/abc',
        'payment_id' => 'PAY-123',
    ]);

    expect($success->status)->toBeTrue()
        ->and($success->p_url)->toBe('https://gateway.test/pay/abc')
        ->and($success->payment_id)->toBe('PAY-123')
        ->and($success->message)->toBeNull();

    $failed = PaymentGatewayGenerateOrdersDto::fromApi([
        'status' => false,
        'message' => 'Insufficient merchant balance',
    ]);

    expect($failed->status)->toBeFalse()
        ->and($failed->p_url)->toBeNull()
        ->and($failed->message)->toBe('Insufficient merchant balance');
});
