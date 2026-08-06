<?php

namespace App\Repositories;

use App\Models\Wallet;
use App\Models\WalletLedger;

class WalletRepository
{
    public function getWalletByUserId(string $userId): ?Wallet
    {
        return Wallet::where('user_id', $userId)
            ->first();
    }

    public function getLedgerByUserId(
        string $userId,
        int $perPage = 15
    ) {
        return WalletLedger::whereHas('wallet', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->latest()
            ->paginate($perPage);
    }

    public function createWallet(array $data): Wallet
    {
        $wallet = Wallet::create([
            'user_id' => $data['user_id'],
            'currency' => $data['currency'] ?? 'MYR',
        ]);

        return $wallet->fresh();
    }

    public function lockWalletByUserId(string $userId): ?Wallet
    {
        return Wallet::where('user_id', $userId)
            ->lockForUpdate()
            ->first();
    }

    public function incrementBalance(Wallet $wallet, string $amount): Wallet
    {
        $wallet->increment('balance', $amount);

        return $wallet->refresh();
    }

    public function decrementBalance(Wallet $wallet, string $amount): Wallet
    {
        $wallet->decrement('balance', $amount);

        return $wallet->refresh();
    }

    public function incrementHeldBalance(Wallet $wallet, string $amount): Wallet
    {
        $wallet->increment('held_balance', $amount);

        return $wallet->refresh();
    }

    public function decrementHeldBalance(Wallet $wallet, string $amount): Wallet
    {
        $wallet->decrement('held_balance', $amount);

        return $wallet->refresh();
    }
}
