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
     * Paginate every transaction in the tenant (admin view), with the user's
     * email eager-loaded for display.
     *
     * @param  array{type?:string|null,status?:string|null}  $filters
     */
    public function paginateAll(
        int $perPage = 20,
        array $filters = []
    ): LengthAwarePaginator {
        return Transaction::query()
            ->with('user:id,email')
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
     * Count transactions in the tenant grouped by status.
     *
     * @return array<string,int> status value => count
     */
    public function countByStatus(): array
    {
        return Transaction::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Sum the amount of the whole tenant's transactions of a given type/status.
     */
    public function sumByTypeStatus(
        TransactionType $type,
        TransactionStatus $status = TransactionStatus::Success
    ): string {
        return (string) Transaction::query()
            ->where('type', $type)
            ->where('status', $status)
            ->sum('amount');
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
