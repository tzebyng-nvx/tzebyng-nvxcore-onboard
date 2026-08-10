# Merchant Wallet (NVXCORE onboarding)

A multi-tenant merchant wallet service. Each tenant (merchant) has isolated data
and its own end users; players deposit and withdraw through a third-party payment
gateway, and balances are tracked with a fund-hold model backed by an immutable
ledger.

## Stack

- **PHP 8.3+**, **Laravel 13**
- **PostgreSQL** (central + per-tenant databases via `stancl/tenancy`)
- **JWT** auth (`tymon/jwt-auth`) with three isolated guards: `api` (tenant
  players), `admin` (tenant admins) and `platform-admin` (central back-office)
- **Inertia + Vue 3 + Tailwind v4** for the web UI
- **Pest** for tests, **Pint** for formatting, **Larastan** for static analysis

---

## Setup (clone → run)

The app is served by [Laravel Herd](https://herd.laravel.com/) at
`https://merchant-wallet.test`. PostgreSQL must be running locally.

```bash
git clone <repo> && cd tzebyng-nvxcore-onboard & cd merchant-wallet

# install deps, copy .env, generate key, create the central DB, migrate, build assets
composer setup

php artisan db:seed            # creates a demo tenant (id `demo`, domain
                               # demo.merchant-wallet.test) + a central platform admin
```

`composer setup` runs `php artisan db:create-central`, which creates the central
database named in `.env` (`DB_DATABASE`) if it doesn't exist — so you only need
Postgres running, not a pre-created database. The configured `DB_USERNAME` must
have `CREATEDB` privilege. Run it standalone any time with:

```bash
php artisan db:create-central          # create if missing (no-op if it exists)
php artisan db:create-central --force  # drop and recreate (destroys central data)
```

Per-tenant databases are created automatically by `stancl/tenancy` when a tenant
is provisioned, so they never need manual setup.

Configuration lives in `.env` (copy from `.env.example`). Key values:

- `DB_CONNECTION=pgsql`, `DB_PORT=5432`, `DB_DATABASE=...`
- `THIRD_PARTY_API_BASE_URL` — payment gateway
- `PAYMENT_CALLBACK_URL` — public URL the gateway calls back to (see ngrok below)
- `JWT_SECRET` — generate with `php artisan jwt:secret`

Run the dev servers (Vite + queue + logs) with:

```bash
composer dev
```

---

## Multi-tenancy & ngrok

- **Tenant resolution** works two ways (spec §3.1): the web/Inertia tenant
  routes resolve by **domain/subdomain** (`InitializeTenancyByDomain`), and the
  API routes resolve by the **`X-Tenant` request header**
  (`InitializeTenancyByRequestData`). Every API call to a tenant route must send
  `X-Tenant: <tenant-id>`.
- **Central DB** holds only platform-level tables — `tenants`, `domains`,
  `payment_transactions`, `platform_admins` — plus Laravel's own framework tables
  (`sessions`, `cache`, `jobs`). **End users, admins, wallets, transactions, and
  the ledger live in each tenant's own database** (spec §3.1).
- The payment gateway calls back over the public internet, so during local
  development expose Herd through **ngrok** and point `PAYMENT_CALLBACK_URL` at it:

```bash
ngrok http --url=<your-subdomain>.ngrok-free.dev \
  https://merchant-wallet.test:443 --host-header=merchant-wallet.test
```

---

## Creating a test tenant

Provision a tenant with an initial user and admin in one command:

```bash
php artisan tenant:provision acme acme.merchant-wallet.test \
  --user-name="Acme User"   --user-email="user@acme.test"   --user-phone="0123456789" --user-password="secret" \
  --admin-name="Acme Admin" --admin-email="admin@acme.test" --admin-phone="0987654321" --admin-password="secret"
```

This creates the tenant record + domain mapping, provisions the tenant database,
and seeds a tenant user and tenant admin.

Platform (central back-office) admin:

```bash
php artisan platform:create-admin \
  --name="Platform Admin" --email="admin@platform.test" \
  --phone="0123456789" --password="secret"
```

---

## Logging in as a user & admin

All auth routes require the `X-Tenant` header. Login returns a JWT
`access_token`; send it as `Authorization: Bearer <token>` on protected routes.

```bash
# Player (guard: api)
curl -X POST https://merchant-wallet.test/api/login \
  -H "X-Tenant: acme" -H "Content-Type: application/json" \
  -d '{"email":"user@acme.test","password":"secret"}'

# Admin (guard: admin)
curl -X POST https://merchant-wallet.test/api/admin/login \
  -H "X-Tenant: acme" -H "Content-Type: application/json" \
  -d '{"email":"admin@acme.test","password":"secret"}'
```

The two guards are fully isolated: a player token is rejected (401) on admin
routes and vice versa (see `tests/Feature/AuthGuardMatrixTest.php`).

---

## Deposit / withdrawal design

Money movement is split between **request time** (create + reserve) and
**callback time** (finalize), and every balance/hold change writes a
`wallet_ledgers` row. A wallet has two columns: `balance` (spendable) and
`held_balance` (reserved); **available = balance − held_balance**.

### Deposit

1. `POST /api/payment/deposit` creates a pending `payment_transaction` (central)
   and `transaction` (tenant), then asks the gateway to initiate the order.
2. The player pays on the gateway. **The deposit is confirmed by the gateway
   callback, never by the redirect.**
3. On a `completed` callback the wallet is credited (`balance += amount`) and a
   `deposit_credit` ledger row is written.

### Withdrawal

1. `POST /api/payment/withdraw` **reserves the funds immediately**: it locks the
   wallet row (`lockForUpdate`), rejects with **422** if `available < amount`
   (no negative balances), then moves the amount into `held_balance` and writes a
   `withdrawal_hold` ledger row — all in one DB transaction.
2. The gateway is then asked to initiate the payout. If the gateway rejects, the
   hold is released and the transaction is marked failed (422).
3. On the callback:
   - `completed` → funds actually leave: `balance -= amount`, `held_balance -= amount`, `withdrawal_debit` ledger row.
   - `failed` → hold returned to spendable: `held_balance -= amount`, `withdrawal_release` ledger row.

**Ledger invariant:** the wallet's `balance` and `held_balance` can be fully
reconstructed from the sum of its ledger rows — they never drift
(`tests/Integration/WithdrawalMoneyCorrectnessTest.php`).

Orchestration lives in `PaymentService`; controllers stay thin. The gateway sits
behind `PaymentGatewayContract` so it can be faked in tests / swapped in prod.

---

## Idempotency, concurrency & callback verification

- **Callback signature** — each callback is verified with
  `hash_equals(md5(secret_key . order_id), token)` before anything is applied
  (`PaymentCallbackService::isValidToken`). Unknown orders and bad tokens are ignored.
- **Idempotency** — a replayed callback is stopped two ways: the payment
  transaction is short-circuited once it reaches a terminal state
  (`completed`/`failed`), and the `wallet_ledgers` table has a **unique index on
  `(transaction_id, entry_type)`**, so the DB itself refuses a duplicate credit /
  hold / debit.
- **Concurrency** — concurrent withdrawals serialize on the wallet's
  `lockForUpdate`; the availability check inside the lock guarantees two
  full-balance withdrawals can't both succeed.

Verify with the suite:

```bash
php artisan test --filter=WithdrawalMoneyCorrectness   # hold / debit / release / no drift / double-spend
php artisan test --filter=PaymentCallbackResolve        # deposit credit + no double-credit
php artisan test --filter=AuthGuardMatrix               # dual-guard isolation
```

---

## Testing & quality gates

```bash
php artisan test          # Pest suite (runs on in-memory sqlite)
vendor/bin/pint           # format
composer ci:check         # lint + format + phpstan + tests
```

Tests run on an in-memory SQLite connection (`phpunit.xml`); production and
local dev use PostgreSQL.

### Test suite (78 tests)

**Tenant isolation & resolution**

| File | What it proves |
|---|---|
| `Feature/TenantIsolationTest.php` | Data written in one tenant DB is invisible from another — the core cross-tenant leak guarantee (spec §3.1). |
| `Feature/TenantIdentificationTest.php` | Tenancy resolves both by full domain/subdomain (web) and by the `X-Tenant` header (API). |

**Authentication & guard separation**

| File | What it proves |
|---|---|
| `Feature/AuthGuardMatrixTest.php` | The full 4-case guard matrix: player/admin token × player/admin route (200s + both rejections) (spec §3.2). |
| `Feature/AuthRouteSeparationTest.php` | Player and admin JWT route guards are separate route files with separate middleware. |
| `Feature/AuthLoginTest.php` | Player and admin can each log in on their own guard; a player cannot use the admin login. |
| `Feature/AuthSessionTest.php` | `me` returns the authenticated identity, is rejected without a token, and a real login issues a working token. |

**Access control (spec §11 — spatie roles)**

| File | What it proves |
|---|---|
| `Feature/AccessControlTest.php` | `tenant-admin` role reaches the back office, an admin without it is forbidden, and self-registration grants the `end-user` role. |

**Money correctness (deposit / withdrawal / ledger)**

| File | What it proves |
|---|---|
| `Integration/WithdrawalMoneyCorrectnessTest.php` | Fund-hold on create, rejection when over available balance, **double-spend prevention** on concurrent full-balance withdrawals, debit-on-complete, and hold-release-on-fail. |
| `Integration/PaymentCallbackResolveTest.php` | A completed callback credits the wallet, a failed one leaves it untouched, and a replayed completed callback **does not double-credit** (idempotency). |
| `Integration/ReconciliationTest.php` | The reconcile job resolves stale `pending` deposits from the status endpoint — credits if completed, marks failed if failed, skips if still pending. |
| `Feature/DepositTest.php` | A successful deposit persists a pending transaction with the gateway payment id; a rejected one marks it failed (422); payment method is validated. |
| `Feature/WalletSummaryTest.php` | Balance summary counts only successful totals, auto-creates a wallet, and requires auth. |

**Input validation (Form Requests)**

| File | What it proves |
|---|---|
| `Feature/ValidationTest.php` | Form Request rules reject bad input with 422 before any business logic runs — deposit/withdrawal amount (required, numeric, positive) and destination fields, and admin user create/update (required fields, email format, uniqueness incl. ignore-self, password length). |

**Gateway integration (driver / DTO)**

| File | What it proves |
|---|---|
| `Unit/PaymentGatewayDtoTest.php` | Gateway responses map correctly into DTOs (status check, pending, float balance, order success/failure shapes). |
| `Unit/PaymentGatewayEndpointTest.php` | The gateway endpoint paths are stable. |

**Back office & CRUD (spec §11)**

| File | What it proves |
|---|---|
| `Feature/PlatformTenantTest.php` | Central BO lists tenants with per-tenant deposit/withdrawal totals, requires platform-admin auth, and validates tenant creation. |
| `Feature/AdminDashboardTest.php` | Tenant dashboard returns tenant-wide payment counts, lists all user transactions, filters by type, and rejects unauthenticated access. |
| `Feature/AdminUserManagementTest.php` | Tenant-admin user CRUD (list/create-with-wallet/update/delete), bank-list sync, and rejection for non-admins. |

**Player features & bonus**

| File | What it proves |
|---|---|
| `Feature/RegistrationTest.php` | Self-registration issues a token and rejects duplicate email / unconfirmed password. |
| `Feature/TransactionFilteringTest.php` | Transaction list filters by type, status, both, and rejects an invalid status. |
| `Feature/WithdrawRateLimitTest.php` | Withdrawals are throttled after the configured attempts (429) and the limit is per authenticated user. |

---

## Known issues / limitations

- **True lock-based concurrency requires PostgreSQL.** The test suite runs on
  in-memory SQLite, which can't serialize `lockForUpdate` across connections, so
  the double-spend test proves the *application-level* availability check.
  Row-lock serialization is exercised only against Postgres.
- **Single currency.** The wallet model assumes one currency per user (`MYR`);
  multi-currency wallets are out of scope. (The schema keeps a `currency` column
  and a `(user_id, currency)` unique key, so this is an extension, not a rewrite.)
- **Central tenant management is create / list / delete only.** The Central BO
  (spec §11.1) can provision a tenant (database + domain + initial admin), list
  tenants with per-tenant deposit/withdrawal totals, and delete a tenant, but
  **edit and soft "deactivate" are not implemented** — there is no `active` flag
  on tenants yet.
- **Permission-cache scoping on tenant switch is not explicitly handled.**
  `spatie/laravel-permission`'s registrar cache is not flushed on tenant switch
  (the §11 gotcha). Tenancy's `CacheTenancyBootstrapper` is enabled, but the
  permission cache is not separately scoped/flushed, so verify this before
  relying on the back office across multiple tenants in one process.
- **Callback signature scheme is gateway-specific.** Verification is
  `hash_equals(md5(secret_key . order_id), token)` per the sandbox docs; a
  different gateway would need a different `isValidToken` implementation (it sits
  behind the driver, so this is a contained change).

---

## Handy commands

```bash
# migrations
php artisan make:migration create_wallets_table --path=database/migrations/tenant   # tenant
php artisan make:migration create_wallets_table                                       # central
php artisan tenants:migrate      # run tenant migrations for all tenants
php artisan migrate              # run central migrations

# workers
php artisan queue:work
php artisan schedule:work
```
