<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ArcRpcAdapter implements BlockchainAdapter
{
    private const NATIVE_USDC_SYSTEM = '0xfffffffffffffffffffffffffffffffffffffffe';

    /*
     * Keep chunks reasonably bounded. Recipient addresses are batched
     * into a single topic filter so request count does not grow with
     * the number of active invoices.
     */
    private const CHUNK_SIZE = 4000;
    private const RECIPIENT_BATCH_SIZE = 100;

    public function __construct(
        private readonly string $rpc,
        private readonly string $usdc,
        private readonly string $topic
    ) {}

    private function rpc(string $method, array $params = []): mixed
    {
        $maxAttempts = 6;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $started = microtime(true);

            $r = Http::timeout(15)->post($this->rpc, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $method,
                'params' => $params,
            ]);

            $elapsed = (int) round((microtime(true) - $started) * 1000);

            if ($r->status() === 429) {
                $delay = min(
                    30,
                    2 ** min($attempt - 1, 4)
                );

                logger()->warning('Arc RPC HTTP 429', [
                    'method' => $method,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'status' => 429,
                    'elapsed_ms' => $elapsed,
                    'delay_seconds' => $delay,
                    'fromBlock' => $params[0]['fromBlock'] ?? null,
                    'toBlock' => $params[0]['toBlock'] ?? null,
                    'address' => $params[0]['address'] ?? null,
                    'topic0' => $params[0]['topics'][0] ?? null,
                    'topic2_count' => isset($params[0]['topics'][2])
                        ? count((array) $params[0]['topics'][2])
                        : 0,
                    'response' => $r->body(),
                ]);

                if ($attempt >= $maxAttempts) {
                    throw new RuntimeException(
                        'Arc RPC HTTP error: 429 after '.$maxAttempts.' attempts'
                    );
                }

                /*
                 * Exponential backoff with a small deterministic jitter.
                 * This prevents repeated workers/scheduler cycles from
                 * immediately hammering the same rate limit window.
                 */
                $jitter = ($attempt % 3) + 1;
                sleep($delay + $jitter);

                continue;
            }

            if (!$r->successful()) {
                throw new RuntimeException(
                    'Arc RPC HTTP error: '.$r->status()
                );
            }

            $j = $r->json();

            if (isset($j['error'])) {
                logger()->warning('Arc RPC JSON-RPC error', [
                    'method' => $method,
                    'attempt' => $attempt,
                    'elapsed_ms' => $elapsed,
                    'error' => $j['error'],
                ]);

                throw new RuntimeException(
                    'Arc RPC error: '.($j['error']['message'] ?? 'unknown')
                );
            }

            return $j['result'] ?? null;
        }

        throw new RuntimeException('Arc RPC request failed.');
    }

    /** Return the chain ID reported by this RPC endpoint. */
    public function chainId(): int
    {
        $value = $this->rpc('eth_chainId');
        if (!is_string($value) || !preg_match('/^0x[0-9a-f]+$/i', $value)) {
            throw new RuntimeException('Arc RPC returned an invalid chain ID.');
        }

        return (int) hexdec($value);
    }

    public function latestBlock(): int
    {
        $value = $this->rpc('eth_blockNumber');
        if (!is_string($value) || !preg_match('/^0x[0-9a-f]+$/i', $value)) {
            throw new RuntimeException('Arc RPC returned an invalid latest block number.');
        }

        return (int) hexdec($value);
    }

    /**
     * Scan USDC Transfer events (Arc system emitter, 18 decimals) sent to the given recipients.
     */
    public function transferEvents(
        int $fromBlock,
        int $toBlock,
        array $recipientAddresses
    ): array {
        if ($fromBlock > $toBlock || !$recipientAddresses) {
            return [];
        }

        $logs = [];
        $seen = [];

        $addresses = array_values(array_unique(
            array_map(
                fn ($address) => strtolower(trim($address)),
                $recipientAddresses
            )
        ));

        foreach (array_chunk($addresses, self::RECIPIENT_BATCH_SIZE) as $recipientBatch) {
            $topic2 = array_map(
                function (string $address): string {
                    $clean = strtolower(
                        preg_replace('/^0x/i', '', $address)
                    );

                    return '0x'.str_pad(
                        substr($clean, -40),
                        64,
                        '0',
                        STR_PAD_LEFT
                    );
                },
                $recipientBatch
            );

            for (
                $chunkFrom = $fromBlock;
                $chunkFrom <= $toBlock;
                $chunkFrom += self::CHUNK_SIZE
            ) {
                $chunkTo = min(
                    $chunkFrom + self::CHUNK_SIZE - 1,
                    $toBlock
                );

                /*
                 * Arc emits an EIP-7708 Transfer log from the native USDC system
                 * emitter (18 decimals) for EVERY USDC movement: plain native sends
                 * and ERC-20 transfer() calls alike. An ERC-20 transfer() also emits
                 * a 6-decimal mirror log from the ERC-20 contract, so scanning both
                 * would count one transfer twice. Scan the system emitter only.
                 */
                $result = $this->rpc('eth_getLogs', [[
                    'fromBlock' => '0x'.dechex($chunkFrom),
                    'toBlock' => '0x'.dechex($chunkTo),
                    'address' => self::NATIVE_USDC_SYSTEM,
                    'topics' => [
                        $this->topic,
                        null,
                        $topic2,
                    ],
                ]]);

                foreach ((array) $result as $log) {
                    $key = strtolower(
                        ($log['transactionHash'] ?? '').':'.
                        ($log['logIndex'] ?? '').':'.
                        ($log['blockNumber'] ?? '')
                    );

                    if ($key === '::' || isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $log['_arc_decimals'] = 18;
                    // Record the asset as the configured USDC identity (one asset, two interfaces).
                    $log['_arc_token_contract'] = $this->usdc !== ''
                        ? $this->usdc
                        : self::NATIVE_USDC_SYSTEM;
                    $logs[] = $log;
                }
            }
        }

        return $logs;
    }

    public function transactionReceipt(string $hash): array
    {
        return $this->rpc(
            'eth_getTransactionReceipt',
            [$hash]
        ) ?? [];
    }
}
