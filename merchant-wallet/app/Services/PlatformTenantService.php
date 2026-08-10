<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\TransactionType;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\TransactionRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;

/**
 * Central back-office operations over tenants. Per-tenant money totals are
 * computed by entering each tenant's database (transactions live tenant-side),
 * so these methods run inside tenant context via `Tenant::run()`.
 */
class PlatformTenantService
{
    public function __construct(
        protected TransactionRepository $transactionRepository
    ) {}

    /**
     * List every tenant with its primary domain and per-tenant money totals.
     *
     * A tenant whose database was never provisioned (e.g. a failed creation)
     * is reported with zeroed totals and flagged `provisioned = false` instead
     * of taking down the whole listing.
     *
     * @return array<int,array{id:string,domain:string|null,user_count:int,total_in:string,total_out:string,provisioned:bool}>
     */
    public function listWithTotals(): array
    {
        return Tenant::with('domains')->get()->map(function (Tenant $tenant) {
            try {
                $stats = $tenant->run(fn () => [
                    'user_count' => User::query()->count(),
                    'total_in' => $this->transactionRepository->sumByTypeStatus(TransactionType::Deposit),
                    'total_out' => $this->transactionRepository->sumByTypeStatus(TransactionType::Withdrawal),
                ]);
                $provisioned = true;
            } catch (TenantDatabaseDoesNotExistException) {
                $stats = ['user_count' => 0, 'total_in' => '0', 'total_out' => '0'];
                $provisioned = false;
            }

            return [
                'id' => $tenant->getTenantKey(),
                'domain' => $tenant->domains->first()?->domain,
                'user_count' => $stats['user_count'],
                'total_in' => number_format((float) $stats['total_in'], 2, '.', ''),
                'total_out' => number_format((float) $stats['total_out'], 2, '.', ''),
                'provisioned' => $provisioned,
            ];
        })->all();
    }

    /**
     * Create a tenant, map its domain, and provision an initial tenant admin
     * (with the tenant-admin role). The tenant database + migrations are handled
     * by the tenancy pipeline (TenantCreated jobs).
     *
     * @param  array{id:string,domain:string,admin_name?:string,admin_email:string,admin_password:string}  $data
     */
    public function create(array $data): Tenant
    {
        $tenant = Tenant::create(['id' => $data['id']]);

        try {
            $tenant->domains()->create(['domain' => $data['domain']]);

            $tenant->run(function () use ($data) {
                $admin = Admin::query()->create([
                    'name' => $data['admin_name'] ?? 'Tenant Admin',
                    'email' => $data['admin_email'],
                    'phone_number' => $data['admin_phone'] ?? '0000000000',
                    'password' => Hash::make($data['admin_password']),
                ]);

                app(AccessControlService::class)->assign($admin, RoleName::TenantAdmin);
            });
        } catch (\Throwable $e) {
            // Provisioning failed after the tenant row was persisted; remove the
            // partial tenant so it can't linger as an orphan and break listings.
            $this->forceDelete($tenant);

            throw $e;
        }

        return $tenant;
    }

    /**
     * Delete a tenant (the tenancy pipeline drops its database). If the tenant
     * was never fully provisioned its database won't exist, so fall back to a
     * record-only delete rather than surfacing a 500.
     */
    public function delete(Tenant $tenant): void
    {
        try {
            $tenant->delete();
        } catch (TenantDatabaseDoesNotExistException) {
            $this->forceDelete($tenant);
        } catch (QueryException $e) {
            // Postgres 3D000 / MySQL 1049: the tenant database is already gone,
            // so drop the record without the (failing) delete pipeline.
            if (! $this->isDatabaseMissing($e)) {
                throw $e;
            }

            $this->forceDelete($tenant);
        }
    }

    /**
     * Whether a query failure is a "database does not exist" error.
     */
    protected function isDatabaseMissing(QueryException $e): bool
    {
        return in_array((string) ($e->errorInfo[0] ?? ''), ['3D000', '1049'], true);
    }

    /**
     * Remove the tenant record and its domains without running the tenancy
     * delete pipeline. Used to clean up a tenant whose database does not exist.
     */
    protected function forceDelete(Tenant $tenant): void
    {
        $tenant->domains()->delete();

        Tenant::withoutEvents(function () use ($tenant) {
            $tenant->delete();
        });
    }
}
