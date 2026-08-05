<?php

namespace App\Services\PaymentGateway\Dtos\Request;

readonly class PaymentGatewayAuthRequestDto
{
    public function __construct(
        public string $username,
        public string $api_key
    ) {}

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'api_key' => $this->api_key,
        ];
    }
}
