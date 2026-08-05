<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayCurrencyDto
{
    public function __construct(
        public bool $status,
        public array $rate,

    ) {}

    public static function fromApi(array $data): self
    {
        $rates = array_map(
            fn (array $arrayItem) => PaymentGatewayCurrencyRate::fromApi($arrayItem),
            $data['rate'] ?? []
        );

        return new self(
            status: (bool) ($data['status']),
            rate: $rates
        );
    }
}
