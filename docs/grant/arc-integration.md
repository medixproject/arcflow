# Arc integration

Arc is not a cosmetic deployment target in ArcFlow. The core settlement flow depends on Arc:

1. Merchant invoice declares an Arc network.
2. Customer sends USDC to a merchant address on Arc.
3. ArcFlow queries Arc JSON-RPC.
4. ArcFlow filters the configured USDC contract for ERC-20 Transfer events.
5. Transfer recipient and amount are matched to an invoice.
6. ArcFlow applies the configured settlement/finality policy.
7. The transaction hash and block are retained as payment evidence.
8. A signed application webhook is emitted.

Network identifiers, RPC URLs, USDC contract addresses and explorer URLs are environment-driven and must be verified against current official Arc documentation before deployment.

Future Circle integrations are intentionally separated from the core adapter: Circle Wallets can provide managed wallet UX, CCTP can support cross-chain USDC flows, and Gateway can support chain-abstracted liquidity where those products materially improve the use case.
