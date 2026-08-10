<?php

namespace App\Services\PaymentGateway\Dtos\Request;

readonly class PaymentGatewayCheckStatusRequestDto
{
    public function __construct(
        public string $username,
        public string $id,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'id' => $this->id,
        ];
    }
}
