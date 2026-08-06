<?php

namespace App\Services;

use App\Enums\PaymentTransactionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\PaymentTransaction;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        protected TransactionRepository $transactionRepository
    ) {}

    public function getUserTransactions(
        string $userId,
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->transactionRepository
            ->paginateByUser($userId, $perPage);
    }

    public function createDeposit(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $orderId = (string) Str::uuid();

            // 1) Create record in central db > payment_transaction
            $paymentTransaction = PaymentTransaction::create([
                'tenant_id' => tenant('id'),
                'order_id' => $orderId,
                'status' => PaymentTransactionStatus::Pending,
            ]);

            // 2) Create record in tenant db > transaction
            return Transaction::create([
                'id' => $orderId,
                'user_id' => $data['user_id'],
                'type' => TransactionType::Deposit,
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'status' => TransactionStatus::Pending,
                'payment_transaction_id' => $paymentTransaction->id,
                'payment_method' => $data['payment_method'] ?? null,
                'bank_id' => $data['bank_id'] ?? null,
            ]);
        });
    }

    public function createWithdrawal(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $orderId = (string) Str::uuid();

            // 1) Create record in central db > payment_transaction
            $paymentTransaction = PaymentTransaction::create([
                'tenant_id' => tenant('id'),
                'order_id' => $orderId,
                'status' => PaymentTransactionStatus::Pending,
            ]);

            // 2) Create record in tenant db > transaction
            return Transaction::create([
                'id' => $orderId,
                'user_id' => $data['user_id'],
                'type' => TransactionType::Withdrawal,
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'status' => TransactionStatus::Pending,
                'payment_transaction_id' => $paymentTransaction->id,
                'payment_method' => null,
                'bank_id' => $data['bank_id'],
            ]);
        });
    }

    public function updateStatus(
        Transaction $transaction,
        TransactionStatus $status
    ): Transaction {
        $transaction->update([
            'status' => $status,
        ]);

        return $transaction->refresh();
    }

    public function updatePaymentId(array $data)
    {
        [$transactionId, $paymentId] = $data;

        Transaction::where('id', $transactionId)
            ->update(['payment_id' => $paymentId]);
    }
}
