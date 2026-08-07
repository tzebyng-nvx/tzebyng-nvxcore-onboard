<?php

use App\Models\PaymentGatewaySetting;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a tenant with its domain and the tenant-side schema needed for tests.
 * Tenancy DB bootstrappers are disabled so every query runs on the shared
 * in-memory sqlite connection (central + tenant tables live together).
 */
function createTenantWithSchema(string $id): Tenant
{
    config()->set('tenancy.bootstrappers', []);

    return Tenant::withoutEvents(function () use ($id) {
        $tenant = Tenant::create(['id' => $id]);
        $tenant->domains()->create(['domain' => $id.'.merchant-wallet.test']);

        // Tests share one in-memory sqlite connection, so drop any tenant
        // tables left by a previous run before rebuilding the tenant schema.
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->decimal('balance', 18, 2)->default(0);
            $table->decimal('held_balance', 18, 2)->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->timestamps();
            $table->unique(['user_id', 'currency']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10);
            $table->string('status')->default('pending');
            $table->uuid('payment_transaction_id');
            $table->string('payment_method')->nullable();
            $table->string('bank_id');
            $table->string('payment_id')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('wallet_ledgers');
        Schema::create('wallet_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wallet_id');
            $table->uuid('transaction_id');
            $table->string('entry_type');
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->timestamps();
            $table->index(['wallet_id', 'created_at']);
            $table->unique(['transaction_id', 'entry_type'], 'uq_ledger_txn_entry_type');
        });

        Schema::dropIfExists('payment_gateway_settings');
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('merchant_username');
            $table->string('api_key');
            $table->string('secret_key');
            $table->string('base_url');
            $table->timestamps();
            $table->softDeletes();
        });

        // A settings row so the real PaymentGatewayService can be constructed
        // (its constructor calls firstOrFail); tests that hit the gateway mock
        // the service, so these credentials are never used for real calls.
        PaymentGatewaySetting::create([
            'merchant_username' => 'test-merchant',
            'api_key' => 'test-api-key',
            'secret_key' => 'test-secret',
            'base_url' => 'https://gateway.test',
        ]);

        return $tenant;
    });
}
