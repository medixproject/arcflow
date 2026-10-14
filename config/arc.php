<?php

return [
    'network' => env('ARC_NETWORK', 'arc-testnet'),
    'rpc_url' => env('ARC_RPC_URL'),
    'chain_id' => env('ARC_CHAIN_ID'),
    'usdc_contract' => env('ARC_USDC_CONTRACT'),
    'explorer_url' => env('ARC_EXPLORER_URL'),
    'required_confirmations' => (int) env('ARC_REQUIRED_CONFIRMATIONS', 1),
    'start_block' => env('ARC_START_BLOCK'),
    'poll_interval_seconds' => (int) env('ARC_POLL_INTERVAL_SECONDS', 3),
    'mode' => env('BLOCKCHAIN_MODE', 'arc_testnet'),
    'transfer_topic' => '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
];
