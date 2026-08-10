<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing transaction shape consumed by the admin dashboard table.
 *
 * @mixin Transaction
 */
class AdminTransactionResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_order_id' => $this->id,
            'user_email' => $this->user?->email,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
