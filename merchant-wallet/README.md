# tzebyng-nvxcore-onboard

## Create Model File

```bash
php artisan make:model ModelName

// if you need migration, factory, seeder, controller, form request at the same time
// remove policy file after the file generation, spatie/laravel-permission handles this
php artisan make:model ModelName -a
```

## Create Service File

```bash
php artisan make:class Services/UserService
```

## Provision a tenant with an initial user and admin

```bash
php artisan tenant:provision acme acme.merchant-wallet.test \
  --user-name="Acme User" \
  --user-email="user@acme.test" \
  --user-phone="0123456789" \
  --user-password="secret" \
  --admin-name="Acme Admin" \
  --admin-email="admin@acme.test" \
  --admin-phone="0987654321" \
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

## Forwarding Port

```bash
ngrok http --url=verbose-exhaust-angriness.ngrok-free.dev https://merchant-wallet.test:443 --host-header=merchant-wallet.test
```

## Build Workers

### Queue

```
php artisan queue:work
```

### Scheduler

```
php artisan schedule:work
```