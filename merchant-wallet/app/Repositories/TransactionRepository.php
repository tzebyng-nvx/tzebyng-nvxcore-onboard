<?php

namespace App\Repositories;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransactionRepository
{
    public function paginateByUser(
        string $userId,
        int $perPage = 15
    ): LengthAwarePaginator {
        return Transaction::query()
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
    }
}
