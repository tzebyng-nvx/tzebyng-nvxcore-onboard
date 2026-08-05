<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayCurrencyRate
{
    public function __construct(
        public string $currency,
        public float $min,
        public float $max
    ) {}

    public static function fromApi(array $data): self
    {
        return new self(
            currency: $data['currency'],
            min: (float) $data['min'],
            max: (float) $data['max'],
        );
    }
}
