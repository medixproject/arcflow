# ArcFlow staging hardening changes

This is a staging release candidate, not authorization to deploy to production.

## Included changes

- Added RPC `eth_chainId` parsing and monitor comparison against configured decimal `ARC_CHAIN_ID`; a mismatch or missing/invalid ID blocks monitoring.
- Validated `eth_blockNumber` response format before conversion.
- Kept initial scan fail-closed for invalid/missing `ARC_START_BLOCK` when there is no saved cursor.
- Rejected malformed payment amount strings and precision beyond six decimal places rather than silently truncating.
- Added a merchant-scoped, read-only `GET /api/v1/reviews/late-transfers` endpoint listing late-review payments and legacy transfer reviews. It requires the existing API-key middleware and filters records to the authenticated merchant's invoices.
- Removed automatic migrations from app-container startup; migrations must be run as an explicit release step against the intended database.
- Removed fixed Compose container/network/volume names to allow Compose project isolation; staging host port defaults to 4174. Use a unique Compose project name and verify the host port before starting it.
- Made the production image build fail on Composer install/platform requirement errors, then regenerate authoritative autoload metadata after source files are copied, and run Laravel package discovery.
- Standardized the mainnet deployment guide's `ARC_NETWORK` example to `mainnet`, matching the currently observed production setting. Never change a live value without confirming the DB and existing merchant derivation-config network values.
- Added unit tests for RPC chain ID parsing and amount format validation.

## Not yet verified

- The full Laravel suite and migrations were not run in this environment because Docker and the project's Composer vendor directory are unavailable here. PHP syntax checks passed for all PHP files.
- Verify the `GET /api/v1/reviews/late-transfers` response on the actual staging PostgreSQL version, including JSON filtering and merchant isolation.
- Review/test confirmation handling and legacy-transfer review UX end-to-end.
- Token contract and system emitter were checked against Arc's published docs (0.2.1); confirm RPC behavior against actual chain data with a real payment test.
- Confirm `ARC_START_BLOCK` from historical chain coverage; do not guess it.
- Production deployment, mainnet payment test, security audit, and backup/rollback rehearsal remain outstanding.
