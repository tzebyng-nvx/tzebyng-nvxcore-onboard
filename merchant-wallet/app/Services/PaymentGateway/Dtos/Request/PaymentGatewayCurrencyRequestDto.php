<?php

namespace App\Services\PaymentGateway\Dtos\Request;

readonly class PaymentGatewayCurrencyRequestDto
{
    public function __construct(
        public string $username
    ) {}

    public function toArray(): array
    {
        return [
            'username' => $this->username,
        ];
    }
}
