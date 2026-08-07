<?php

namespace App\Repositories;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
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

    /**
     * Sum the amount of a user's successful transactions of a given type.
     */
    public function sumByUserTypeStatus(
        string $userId,
        TransactionType $type,
        TransactionStatus $status = TransactionStatus::Success
    ): string {
        return (string) Transaction::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('status', $status)
            ->sum('amount');
    }
}
