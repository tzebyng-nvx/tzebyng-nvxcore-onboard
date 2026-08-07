<?php

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('tenancy.bootstrappers', []);

    Tenant::withoutEvents(function (): void {
        $tenant = Tenant::create(['id' => 'auth-login']);
        $tenant->domains()->create(['domain' => 'auth-login.merchant-wallet.test']);

        $tenant->run(function () {
            // Drop the vestigial central `users` table before building the
            // tenant-shaped schema.
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
                'email' => 'player@auth-login.test',
                'password' => 'secret123',
            ]);

            Admin::query()->create([
                'name' => 'Admin User',
                'email' => 'admin@auth-login.test',
                'password' => 'secret123',
            ]);
        });
    });
});

test('player can login with the user guard', function (): void {
    $response = $this->withHeaders(['X-Tenant' => 'auth-login'])
        ->postJson('/api/login', [
            'email' => 'player@auth-login.test',
            'password' => 'secret123',
        ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);
});

test('admin can login with the admin guard', function (): void {
    $response = $this->withHeaders(['X-Tenant' => 'auth-login'])
        ->postJson('/api/admin/login', [
            'email' => 'admin@auth-login.test',
            'password' => 'secret123',
        ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);
});

test('user credentials cannot access the admin login route', function (): void {
    $response = $this->withHeaders(['X-Tenant' => 'auth-login'])
        ->postJson('/api/admin/login', [
            'email' => 'player@auth-login.test',
            'password' => 'secret123',
        ]);

    $response
        ->assertStatus(401)
        ->assertJson(['error' => 'Unauthorized']);
});
