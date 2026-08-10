<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property TransactionType $type
 * @property string $amount
 * @property string $currency
 * @property TransactionStatus $status
 * @property string|null $payment_transaction_id
 * @property string|null $payment_method
 * @property string|null $bank_id
 * @property string|null $payment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Transaction extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'amount',
        'currency',
        'status',
        'payment_transaction_id',
        'payment_method',
        'bank_id',
        'payment_id', // from payment gateway resp
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => TransactionStatus::class,
        'type' => TransactionType::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
