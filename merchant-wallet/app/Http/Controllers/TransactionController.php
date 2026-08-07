<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionIndexRequest;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    public function index(TransactionIndexRequest $request): JsonResponse
    {
        $transactions = $this->transactionService
            ->getUserTransactions(
                auth('api')->id(),
                $request->validated('per_page', 15),
                [
                    'type' => $request->validated('type'),
                    'status' => $request->validated('status'),
                ]
            );

        return response()->json($transactions);
    }
}
