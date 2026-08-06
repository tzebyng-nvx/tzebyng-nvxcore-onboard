<?php

namespace App\Services;

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
}
