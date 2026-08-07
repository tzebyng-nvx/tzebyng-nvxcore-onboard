<?php

use App\Models\User;

beforeEach(function () {
    createTenantWithSchema('session-test');

    $this->user = User::create([
        'name' => 'Session User',
        'email' => 'session@session-test.com',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);
});

test('me returns the authenticated user', function () {
    $this->actingAs($this->user, 'api')
        ->withHeaders(['X-Tenant' => 'session-test'])
        ->postJson('/api/me')
        ->assertOk()
        ->assertJson([
            'email' => 'session@session-test.com',
            'phone_number' => '0123456789',
        ]);
});

test('me is rejected without authentication', function () {
    $this->withHeaders(['X-Tenant' => 'session-test'])
        ->postJson('/api/me')
        ->assertUnauthorized();
});

test('a real login issues a token that authorizes me', function () {
    $login = $this->withHeaders(['X-Tenant' => 'session-test'])
        ->postJson('/api/login', [
            'email' => 'session@session-test.com',
            'password' => 'secret123',
        ])->assertOk();

    $token = $login->json('access_token');

    $this->withHeaders([
        'X-Tenant' => 'session-test',
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/me')->assertOk();

    $this->withHeaders([
        'X-Tenant' => 'session-test',
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/logout')->assertOk()->assertJson(['message' => 'Successfully logged out']);
});
