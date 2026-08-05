<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayAuthDto
{
    public function __construct(
        public bool $status,
        public ?string $auth,
        public ?string $message
    ) {}

    public static function fromApi(array $data): self
    {
        return new self(
            status: (bool) $data['status'] ?? false,
            auth: $data['auth'] ?? null,
            message: $data['message'] ?? null,
        );
    }
}
