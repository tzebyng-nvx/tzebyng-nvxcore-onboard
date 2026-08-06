<?php

namespace App\Models;

use App\Enums\PaymentTransactionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

// CENTRAL TABLE: LOOKUP

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentTransaction extends Model
{
    use CentralConnection, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'status',
    ];

    protected $casts = [
        'status' => PaymentTransactionStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
