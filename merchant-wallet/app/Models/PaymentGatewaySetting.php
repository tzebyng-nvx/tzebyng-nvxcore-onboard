<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\PaymentGatewaySettingsFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $merchant_username
 * @property string $api_key
 * @property string $secret_key
 * @property string $base_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class PaymentGatewaySetting extends Model
{
    /** @use HasFactory<PaymentGatewaySettingsFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'merchant_username',
        'api_key',
        'secret_key',
        'base_url',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
