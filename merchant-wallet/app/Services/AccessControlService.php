<?php

namespace App\Services;

use App\Enums\RoleName;
use Spatie\Permission\Models\Role;

/**
 * Provisions the tenant-scoped roles used by the access model. Roles live in
 * the tenant database (guard-scoped), so this must run inside tenant context.
 */
class AccessControlService
{
    /**
     * Idempotently create the tenant admin + end-user roles.
     */
    public function ensureTenantRoles(): void
    {
        foreach ([RoleName::TenantAdmin, RoleName::EndUser] as $role) {
            Role::findOrCreate($role->value, $role->guard());
        }
    }

    /**
     * Ensure roles exist, then grant one to a model (Admin/User).
     */
    public function assign(object $model, RoleName $role): void
    {
        $this->ensureTenantRoles();
        $model->assignRole($role->value);
    }
}
