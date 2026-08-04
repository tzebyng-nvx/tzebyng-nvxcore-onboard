# Tenancy Checklist

- [x] Tenants are identified by domain/subdomain and also work through an `X-Tenant` request header.
  - Browser tenant routes use `InitializeTenancyByDomain`; API routes use `InitializeTenancyByRequestData`, which reads `X-Tenant`. Both resolution paths have automated tests.
  - Justification: the app registers both domain-based and request-data tenant middleware in the tenancy provider and tests cover both resolution paths.

- [ ] The central application manages only tenant records and tenant-domain mappings.

- [ ] Wallet, user, admin, and transaction data live only in tenant databases.

- [x] Central and tenant migrations are fully separated into clear paths.
  - Justification: central migrations are limited to tenant metadata tables, while tenant-specific schema lives under `database/migrations/tenant` and is executed through `php artisan tenants:migrate`.

- [ ] An Artisan command or seeder provisions a tenant, its domain, an initial user, and an initial admin.

- [x] Two tenants are demonstrated to be data-isolated, with an automated test proving Tenant A cannot access Tenant B data.
  - Justification: the test creates two unique tenant databases and asserts each database only returns its own wallet data.

- [x] Use stancl/tenancy in multi-database mode — each tenant has its own database.
  - Justification: the tenancy config enables the database tenancy bootstrapper and each tenant is created with its own isolated database instance.
