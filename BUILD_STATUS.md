# ArcFlow build status

Release: 0.3.0 (dashboard login + partial-payment views on top of 0.2.1)

Previous: 0.2.1 (0.2.0 promoted from the hardened staging tree, 2026-10-14; payment-detection fixes added the same day)

## Included
- Everything from the 0.1 MVP (invoice API, Arc RPC monitor, webhooks, payment page, SDK, OpenAPI).
- Optional per-merchant HD addresses from a public xpub (`php artisan arcflow:merchant-xpub <merchant_id>`); legacy caller-supplied addresses still work for merchants without an xpub.
- Late/expired transfers are recorded as `late_review` / `legacy_transfer_reviews` and never mark invoices paid.
- Exact decimal-string amount handling (no floats); amounts with more than 6 decimals are rejected.
- `eth_chainId` check against `ARC_CHAIN_ID` before every scan; initial scan fails closed without `ARC_START_BLOCK`.
- Per-event (`log_index`) payment de-duplication.
- Read-only `GET /api/v1/reviews/late-transfers`.
- Migrations are an explicit release step (not run on container start).
- `composer.lock` committed; image build fails on Composer/platform errors.

## Release-prep fixes (on top of staging)
- Invoice routes bind by `public_id` (the id the API returns); previously `GET/cancel /invoices/{id}` expected the numeric DB id.
- `/api/health` no longer reports `degraded` forever because Redis is not configured/used.
- Webhooks are not sent (critical log) if `WEBHOOK_SIGNING_SECRET` is empty, instead of signing with an empty key.
- Invoice `amount` validated as plain decimal, max 6 fractional digits.
- Guests hitting `/dashboard` are redirected to `/` instead of a 500.
- URLs (e.g. `payment_url`) are generated as https when `APP_URL` is https (TLS terminates at the reverse proxy).
- **Payment detection scans Arc's system emitter (`0xffff…fffe`, 18 decimals) only.** Per Arc's docs it logs every USDC movement (native sends and ERC-20 `transfer()`); the 6-decimal ERC-20 mirror log duplicates ERC-20 transfers, so scanning both counted one transfer twice.
- **Partial payments accumulate.** Transfers to the same open invoice are summed: total below the amount = `underpaid` (a top-up can complete it), exact total = paid (after the required confirmations), above = `overpaid` (manual review). Webhook `invoice.paid` reports the invoice amount; `invoice.payment_detected` adds `total_paid`.
- Zero-value and sub-0.000001 dust transfers are ignored (they could previously flip an invoice to `underpaid`).
- Dockerfile: production `php.ini`, `bootstrap/cache` guaranteed.
- Compose: fixed names/volume `arcflow-pgdata` and port 4173 restored so the live DB volume is reused.
- `.env.example` added; stray backup/`.before-*` files and `public/build/index.php` removed.

## NOT verified (no PHP/Composer/Docker/network in the packaging environment)
- The Laravel test suite has not been run against this tree. Run it before deploying (see DEPLOY.md).
- Migrations have not been run against PostgreSQL here. Test on a restored copy of your DB dump first.
- Live mainnet payment detection still needs one real end-to-end payment test (a native send AND an ERC-20 transfer) after each deploy of detection changes.
- Token contract address (`0x3600…0000`, 6 decimals) and system emitter (`0xffff…fffe`, 18 decimals) match Arc's published docs; nothing was checked against your own RPC beyond the chain-ID check.

ArcFlow is a prototype; it has not had an independent security review and is not Circle/Arc endorsed.

## 0.3.0 - dashboard and payment page
- Merchant sign-in (`/login`, `/logout`) with CSRF, session regeneration, per-email+IP throttling (5 tries / 5 min) and security headers.
- Dashboard pages: Overview, Invoices (filters + pagination), Invoice detail (payments, progress), Payments, Needs review.
- Partial payments are visible everywhere: paid so far, remaining, progress bar, overpayment excess.
- Public payment page: shows received/remaining, lists every payment, and hides the address and stops refreshing once an invoice has expired, been cancelled, paid or overpaid.
- Invoices past `expires_at` are *displayed* as expired; stored statuses and the API are unchanged.
- `php artisan arcflow:dashboard-password [email]` sets a password (lists users when run without an email).
- No database migrations.
