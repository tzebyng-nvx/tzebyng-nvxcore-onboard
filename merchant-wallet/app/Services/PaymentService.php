<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Exceptions\GatewayRejectedException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Services\PaymentGateway\Contracts\PaymentGatewayContract;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayGenerateOrdersDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayWithdrawOrdersDto;

/**
 * Orchestrates deposit/withdrawal actions across the payment gateway and the
 * transaction/wallet layer. Kept separate from TransactionService so that
 * read-only transaction/wallet consumers (dashboard, listings) don't pull in
 * the gateway HTTP client just to be constructed.
 */
class PaymentService
{
    public function __construct(
        protected PaymentGatewayContract $paymentGateway,
        protected TransactionService $transactionService
    ) {}

    /**
     * Full deposit flow: persist the transaction, ask the gateway to initiate
     * the order, and either record the gateway payment id or mark it failed.
     *
     * @param  array{amount:mixed,currency:string,bank_id?:string,payment_method?:string}  $data
     *
     * @throws GatewayRejectedException when the gateway declines the order
     */
    public function processDeposit(User $user, array $data): PaymentGatewayGenerateOrdersDto
    {
        $transaction = $this->transactionService->createDeposit([
            ...$data,
            'user_id' => $user->id,
        ]);

        $result = $this->paymentGateway->createDeposit([
            ...$data,
            'order_id' => $transaction->id,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
        ]);

        if (! $result->status) {
            $this->transactionService->updateStatus($transaction, TransactionStatus::Failed);

            throw new GatewayRejectedException($result->message ?? 'Deposit could not be initiated.');
        }

        $this->transactionService->updatePaymentId([$transaction->id, $result->payment_id]);

        return $result;
    }

    /**
     * Full withdrawal flow: reserve funds (throws if insufficient), ask the
     * gateway to initiate the payout, and either record the payment id or
     * release the hold and mark the transaction failed.
     *
     * @param  array{amount:mixed,currency:string,bank_id:string,holder_name:string,account_no:string}  $data
     *
     * @throws InsufficientBalanceException when funds are insufficient
     * @throws GatewayRejectedException when the gateway declines the order
     */
    public function processWithdrawal(User $user, array $data): PaymentGatewayWithdrawOrdersDto
    {
        $transaction = $this->transactionService->createWithdrawal([
            ...$data,
            'user_id' => $user->id,
        ]);

        $result = $this->paymentGateway->createWithdrawal([
            ...$data,
            'order_id' => $transaction->id,
            'holder_name' => $data['holder_name'],
            'account_no' => $data['account_no'],
        ]);

        if (! $result->status) {
            $this->transactionService->failWithdrawal($transaction);

            throw new GatewayRejectedException($result->message ?? 'Withdrawal could not be initiated.');
        }

        $this->transactionService->updatePaymentId([$transaction->id, $result->payment_id]);

        return $result;
    }
}
