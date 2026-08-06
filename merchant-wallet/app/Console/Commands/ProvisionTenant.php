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
        {--user-password=password} 
        {--admin-name=Tenant Admin} 
        {--admin-email=} 
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
        $userPassword = $this->option('user-password');

        $adminName = $this->option('admin-name');
        $adminEmail = $this->option('admin-email') ?: $this->defaultEmail($tenantId, 'admin');
        $adminPassword = $this->option('admin-password');

        $tenant->run(function () use (
            $userName,
            $userEmail,
            $userPassword,
            $adminName,
            $adminEmail,
            $adminPassword
        ) {
            User::query()->firstOrCreate([
                'email' => $userEmail,
            ], [
                'name' => $userName,
                'password' => Hash::make($userPassword),
            ]);

            Admin::query()->firstOrCreate([
                'email' => $adminEmail,
            ], [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
            ]);
        });

        $this->info("Tenant [$tenantId] provisioned for [$domain].");

        $this->table([
            'Type',
            'Name',
            'Email',
        ], [
            ['User', $userName, $userEmail],
            ['Admin', $adminName, $adminEmail],
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
