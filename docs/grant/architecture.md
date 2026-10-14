# Architecture

```text
Merchant / SaaS
      |
      | REST API + API key
      v
+-----------------------+
|       ArcFlow         |
| Laravel API           |
| Invoice state         |
| Payment matcher       |
| Webhook service       |
+-----------+-----------+
            |
            v
     Arc JSON-RPC
            |
            v
       Arc + USDC
```

The application is non-custodial. Payment addresses are merchant-controlled; private keys never enter ArcFlow.

Adapters:

- `ArcRpcAdapter` — real Arc/EVM JSON-RPC monitoring.
- `MockAdapter` — deterministic development/test adapter.

This makes the business logic independently testable while keeping Arc as the production settlement source.
