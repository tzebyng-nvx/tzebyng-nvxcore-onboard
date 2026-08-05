<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayGeneralInfoDto
{
    public function __construct(
        public array $currencies,
        public array $banks,
    ) {}
}
