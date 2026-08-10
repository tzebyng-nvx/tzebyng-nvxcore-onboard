<?php

namespace App\Services\PaymentGateway\Dtos\Request;

readonly class PaymentGatewayGenerateOrdersRequestDto
{
    public function __construct(
        public string $username,
        public string $auth,
        public float $amount,
        public string $currency,
        public string $order_id,
        public string $email,
        public string $phone_number,
        public string $redirect_url,
        public string $payment_method,
        public string $bank_id,
        public ?string $callback_url = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'auth' => $this->auth,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'orderid' => $this->order_id,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'redirect_url' => $this->redirect_url,
            'pay_method' => $this->payment_method,
            'bank_id' => $this->bank_id,
            'callback_url' => $this->callback_url,
        ];
    }
}
