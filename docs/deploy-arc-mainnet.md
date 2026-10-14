# Arc mainnet deployment checklist

ArcFlow is designed to be deployed independently from any other application.

## Current Arc mainnet reference values

As of October 2026, verify these values against the current official Arc documentation immediately before deployment:

```dotenv
ARC_NETWORK=mainnet
ARC_RPC_URL=https://rpc.mainnet.arc.io
ARC_CHAIN_ID=5042
ARC_USDC_CONTRACT=0x3600000000000000000000000000000000000000
ARC_EXPLORER_URL=https://explorer.arc.io
ARC_REQUIRED_CONFIRMATIONS=1
BLOCKCHAIN_MODE=arc_mainnet
```

Arc mainnet uses USDC as the native gas asset and is EVM-compatible. USDC's ERC-20 representation on Arc uses 6 decimals for token transfer amounts. The project still keeps all values configurable so an operator can switch to a different official RPC provider without changing source code.

## Independent deployment requirements

For a second, isolated deployment (e.g. staging) copy this tree to its own directory and change the container names, network name, `arcflow-pgdata` volume name and `ARCFLOW_HTTP_BIND` first; the production Compose file pins these names so it re-attaches to the live database volume.

Use a dedicated:

- Linux user
- project directory
- domain/subdomain
- PostgreSQL database and database role
- Redis namespace/instance or dedicated Redis credentials
- systemd/Docker services
- `.env`
- backup location

Do not reuse another project's application path, database, service, deployment user, Nginx configuration block, secrets, or backups.

## First mainnet run

1. Populate `.env` with a real `APP_KEY`, database credentials, Redis credentials, webhook secret, and Arc mainnet values.
2. Run migrations as a separate, reviewed release step against the intended database; app container startup does not run migrations automatically. Set `ARCFLOW_HTTP_BIND` to a dedicated free loopback port for this deployment (production default is `127.0.0.1:4173`).
3. Create or seed one merchant and one merchant-controlled Arc payment address.
4. Generate an API key and store the raw key securely.
5. Create a small USDC invoice.
6. Send the exact amount from a separate wallet to the invoice address.
7. Run `php artisan arcflow:monitor --once`.
8. Confirm the payment record contains the transaction hash, block number and confirmation count.
9. Confirm the invoice reaches `paid`.
10. Verify the webhook delivery and HMAC signature.
11. Verify the payment URL links to the Arc explorer transaction.

## Grant evidence

Capture only real evidence:

- public ArcFlow URL
- public repository URL
- live invoice/payment page
- Arc explorer transaction URL
- ArcFlow API response showing payment evidence
- webhook delivery evidence
- short screen recording of the complete flow

Do not claim users, volume, revenue, uptime, integrations or grant awards that have not actually occurred.
