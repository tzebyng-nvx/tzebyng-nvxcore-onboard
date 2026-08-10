<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayGeneralInfoDto
{
    /**
     * @param  list<PaymentGatewayCurrencyRate>  $currencies
     * @param  list<PaymentGatewayBank>|null  $banks
     */
    public function __construct(
        public array $currencies,
        public ?array $banks,
    ) {}
}
