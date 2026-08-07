# Merchant Wallet (NVXCORE onboarding)

A multi-tenant merchant wallet service. Each tenant (merchant) has isolated data
and its own end users; players deposit and withdraw through a third-party payment
gateway, and balances are tracked with a fund-hold model backed by an immutable
ledger.

## Stack

- **PHP 8.4**, **Laravel 13**
- **PostgreSQL** (central + per-tenant databases via `stancl/tenancy`)
- **JWT** auth (`tymon/jwt-auth`) with two isolated guards: `api` (players) and `admin`
- **Inertia + Vue 3 + Tailwind v4** for the web UI
- **Pest** for tests, **Pint** for formatting, **Larastan** for static analysis

---

## Setup (clone → run)

The app is served by [Laravel Herd](https://herd.laravel.com/) at
`https://merchant-wallet.test`. PostgreSQL must be running locally.

```bash
git clone <repo> && cd merchant-wallet

# install deps, copy .env, generate key, migrate, build assets
composer setup

# create the central DB (see .env DB_DATABASE) in Postgres first, then:
php artisan migrate            # central connection
php artisan db:seed            # creates a demo `merchant-wallet` tenant + domain
```

Configuration lives in `.env` (copy from `.env.example`). Key values:

- `DB_CONNECTION=pgsql`, `DB_PORT=5432`, `DB_DATABASE=...`
- `THIRD_PARTY_API_BASE_URL`, `THIRD_PARTY_API_KEY` — payment gateway
- `PAYMENT_CALLBACK_URL` — public URL the gateway calls back to (see ngrok below)
- `JWT_SECRET` — generate with `php artisan jwt:secret`

Run the dev servers (Vite + queue + logs) with:

```bash
composer dev
```

---

## Multi-tenancy & ngrok

- **Tenant resolution** is by the `X-Tenant` request header
  (`InitializeTenancyByRequestData`). Every API call to a tenant route must send
  `X-Tenant: <tenant-id>`.
- **Central DB** holds `tenants`, `domains`, `payment_transactions`,
  `platform_admins`, and `sessions` only. **End users, admins, wallets,
  transactions, and the ledger live in each tenant's own database** (spec §3.1).
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

---

## Known issues / limitations

- **True lock-based concurrency requires PostgreSQL.** The test suite runs on
  in-memory SQLite, which can't serialize `lockForUpdate` across connections, so
  the double-spend test proves the *application-level* availability check.
  Row-lock serialization is exercised only against Postgres.
- **Single currency.** The wallet model assumes one currency per user (`MYR`);
  multi-currency wallets are out of scope.
- **Back office (spec §11) is partial.** The access model
  (`spatie/laravel-permission`) and central/tenant back-office screens are only
  partially implemented; some admin dashboard endpoints are still stubs.

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
