# ArcFlow

**Live project:** https://arc.chainserver.org

**Non-custodial USDC invoice, payment detection and webhook infrastructure for Arc.**

ArcFlow is an independent open-source MVP designed for developers who want to accept and reconcile USDC on Arc without implementing blockchain event monitoring, invoice matching and webhook delivery themselves.

## What is implemented

- Laravel 12 API
- PostgreSQL data model
- API-key authentication with hashed keys
- Non-custodial payment addresses: merchant-supplied, or derived from a merchant xpub (HD)
- USDC invoice creation
- Arc EVM JSON-RPC adapter
- ERC-20 `Transfer` event monitoring
- Exact/under/overpayment classification
- Configurable confirmation-depth reconciliation before marking exact payments paid
- Duplicate transaction protection
- Signed HMAC-SHA256 webhooks with retries
- Payment page and basic dashboard
- Mock blockchain adapter for deterministic tests
- Docker Compose deployment
- TypeScript SDK foundation using viem
- Grant-ready architecture and demo documentation

## Current status

This repository is an MVP package. The immediate deployment target is an Arc mainnet proof-of-concept suitable for the current Arc Microgrants window; the project must still be independently security-reviewed before production use. **Do not represent it as production-ready or Circle/Arc endorsed.** Arc network values, USDC contract addresses and RPC endpoints are deliberately configured through environment variables and must be populated from the current official Arc/Circle documentation.

## Quick start

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

For Docker:

```bash
cp .env.example .env
docker compose up --build
```

## Arc configuration

Populate:

```text
ARC_NETWORK
ARC_RPC_URL
ARC_CHAIN_ID
ARC_USDC_CONTRACT
ARC_EXPLORER_URL
ARC_REQUIRED_CONFIRMATIONS
```

Do not copy network values from old tutorials. Verify them against the current official Arc documentation before deploying.

## API authentication

Create a merchant, then:

```bash
php artisan arcflow:api-key 1 production
```

The raw key is displayed once. Store it securely.

Use it as:

```bash
curl https://example.test/api/v1/invoices \\
  -H 'Authorization: Bearer af_live_...' \\
  -H 'Accept: application/json'
```

## Create invoice

```bash
curl -X POST https://example.test/api/v1/invoices \\
  -H 'Authorization: Bearer af_live_...' \\
  -H 'Content-Type: application/json' \\
  -d '{
    "amount":"1.00",
    "currency":"USDC",
    "description":"Demo payment",
    "payment_address":"0xYOUR_MERCHANT_ADDRESS"
  }'
```

## Monitor payments

For a one-off monitor run:

```bash
php artisan arcflow:monitor --once
```

In production, run the scheduler/worker continuously. The monitor reads USDC `Transfer` logs from the configured Arc RPC and matches recipient addresses against active, unexpired invoices. Exact payments remain `confirming` until `ARC_REQUIRED_CONFIRMATIONS` is reached, then the invoice becomes `paid` and an HMAC-signed `invoice.paid` webhook is emitted.

## Arc Microgrant target

Arc Microgrants currently describes a mainnet-first program for small proofs of concept and advertises 20 grants of 500 USDC, with submissions closing October 14, 2026. ArcFlow is structured around that requirement: deploy a working mainnet proof, publish the repository, demonstrate an actual USDC payment, and submit the live URL plus repository rather than claiming funding in advance. Verify the live program terms immediately before submission.

## Security model

ArcFlow is intentionally non-custodial in the MVP. It does not store merchant private keys and has no withdrawal feature. API keys are stored as SHA-256 hashes. Webhooks are signed using HMAC-SHA256. Secrets must remain in `.env` or an external secret manager.

## Official references

- Arc developer documentation: https://docs.arc.network/
- Circle developer platform: https://www.circle.com/developer
- Circle Developer Grants: https://www.circle.com/grant

## License

MIT

## Merchant dashboard

Merchants sign in at `/login` and use the workspace at `/dashboard` (Overview, Invoices, Payments, Needs review).
It is a read-only view of the same invoices and payments the API manages. Partial payments are shown as
"paid / remaining" and a progress bar.

Accounts are created by an administrator; there is no self-service sign-up. Set or reset a password with:

```bash
docker compose exec app php artisan arcflow:dashboard-password            # lists users
docker compose exec app php artisan arcflow:dashboard-password you@example.com
```

Passwords need at least 12 characters. Five failed sign-ins lock that email for five minutes.
Set `SESSION_SECURE_COOKIE=true` in `.env` so the session cookie is only sent over HTTPS.
