<?php

namespace Tests\Unit;

use App\Services\Blockchain\ArcRpcAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArcRpcAdapterEmitterTest extends TestCase
{
    public function test_only_the_system_emitter_is_scanned_so_transfers_are_not_double_counted(): void
    {
        $usdc = '0x3600000000000000000000000000000000000000';
        $system = '0xfffffffffffffffffffffffffffffffffffffffe';
        $topic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
        $recipient = '0x4444444444444444444444444444444444444444';

        Http::fake(function ($request) use ($topic) {
            return Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [[
                    'transactionHash' => '0xabc',
                    'logIndex' => '0x1',
                    'blockNumber' => '0x10',
                    'topics' => [$topic, '0x' . str_repeat('0', 64), '0x' . str_repeat('0', 24) . str_repeat('4', 40)],
                    'data' => '0x' . str_pad('de0b6b3a7640000', 64, '0', STR_PAD_LEFT),
                ]],
            ], 200);
        });

        $adapter = new ArcRpcAdapter('https://rpc.example.test', $usdc, $topic);
        $events = $adapter->transferEvents(10, 20, [$recipient]);

        $this->assertCount(1, $events);
        $this->assertSame(18, $events[0]['_arc_decimals']);
        $this->assertSame($usdc, $events[0]['_arc_token_contract']);

        Http::assertSent(fn ($request) => $request['method'] === 'eth_getLogs'
            && strtolower($request['params'][0]['address']) === $system);
        Http::assertNotSent(fn ($request) => $request['method'] === 'eth_getLogs'
            && strtolower($request['params'][0]['address']) !== $system);
    }
}
