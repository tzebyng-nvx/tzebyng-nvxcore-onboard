<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayCheckStatusDto
{
    public function __construct(
        public bool $status,
        public string $order_status,
        public string $order_datetime,
        public float $amount,
        public string $currency,
    ) {}

    public static function fromApi(array $data): self
    {
        return new self(
            status: (bool) $data['status'] ?? false,
            order_status: $data['order_status'],
            order_datetime: $data['order_datetime'],
            amount: (float) $data['amount'] ?? 0.0,
            currency: $data['currency'],
        );
    }
}
