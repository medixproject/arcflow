<?php

namespace Tests\Unit;

use App\Services\Blockchain\ArcRpcAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArcRpcAdapterTest extends TestCase
{
    public function test_chain_id_is_read_from_rpc_and_parsed(): void
    {
        Http::fake([
            'https://rpc.example.test' => Http::response([
                'jsonrpc' => '2.0', 'id' => 1, 'result' => '0x13b2',
            ], 200),
        ]);

        $adapter = new ArcRpcAdapter(
            'https://rpc.example.test',
            '0x3600000000000000000000000000000000000000',
            '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef'
        );

        $this->assertSame(5042, $adapter->chainId());
        Http::assertSent(fn ($request) => $request['method'] === 'eth_chainId');
    }

    public function test_invalid_chain_id_response_is_rejected(): void
    {
        Http::fake([
            'https://rpc.example.test' => Http::response([
                'jsonrpc' => '2.0', 'id' => 1, 'result' => '5042',
            ], 200),
        ]);

        $adapter = new ArcRpcAdapter('https://rpc.example.test', '0x1', '0x2');
        $this->expectException(\RuntimeException::class);
        $adapter->chainId();
    }
}
