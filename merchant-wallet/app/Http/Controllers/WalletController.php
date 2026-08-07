<?php

namespace App\Http\Controllers;

use App\Services\TransactionService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected TransactionService $transactionService
    ) {}

    /**
     * Get authenticated user's wallet balance
     */
    public function index(): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet(
            auth('api')->id()
        );

        return response()->json($wallet);
    }

    /**
     * Dashboard summary: current balance plus total money in / out.
     */
    public function summary(): JsonResponse
    {
        $userId = auth('api')->id();

        $wallet = $this->walletService->getOrCreateWallet($userId);
        $totals = $this->transactionService->getUserTotals($userId);

        return response()->json([
            'balance' => $wallet->balance,
            'currency' => $wallet->currency,
            'total_in' => $totals['total_in'],
            'total_out' => $totals['total_out'],
        ]);
    }
}
