<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Withdrawal Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of withdrawal requests a single user (falling back to IP)
    | may make within the given window. Consumed by the "withdraw" rate limiter
    | applied to the player withdraw route.
    |
    */

    'rate_limit' => [
        'max_attempts' => (int) env('WITHDRAW_RATE_LIMIT_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('WITHDRAW_RATE_LIMIT_DECAY_MINUTES', 1),
    ],
];
