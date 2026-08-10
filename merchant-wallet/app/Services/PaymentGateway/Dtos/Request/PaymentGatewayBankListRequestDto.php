<?php

namespace App\Services\PaymentGateway\Dtos\Request;

readonly class PaymentGatewayBankListRequestDto
{
    public function __construct(
        public string $username,
        public ?string $currency = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'currency' => $this->currency,
        ];
    }
}
