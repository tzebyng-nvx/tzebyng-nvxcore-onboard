# tzebyng-nvxcore-onboard

## Provision a tenant with an initial user and admin

```bash
php artisan tenant:provision acme acme.merchant-wallet.test \
  --user-name="Acme User" \
  --user-email="user@acme.test" \
  --user-password="secret" \
  --admin-name="Acme Admin" \
  --admin-email="admin@acme.test" \
  --admin-password="secret"
```

This command will:

- create the tenant record in the central database
- create the domain mapping for `acme.merchant-wallet.test`
- create the tenant database via Stancl tenancy
- seed an initial tenant user and tenant admin into the tenant database

## Create Migration File

### For Tenant

```bash
php artisan make:migration create_wallets_table --path=database/migrations/tenant
```

### For Central

```bash
php artisan make:migration create_wallets_table
```

## Migration

### Tenant

```bash
php artisan tenants:migrate
```

### Central

```bash
php artisan migrate
```

## Test

```bash
php artisan test
```