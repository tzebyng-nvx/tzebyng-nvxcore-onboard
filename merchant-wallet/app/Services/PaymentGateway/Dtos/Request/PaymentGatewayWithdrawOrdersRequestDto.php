<?php

namespace App\Services\PaymentGateway\Dtos\Request;

readonly class PaymentGatewayWithdrawOrdersRequestDto
{
    public function __construct(
        public string $auth,
        public float $amount,
        public string $currency,
        public string $order_id,
        public string $bank_id,
        public string $holder_name,
        public string $account_no,
        public ?string $callback_url = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'auth' => $this->auth,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'orderid' => $this->order_id,
            'bank_id' => $this->bank_id,
            'holder_name' => $this->holder_name,
            'account_no' => $this->account_no,
            'callback_url' => $this->callback_url,
        ];
    }
}
