<?php

use App\Services\PaymentGateway\Enums\PaymentGatewayEndpoint;

test('gateway endpoint paths are stable', function () {
    // Guards against accidental path changes that would silently break
    // live integration with the payment gateway.
    expect(PaymentGatewayEndpoint::AUTH->value)->toBe('/merchant/auth')
        ->and(PaymentGatewayEndpoint::ORDERS_GENERATE->value)->toBe('/merchant/generate_orders')
        ->and(PaymentGatewayEndpoint::ORDERS_WITHDRAW->value)->toBe('/merchant/withdraw_orders')
        ->and(PaymentGatewayEndpoint::CHECK_STATUS->value)->toBe('/merchant/check_status')
        ->and(PaymentGatewayEndpoint::CHECK_STATUS_WITHDRAW->value)->toBe('/merchant/check_withdraw_status')
        ->and(PaymentGatewayEndpoint::BALANCE->value)->toBe('/wallet/get_balance');
});
