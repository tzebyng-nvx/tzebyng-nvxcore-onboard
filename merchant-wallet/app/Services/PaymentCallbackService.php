<?php

namespace App\Services;

use App\Enums\PaymentTransactionStatus;
use App\Enums\TransactionType;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentCallbackService
{
    public function handle(array $payload): void
    {
        Log::info('Payment callback received', [
            'payload' => $payload,
        ]);

        $paymentTransaction = PaymentTransaction::where(
            'order_id',
            $payload['order_id']
        )->first();

        if (! $paymentTransaction) {
            Log::warning('Payment callback unknown order', [
                'order_id' => $payload['order_id'],
            ]);

            return;
        }

        Log::info('Payment transaction found', [
            'id' => $paymentTransaction->id,
            'status' => $paymentTransaction->status,
            'tenant_id' => $paymentTransaction->tenant_id,
        ]);

        tenancy()->initialize($paymentTransaction->tenant_id);

        if (! $this->isValidToken($payload)) {
            Log::warning('Invalid callback token', [
                'order_id' => $payload['order_id'],
            ]);

            return;
        }

        Log::info('Callback token verified');

        $this->resolve($paymentTransaction, $payload['order_status']);
    }

    /**
     * Apply a gateway order status to a payment transaction and its tenant
     * transaction/wallet. Assumes tenancy is already initialized. Reused by
     * both the live callback and the reconciliation feature.
     */
    public function resolve(PaymentTransaction $paymentTransaction, string $orderStatus): void
    {
        $isCompleted = false;

        DB::transaction(function () use ($orderStatus, $paymentTransaction, &$isCompleted) {

            $lockedPaymentTransaction = PaymentTransaction::where(
                'id',
                $paymentTransaction->id
            )
                ->lockForUpdate()
                ->first();

            Log::info('Locked payment transaction', [
                'id' => $lockedPaymentTransaction->id,
                'status' => $lockedPaymentTransaction->status,
            ]);

            if ($lockedPaymentTransaction->status === PaymentTransactionStatus::Completed->value) {

                Log::info('Already completed callback ignored', [
                    'order_id' => $paymentTransaction->order_id,
                ]);

                return;
            }

            Log::info('Gateway status received', [
                'order_status' => $orderStatus,
            ]);

            if ($orderStatus === 'completed') {

                $lockedPaymentTransaction->update([
                    'status' => PaymentTransactionStatus::Completed->value,
                ]);

                $isCompleted = true;

            } else {

                $lockedPaymentTransaction->update([
                    'status' => PaymentTransactionStatus::Failed->value,
                ]);
            }

            Log::info('Payment transaction updated', [
                'new_status' => $lockedPaymentTransaction->fresh()->status,
            ]);

            $lockedTransaction = Transaction::where(
                'id',
                $paymentTransaction->order_id
            )
                ->lockForUpdate()
                ->first();

            if (! $lockedTransaction) {

                Log::error('Tenant transaction missing', [
                    'transaction_id' => $paymentTransaction->order_id,
                ]);

                return;
            }

            Log::info('Tenant transaction found', [
                'id' => $lockedTransaction->id,
                'type' => $lockedTransaction->type,
                'status' => $lockedTransaction->status,
            ]);

            if ($isCompleted) {

                Log::info('Processing wallet update', [
                    'transaction_type' => $lockedTransaction->type,
                    'amount' => $lockedTransaction->amount,
                ]);

                $wallet = Wallet::where('user_id', $lockedTransaction->user_id)
                    ->where('currency', $lockedTransaction->currency)
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    Log::error('Wallet not found', [
                        'user_id' => $lockedTransaction->user_id,
                        'currency' => $lockedTransaction->currency,
                    ]);

                    return;
                }

                if ($lockedTransaction->type === TransactionType::Deposit) {

                    $wallet->increment(
                        'balance',
                        $lockedTransaction->amount
                    );

                    Log::info('Deposit wallet credited', [
                        'balance' => $wallet->fresh()->balance,
                    ]);

                } elseif ($lockedTransaction->type === TransactionType::Withdrawal) {

                    $wallet->decrement(
                        'balance',
                        $lockedTransaction->amount
                    );

                    Log::info('Withdrawal wallet debited', [
                        'balance' => $wallet->fresh()->balance,
                    ]);
                }
            }

            $lockedTransaction->update([
                'status' => $orderStatus === 'completed'
                    ? 'success'
                    : 'failed',
            ]);

            Log::info('Tenant transaction updated', [
                'status' => $lockedTransaction->fresh()->status,
            ]);
        });
    }

    // can be used to check the token:
    //  php artisan tinker
    //  md5('SECRET_KEY' . 'order_id')
    private function isValidToken(array $payload): bool
    {
        $settings = PaymentGatewaySetting::first();
        $expected = md5($settings->secret_key.$payload['order_id']);

        return hash_equals($expected, $payload['token']);
    }
}
