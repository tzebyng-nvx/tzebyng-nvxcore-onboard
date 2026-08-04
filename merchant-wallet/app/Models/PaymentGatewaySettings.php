<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\PaymentGatewaySettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $merchant_username
 * @property string $api_key
 * @property string $secret_key
 * @property string $base_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentGatewaySettings extends Model
{
    /** @use HasFactory<PaymentGatewaySettingsFactory> */
    use HasFactory;

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
    ];
}
