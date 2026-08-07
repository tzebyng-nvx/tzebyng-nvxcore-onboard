<?php

namespace App\Repositories;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransactionRepository
{
    /**
     * @param  array{type?:string|null,status?:string|null}  $filters
     */
    public function paginateByUser(
        string $userId,
        int $perPage = 15,
        array $filters = []
    ): LengthAwarePaginator {
        return Transaction::query()
            ->where('user_id', $userId)
            ->when(
                ! empty($filters['type']),
                fn ($query) => $query->where('type', $filters['type'])
            )
            ->when(
                ! empty($filters['status']),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
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
