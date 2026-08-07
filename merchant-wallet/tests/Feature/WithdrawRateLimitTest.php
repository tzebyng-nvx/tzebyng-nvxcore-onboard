<?php

use App\Models\User;

beforeEach(function () {
    createTenantWithSchema('rate-test');

    $this->user = User::create([
        'name' => 'Rate User',
        'email' => 'rate@rate-test.com',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);

    config()->set('withdrawal.rate_limit.max_attempts', 5);
    config()->set('withdrawal.rate_limit.decay_minutes', 1);
});

test('withdrawals are throttled after the configured number of attempts', function () {
    // Empty body -> validation 422, but the throttle middleware runs first and
    // still counts each attempt, so we never reach the payment gateway.
    for ($i = 1; $i <= 5; $i++) {
        $this->actingAs($this->user, 'api')
            ->withHeaders(['X-Tenant' => 'rate-test'])
            ->postJson('/api/payment/withdraw', [])
            ->assertStatus(422);
    }

    $this->actingAs($this->user, 'api')
        ->withHeaders(['X-Tenant' => 'rate-test'])
        ->postJson('/api/payment/withdraw', [])
        ->assertStatus(429);
});

test('the limit is per authenticated user', function () {
    $other = User::create([
        'name' => 'Other User',
        'email' => 'other@rate-test.com',
        'phone_number' => '0100000000',
        'password' => 'secret123',
    ]);

    // Exhaust the first user's quota.
    for ($i = 1; $i <= 6; $i++) {
        $this->actingAs($this->user, 'api')
            ->withHeaders(['X-Tenant' => 'rate-test'])
            ->postJson('/api/payment/withdraw', []);
    }

    // A different user is unaffected.
    $this->actingAs($other, 'api')
        ->withHeaders(['X-Tenant' => 'rate-test'])
        ->postJson('/api/payment/withdraw', [])
        ->assertStatus(422);
});
