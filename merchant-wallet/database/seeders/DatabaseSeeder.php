<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Admin;
use App\Models\PaymentGatewaySetting;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\WalletService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a ready-to-test setup:
     *  - a central platform admin
     *  - a `demo` tenant (its database is provisioned by the TenantCreated
     *    pipeline) containing a tenant admin and a player, each with their role.
     *
     * Passwords are passed as plain text — the models' `hashed` cast hashes them,
     * so do NOT wrap them in Hash::make() (that would double-hash).
     *
     * Note: model events must fire here so tenancy provisions the tenant DB;
     * that's why this seeder does not use WithoutModelEvents.
     */
    public function run(): void
    {
        PlatformAdmin::firstOrCreate(
            ['email' => 'platform@example.com'],
            [
                'name' => 'Platform Admin',
                'phone_number' => '0123456789',
                'password' => 'password',
            ],
        );

        $tenantId = 'demo';
        $domain = 'demo.merchant-wallet.test';

        $tenant = Tenant::firstOrCreate(['id' => $tenantId]);
        $tenant->domains()->firstOrCreate(['domain' => $domain]);

        $tenant->run(function () {
            $access = app(AccessControlService::class);

            // Gateway credentials so gateway-backed pages don't 500 on a fresh
            // tenant. Placeholder creds — update via the admin gateway-settings UI.
            PaymentGatewaySetting::firstOrCreate([], [
                'merchant_username' => 'demo-merchant',
                'api_key' => env('THIRD_PARTY_API_KEY', 'your_api_key_here'),
                'secret_key' => 'demo-secret',
                'base_url' => env('THIRD_PARTY_API_BASE_URL', 'https://staging-api.one1pay.asia'),
            ]);

            $admin = Admin::firstOrCreate(
                ['email' => 'admin@demo.test'],
                [
                    'name' => 'Demo Admin',
                    'phone_number' => '0123456789',
                    'password' => 'password',
                ],
            );
            $access->assign($admin, RoleName::TenantAdmin);

            $player = User::firstOrCreate(
                ['email' => 'player@demo.test'],
                [
                    'name' => 'Demo Player',
                    'phone_number' => '0123456789',
                    'password' => 'password',
                ],
            );
            app(WalletService::class)->getOrCreateWallet($player->id);
            $access->assign($player, RoleName::EndUser);
        });

        $this->summarize($tenantId, $domain);
    }

    /**
     * Print what was seeded so the credentials are visible after `db:seed`.
     */
    protected function summarize(string $tenantId, string $domain): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info("Seeded tenant [{$tenantId}] at [{$domain}].");
        $this->command->table(
            ['Role', 'Scope', 'Login URL', 'Email', 'Password'],
            [
                ['Platform admin', 'central', 'http://merchant-wallet.test/login', 'platform@example.com', 'password'],
                ['Tenant admin', $tenantId, "http://{$domain}/admin/login", 'admin@demo.test', 'password'],
                ['Player', $tenantId, "http://{$domain}/login", 'player@demo.test', 'password'],
            ],
        );
    }
}
