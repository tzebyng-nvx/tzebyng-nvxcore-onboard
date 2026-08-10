<?php

namespace App\Services\PaymentGateway\Dtos\Request;

readonly class PaymentGatewayCurrencyRequestDto
{
    public function __construct(
        public string $username
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
        ];
    }
}
