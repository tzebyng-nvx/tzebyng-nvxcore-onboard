<?php

namespace App\Enums;

/**
 * Application roles across the access model. `guard()` ties each role to the
 * JWT guard whose model owns it:
 *  - CentralAdmin → platform-admin (central DB)   [same pattern, provisioned centrally]
 *  - TenantAdmin  → admin          (tenant DB)
 *  - EndUser      → api            (tenant DB)
 */
enum RoleName: string
{
    case CentralAdmin = 'central-admin';
    case TenantAdmin = 'tenant-admin';
    case EndUser = 'end-user';

    public function guard(): string
    {
        return match ($this) {
            self::CentralAdmin => 'platform-admin',
            self::TenantAdmin => 'admin',
            self::EndUser => 'api',
        };
    }
}
