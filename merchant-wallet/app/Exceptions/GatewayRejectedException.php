<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the payment gateway declines to initiate a deposit/withdrawal
 * order. Carries the gateway's own message so the caller can surface it.
 */
class GatewayRejectedException extends RuntimeException
{
    public function __construct(string $message = 'The payment could not be initiated.')
    {
        parent::__construct($message);
    }
}
