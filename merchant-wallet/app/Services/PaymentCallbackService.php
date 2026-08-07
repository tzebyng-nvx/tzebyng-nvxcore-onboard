<?php

namespace App\Services;

use App\Enums\PaymentTransactionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentCallbackService
{
    /**
     * Gateway vocabulary for a settled-good order. External value, so it stays
     * a string here; we map it onto our own enums for anything we persist.
     */
    private const GATEWAY_STATUS_COMPLETED = 'completed';

    public function __construct(
        protected WalletService $walletService
    ) {}

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
        $isCompleted = $orderStatus === self::GATEWAY_STATUS_COMPLETED;

        DB::transaction(function () use ($orderStatus, $paymentTransaction, $isCompleted) {

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

            // Idempotency: once a payment reaches a terminal state the funds
            // have already been applied. Bail before touching the wallet again
            // so a replayed callback can't double-credit or double-release.
            if (in_array($lockedPaymentTransaction->status, [
                PaymentTransactionStatus::Completed,
                PaymentTransactionStatus::Failed,
            ], true)) {

                Log::info('Terminal payment callback ignored', [
                    'order_id' => $paymentTransaction->order_id,
                    'status' => $lockedPaymentTransaction->status,
                ]);

                return;
            }

            Log::info('Gateway status received', [
                'order_status' => $orderStatus,
            ]);

            $lockedPaymentTransaction->update([
                'status' => $isCompleted
                    ? PaymentTransactionStatus::Completed->value
                    : PaymentTransactionStatus::Failed->value,
            ]);

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

            $this->applyToWallet($lockedTransaction, $isCompleted);

            $lockedTransaction->update([
                'status' => $isCompleted
                    ? TransactionStatus::Success
                    : TransactionStatus::Failed,
            ]);

            Log::info('Tenant transaction updated', [
                'status' => $lockedTransaction->fresh()->status,
            ]);
        });
    }

    /**
     * Apply the settled gateway result to the wallet through the ledger-backed
     * WalletService. Every branch that moves money records a ledger entry.
     * Assumes we are inside the resolve() DB transaction.
     */
    private function applyToWallet(Transaction $transaction, bool $isCompleted): void
    {
        // A failed deposit never moved funds, so there is nothing to undo.
        if (! $isCompleted && $transaction->type === TransactionType::Deposit) {
            return;
        }

        $wallet = Wallet::where('user_id', $transaction->user_id)
            ->where('currency', $transaction->currency)
            ->lockForUpdate()
            ->first();

        if (! $wallet) {
            Log::error('Wallet not found', [
                'user_id' => $transaction->user_id,
                'currency' => $transaction->currency,
            ]);

            return;
        }

        $amount = (string) $transaction->amount;

        if ($isCompleted && $transaction->type === TransactionType::Deposit) {
            $this->walletService->creditDeposit($wallet, $transaction->id, $amount);
            Log::info('Deposit wallet credited', ['balance' => $wallet->fresh()->balance]);

            return;
        }

        if ($transaction->type === TransactionType::Withdrawal) {
            if ($isCompleted) {
                // Finalize: reserved funds actually leave the wallet.
                $this->walletService->debitWithdrawal($wallet, $transaction->id, $amount);
                Log::info('Withdrawal wallet debited', ['balance' => $wallet->fresh()->balance]);
            } else {
                // Rejected: return the reserved hold to spendable balance.
                $this->walletService->releaseHold($wallet, $transaction->id, $amount);
                Log::info('Withdrawal hold released', ['held' => $wallet->fresh()->held_balance]);
            }
        }
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
