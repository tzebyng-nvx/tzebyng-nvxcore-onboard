<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayBalanceDto
{
    public function __construct(
        public bool $status,
        public string $balance,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        return new self(
            status: (bool) ($data['status'] ?? false),
            balance: $data['balance'],
        );
    }
}
