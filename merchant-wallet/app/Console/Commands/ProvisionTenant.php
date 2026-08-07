<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ProvisionTenant extends Command
{
    protected $signature = 'tenant:provision 
        {tenant_id} 
        {domain} 
        {--user-name=Tenant User}
        {--user-email=}
        {--user-phone=0000000000}
        {--user-password=password}
        {--admin-name=Tenant Admin}
        {--admin-email=}
        {--admin-phone=0000000000}
        {--admin-password=password}';

    protected $description = 'Create a tenant, map a domain, and provision initial tenant users/admins in the tenant database.';

    public function handle(): int
    {
        $tenantId = (string) $this->argument('tenant_id');
        $domain = (string) $this->argument('domain');

        $tenant = Tenant::firstOrCreate([
            'id' => $tenantId,
        ]);

        $tenant->domains()->firstOrCreate([
            'domain' => $domain,
        ]);

        $userName = $this->option('user-name');
        $userEmail = $this->option('user-email') ?: $this->defaultEmail($tenantId, 'user');
        $userPhone = $this->option('user-phone');
        $userPassword = $this->option('user-password');

        $adminName = $this->option('admin-name');
        $adminEmail = $this->option('admin-email') ?: $this->defaultEmail($tenantId, 'admin');
        $adminPhone = $this->option('admin-phone');
        $adminPassword = $this->option('admin-password');

        $tenant->run(function () use (
            $userName,
            $userEmail,
            $userPhone,
            $userPassword,
            $adminName,
            $adminEmail,
            $adminPhone,
            $adminPassword
        ) {
            User::query()->firstOrCreate([
                'email' => $userEmail,
            ], [
                'name' => $userName,
                'phone_number' => $userPhone,
                'password' => Hash::make($userPassword),
            ]);

            Admin::query()->firstOrCreate([
                'email' => $adminEmail,
            ], [
                'name' => $adminName,
                'phone_number' => $adminPhone,
                'password' => Hash::make($adminPassword),
            ]);
        });

        $this->info("Tenant [$tenantId] provisioned for [$domain].");

        $this->table([
            'Type',
            'Name',
            'Email',
            'Phone',
        ], [
            ['User', $userName, $userEmail, $userPhone],
            ['Admin', $adminName, $adminEmail, $adminPhone],
        ]);

        return self::SUCCESS;
    }

    protected function defaultEmail(string $tenantId, string $label): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($tenantId));
        $slug = trim((string) $slug, '-');

        return sprintf('%s-%s@example.com', $label, $slug ?: 'tenant');
    }
}
