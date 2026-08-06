<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
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
}
