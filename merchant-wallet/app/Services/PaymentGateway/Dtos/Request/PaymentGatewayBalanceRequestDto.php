<?php

namespace App\Services\PaymentGateway\Dtos\Request;

readonly class PaymentGatewayBalanceRequestDto
{
    public function __construct(
        public string $auth,
        public string $currency
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'auth' => $this->auth,
            'currency' => $this->currency,
        ];
    }
}
