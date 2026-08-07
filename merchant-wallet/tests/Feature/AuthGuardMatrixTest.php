<?php

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Behavioural token-based auth matrix (assignment §3.2): a token minted for one
 * guard must work on that guard's routes and be rejected by the other guard's
 * routes. Uses the real login endpoints to obtain tokens, then hits the
 * protected `me` route of each guard.
 */
beforeEach(function (): void {
    config()->set('tenancy.bootstrappers', []);

    Tenant::withoutEvents(function (): void {
        $tenant = Tenant::create(['id' => 'auth-matrix']);
        $tenant->domains()->create(['domain' => 'auth-matrix.merchant-wallet.test']);

        $tenant->run(function () {
            Schema::dropIfExists('admins');
            Schema::dropIfExists('users');

            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('phone_number')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });

            Schema::create('admins', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('phone_number')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });

            User::query()->create([
                'name' => 'Player User',
                'email' => 'player@auth-matrix.test',
                'password' => 'secret123',
            ]);

            Admin::query()->create([
                'name' => 'Admin User',
                'email' => 'admin@auth-matrix.test',
                'password' => 'secret123',
            ]);
        });
    });
});

/**
 * Log in through the given route and return the bearer access token.
 */
function loginToken(string $loginRoute, string $email): string
{
    return test()
        ->withHeaders(['X-Tenant' => 'auth-matrix'])
        ->postJson($loginRoute, ['email' => $email, 'password' => 'secret123'])
        ->assertOk()
        ->json('access_token');
}

test('player token is accepted on a player route', function (): void {
    $token = loginToken('/api/login', 'player@auth-matrix.test');

    $this->withHeaders([
        'X-Tenant' => 'auth-matrix',
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/me')->assertOk();
});

test('player token is rejected on an admin route', function (): void {
    $token = loginToken('/api/login', 'player@auth-matrix.test');

    $this->withHeaders([
        'X-Tenant' => 'auth-matrix',
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/admin/me')->assertStatus(401);
});

test('admin token is accepted on an admin route', function (): void {
    $token = loginToken('/api/admin/login', 'admin@auth-matrix.test');

    $this->withHeaders([
        'X-Tenant' => 'auth-matrix',
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/admin/me')->assertOk();
});

test('admin token is rejected on a player route', function (): void {
    $token = loginToken('/api/admin/login', 'admin@auth-matrix.test');

    $this->withHeaders([
        'X-Tenant' => 'auth-matrix',
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/me')->assertStatus(401);
});
