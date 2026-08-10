<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayBank
{
    public function __construct(
        public string $currency,
        public string $bank_name,
        public string $id
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        return new self(
            currency: $data['currency'],
            bank_name: $data['bank_name'],
            id: $data['id']
        );
    }
}
