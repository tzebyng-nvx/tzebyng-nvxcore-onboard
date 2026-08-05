<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayGenerateOrdersDto
{
    public function __construct(
        public bool $status,

        // Success
        public ?string $p_url,
        public ?string $payment_id,

        // Failed
        public ?string $message
    ) {}

    public static function fromApi(array $data): self
    {
        return new self(
            status: (bool) $data['status'] ?? false,
            p_url: $data['p_url'] ?? null,
            payment_id: $data['payment_id'] ?? null,
            message: $data['message'] ?? null,
        );
    }
}
