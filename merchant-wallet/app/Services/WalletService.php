<?php

namespace App\Services;

use App\Enums\WalletLedgerEntryType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Wallet;
use App\Repositories\WalletRepository;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function __construct(
        protected WalletRepository $walletRepository
    ) {}

    public function getWallet(string $userId)
    {
        return $this->walletRepository->getWalletByUserId($userId);
    }

    public function getOrCreateWallet(string $userId)
    {
        return DB::transaction(function () use ($userId) {
            $wallet = $this->walletRepository->getWalletByUserId($userId);

            if ($wallet) {
                return $wallet;
            }

            return $this->walletRepository->createWallet([
                'user_id' => $userId,
                'currency' => 'MYR',
            ]);

        });
    }

    /**
     * Reserve funds for a pending withdrawal at request time. Locks the wallet
     * row, rejects the request if the available balance (balance - held_balance)
     * cannot cover the amount, then moves the amount into held_balance and
     * records a hold ledger entry.
     *
     * MUST be called inside a DB transaction so the lock and the mutation are
     * atomic; concurrent full-balance withdrawals serialize on the row lock and
     * the second one fails the availability check.
     *
     * @throws InsufficientBalanceException
     */
    public function holdForWithdrawal(
        string $userId,
        string $transactionId,
        string $amount,
        string $currency
    ): Wallet {
        $wallet = $this->walletRepository
            ->lockWalletByUserIdAndCurrency($userId, $currency);

        if (! $wallet) {
            throw new InsufficientBalanceException('Wallet not found for this currency.');
        }

        $available = bcsub($wallet->balance, $wallet->held_balance, 2);

        if (bccomp($available, $amount, 2) < 0) {
            throw new InsufficientBalanceException;
        }

        $wallet = $this->walletRepository->incrementHeldBalance($wallet, $amount);
        $this->walletRepository->recordLedgerEntry(
            $wallet,
            $transactionId,
            WalletLedgerEntryType::WithdrawalHold,
            $amount
        );

        return $wallet;
    }

    /**
     * Release a hold given a user + currency, locking the wallet itself.
     * MUST be called inside a DB transaction. Returns null if no wallet exists.
     */
    public function releaseHoldForUser(
        string $userId,
        string $transactionId,
        string $amount,
        string $currency
    ): ?Wallet {
        $wallet = $this->walletRepository
            ->lockWalletByUserIdAndCurrency($userId, $currency);

        if (! $wallet) {
            return null;
        }

        return $this->releaseHold($wallet, $transactionId, $amount);
    }

    /**
     * Credit a confirmed deposit onto the wallet and record the ledger entry.
     * MUST be called inside a DB transaction with the wallet already locked.
     */
    public function creditDeposit(Wallet $wallet, string $transactionId, string $amount): Wallet
    {
        $wallet = $this->walletRepository->incrementBalance($wallet, $amount);
        $this->walletRepository->recordLedgerEntry(
            $wallet,
            $transactionId,
            WalletLedgerEntryType::DepositCredit,
            $amount
        );

        return $wallet;
    }

    /**
     * Finalize a successful withdrawal: the reserved funds actually leave the
     * wallet, so both balance and held_balance drop by the amount.
     * MUST be called inside a DB transaction with the wallet already locked.
     */
    public function debitWithdrawal(Wallet $wallet, string $transactionId, string $amount): Wallet
    {
        $wallet = $this->walletRepository->decrementHeldBalance($wallet, $amount);
        $wallet = $this->walletRepository->decrementBalance($wallet, $amount);
        $this->walletRepository->recordLedgerEntry(
            $wallet,
            $transactionId,
            WalletLedgerEntryType::WithdrawalDebit,
            $amount
        );

        return $wallet;
    }

    /**
     * Release a hold when a withdrawal is rejected/failed: the reserved funds
     * return to spendable balance (held_balance drops, balance untouched).
     * MUST be called inside a DB transaction with the wallet already locked.
     */
    public function releaseHold(Wallet $wallet, string $transactionId, string $amount): Wallet
    {
        $wallet = $this->walletRepository->decrementHeldBalance($wallet, $amount);
        $this->walletRepository->recordLedgerEntry(
            $wallet,
            $transactionId,
            WalletLedgerEntryType::WithdrawalRelease,
            $amount
        );

        return $wallet;
    }
}
