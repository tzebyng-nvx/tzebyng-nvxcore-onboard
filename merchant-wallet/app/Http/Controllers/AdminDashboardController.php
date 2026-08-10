<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionIndexRequest;
use App\Http\Resources\AdminTransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * Tenant-wide payment summary shown on the admin dashboard.
     */
    public function generalInfo(): JsonResponse
    {
        return response()->json($this->transactionService->getGeneralInfo());
    }

    /**
     * Paginated list of every transaction in the tenant.
     */
    public function transactions(TransactionIndexRequest $request): AnonymousResourceCollection
    {
        $transactions = $this->transactionService->getAllTransactions(
            $request->validated('per_page', 20),
            [
                'type' => $request->validated('type'),
                'status' => $request->validated('status'),
            ],
        );

        return AdminTransactionResource::collection($transactions);
    }
}
