<?php

namespace App\Services\PaymentGateway\Dtos\Response;

readonly class PaymentGatewayBankListDto
{
    public function __construct(
        public bool $status,
        public array $data,

    ) {}

    public static function fromApi(array $data): self
    {
        $dataList = array_map(
            fn (array $arrayItem) => PaymentGatewayBank::fromApi($arrayItem),
            $data['data'] ?? []
        );

        return new self(
            status: (bool) ($data['status']),
            data: $dataList
        );
    }
}
