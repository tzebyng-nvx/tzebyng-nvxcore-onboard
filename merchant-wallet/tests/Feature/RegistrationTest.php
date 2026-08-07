<?php

use App\Models\User;
use App\Models\Wallet;

beforeEach(function () {
    createTenantWithSchema('reg-test');
});

test('a visitor can self-register and receives a token', function () {
    $response = $this->withHeaders(['X-Tenant' => 'reg-test'])
        ->postJson('/api/register', [
            'name' => 'New Player',
            'email' => 'new@reg-test.com',
            'phone_number' => '0123456789',
            'password' => 'Sup3r$ecret!',
            'password_confirmation' => 'Sup3r$ecret!',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

    $user = User::where('email', 'new@reg-test.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->phone_number)->toBe('0123456789');

    // A wallet is provisioned as part of registration.
    expect(Wallet::where('user_id', $user->id)->exists())->toBeTrue();
});

test('registration rejects a duplicate email', function () {
    User::create([
        'name' => 'Existing',
        'email' => 'taken@reg-test.com',
        'phone_number' => '0100000000',
        'password' => 'whatever123',
    ]);

    $response = $this->withHeaders(['X-Tenant' => 'reg-test'])
        ->postJson('/api/register', [
            'name' => 'Duplicate',
            'email' => 'taken@reg-test.com',
            'phone_number' => '0123456789',
            'password' => 'Sup3r$ecret!',
            'password_confirmation' => 'Sup3r$ecret!',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

test('registration rejects an unconfirmed password', function () {
    $response = $this->withHeaders(['X-Tenant' => 'reg-test'])
        ->postJson('/api/register', [
            'name' => 'Mismatch',
            'email' => 'mismatch@reg-test.com',
            'phone_number' => '0123456789',
            'password' => 'Sup3r$ecret!',
            'password_confirmation' => 'different',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('password');
});
