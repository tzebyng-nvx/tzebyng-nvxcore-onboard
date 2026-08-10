<?php

namespace App\Services\PaymentGateway\Dtos\Callback;

use App\Enums\TransactionType;
use InvalidArgumentException;

readonly class PaymentGatewayCallbackDto
{
    public function __construct(
        public string $orderId,
        public string $amount,
        public string $currency,
        public string $orderStatus,   // 'completed' | 'fail'
        public bool $status,
        public string $charge,
        public string $token,
        public ?string $name,
        public TransactionType $type,

        // Deposit-only — null on withdrawal callbacks
        public ?string $ccno = null,
        public ?string $mode = null,
        public ?string $paymentType = null,
        public ?string $username = null,
        public ?string $porderId = null,

        // Withdrawal-only — null on deposit callbacks
        public ?string $remarks = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        return new self(
            orderId: (string) ($data['order_id'] ?? throw new InvalidArgumentException('Missing order_id')),
            amount: (string) ($data['amount'] ?? throw new InvalidArgumentException('Missing amount')),
            currency: (string) ($data['currency'] ?? throw new InvalidArgumentException('Missing currency')),
            orderStatus: (string) ($data['order_status'] ?? throw new InvalidArgumentException('Missing order_status')),
            status: (bool) ($data['status'] ?? false),
            charge: (string) ($data['charge'] ?? '0.0000'),
            token: (string) ($data['token'] ?? throw new InvalidArgumentException('Missing token')),
            name: $data['name'] ?? null,
            type: TransactionType::from(
                (string) ($data['type'] ?? throw new InvalidArgumentException('Missing type'))
            ),

            ccno: $data['ccno'] ?? null,
            mode: $data['mode'] ?? null,
            paymentType: $data['payment_type'] ?? null,
            username: $data['username'] ?? null,
            porderId: isset($data['porder_id']) ? (string) $data['porder_id'] : null,

            remarks: $data['remarks'] ?? null,
        );
    }

    public function isCompleted(): bool
    {
        return $this->orderStatus === 'completed';
    }

    public function isDeposit(): bool
    {
        return $this->type === TransactionType::Deposit;
    }

    public function isWithdrawal(): bool
    {
        return $this->type === TransactionType::Withdrawal;
    }

    /**
     * Value the gateway's token is computed over — MD5("Secret_key" + "Order_id")
     * per the gateway's documented scheme.
     */
    public function tokenSubject(): string
    {
        return $this->orderId;
    }
}
