<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayWithdrawOrdersDto
{
    public function __construct(
        public bool $status,
        public ?string $message,

        // Success
        public ?string $payment_id
    ) {}

    public static function fromApi(array $data): self
    {
        return new self(
            status: (bool) $data['status'] ?? false,
            payment_id: $data['payment_id'] ?? null,
            message: $data['message'] ?? null,
        );
    }
}
