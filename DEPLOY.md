# ArcFlow 0.2.0 - deploy runbook (existing VPS)

`<APP_DIR>` = the directory on the VPS that holds the live `docker-compose.yml` and `.env`.
Nothing below uses `docker compose down -v` - never run that, it deletes the database volume.

## 0. What changes for you
- New code: HD addresses, late-transfer review, chain-ID check, exact amounts, per-log dedupe.
- **3 additive migrations** (`2026_10_10_000001..3`). They add tables/columns and replace the
  `payments (network, transaction_hash)` unique constraint with partial unique indexes + a DB trigger.
- Migrations no longer run on container start - you run them in step 5.
- Compose keeps the live names (`arcflow-pgdata`, `arcflow-internal`, port 4173) so the existing DB is reused.

## 1. Check your live `.env` (prints only OK/MISSING, no values)
```bash
cd <APP_DIR>
for k in APP_KEY DB_PASSWORD WEBHOOK_SIGNING_SECRET ARC_NETWORK ARC_RPC_URL ARC_CHAIN_ID ARC_USDC_CONTRACT ARC_EXPLORER_URL; do
  grep -Eq "^$k=.+" .env && echo "OK      $k" || echo "MISSING $k"; done
grep -Eq '^(ARCFLOW|ARCflow)_POSTGRES_PASSWORD=.+' .env && echo "OK      postgres password var" || echo "MISSING postgres password var"
```
- `ARC_CHAIN_ID` must be the decimal chain ID matching your RPC. If missing/wrong, the monitor **refuses to scan** (by design).
- `WEBHOOK_SIGNING_SECRET` must be set or webhooks are not sent.
- Do not change `APP_KEY` (encrypts stored xpubs) or `ARC_NETWORK` (must match existing invoices).

## 2. Backups (verify them)
```bash
set -eu; TS=$(date +%Y%m%d-%H%M%S); B=/root/arcflow-backup-$TS; mkdir -p $B; cd <APP_DIR>
docker compose exec -T postgres pg_dump -U arcflow -d arcflow -Fc > $B/db.dump
pg_restore -l $B/db.dump >/dev/null 2>&1 || docker run --rm -i postgres:16-alpine pg_restore -l < $B/db.dump >/dev/null
tar -czf $B/source.tar.gz -C <APP_DIR> . && cp -p .env $B/env.bak && chmod 600 $B/env.bak
ls -lh $B
```

## 3. Test first (does not touch the live DB - sqlite in memory)
```bash
mkdir -p /root/arcflow-release && tar -xzf /root/arcflow-0.2.1-production.tar.gz --strip-components=1 -C /root/arcflow-release
cd /root/arcflow-release
docker build -f Dockerfile.test -t arcflow-test .
docker run --rm arcflow-test          # php artisan test  -> must be all green
```
Do not continue if anything fails. Optionally also run the migrations against a **restored copy** of
`db.dump` in a throw-away Postgres container before touching live.

## 4. Build the new image (live stack keeps running)
```bash
cd /root/arcflow-release && cp -p <APP_DIR>/.env .env
docker compose -p arcflow-build build        # only builds; does not start anything
```
(The build fails loudly on Composer/platform problems. It needs internet access on the VPS.)

## 5. Cut over (short maintenance window)
```bash
cd <APP_DIR>
docker compose stop scheduler worker                       # stop monitor + queue writers
rsync -a --delete --exclude .env /root/arcflow-release/ <APP_DIR>/
docker compose build
docker compose run --rm app php artisan migrate --status   # review: 3 pending
docker compose run --rm app php artisan migrate --force
docker compose up -d
```

## 6. Verify
```bash
docker compose ps
curl -s http://127.0.0.1:4173/api/health                   # {"status":"ok",...,"redis":"not_configured"}
docker compose exec app php artisan route:list | grep -E "late-transfers|invoices"
docker compose exec app php artisan arcflow:monitor --once # expect "Processed blocks A-B ...", not "blocked"
docker compose logs --tail=50 scheduler
```
Then through your public URL: create a small invoice, `GET /api/v1/invoices/<id from response>` (now works with the
public id), pay it, confirm `paid` + webhook.

## 7. Optional: enable HD addresses for a merchant
```bash
docker compose exec app php artisan arcflow:merchant-xpub <merchant_id>   # hidden prompt
```
xpub only, exported at `m/44'/60'/0'/0`. Never a seed phrase or private key. Verify the first derived
address against an independently derived wallet address **before** using it for real money.
After this, `payment_address` is rejected for that merchant on invoice creation.

## 8. Rollback
- Code only: extract `source.tar.gz` back into `<APP_DIR>`, `docker compose build && docker compose up -d`.
  Old code works with the new schema for normal flows (it writes `log_index = NULL` rows).
- Full: `docker compose stop app worker scheduler`, then restore `db.dump`
  (`docker compose exec -T postgres pg_restore -U arcflow -d arcflow --clean --if-exists < $B/db.dump`), restore code, start.
  Payments/invoices created after the backup are lost with a full DB restore.
- Schema only: `docker compose run --rm app php artisan migrate:rollback --step=3 --force`
  (refuses if several payment events share one tx hash).
