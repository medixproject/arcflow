<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonitorZeroAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_value_transfers_are_ignored_and_an_eighteen_decimal_payment_marks_the_invoice_paid(): void
    {
        $address = '0x4444444444444444444444444444444444444444';
        $from = '0x2222222222222222222222222222222222222222';

        $user = User::create([
            'name' => 'Zero Amount User',
            'email' => 'zero-amount@example.test',
            'password' => Hash::make('password'),
        ]);
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'name' => 'Zero Amount Merchant',
            'slug' => 'zero-amount-test',
            'status' => 'active',
            'default_network' => 'mainnet',
        ]);
        $invoice = Invoice::create([
            'merchant_id' => $merchant->id,
            'amount' => '1.000000',
            'currency' => 'USDC',
            'network' => 'mainnet',
            'payment_address' => $address,
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        config([
            'arc.mode' => 'arc_mainnet',
            'arc.network' => 'mainnet',
            'arc.rpc_url' => 'https://rpc.example.test',
            'arc.chain_id' => '5042',
            'arc.usdc_contract' => '0x3600000000000000000000000000000000000000',
            'arc.start_block' => '100',
            'arc.required_confirmations' => 1,
        ]);
        Cache::forget('arcflow:last_block');

        $pad = fn (string $value): string => '0x' . str_pad(substr($value, 2), 64, '0', STR_PAD_LEFT);
        $topic = (string) config('arc.transfer_topic');
        $logs = [
            [
                'transactionHash' => '0xzero',
                'logIndex' => '0x0',
                'blockNumber' => '0x65',
                'topics' => [$topic, $pad($from), $pad($address)],
                'data' => '0x' . str_repeat('0', 64),
            ],
            [
                'transactionHash' => '0xpaid',
                'logIndex' => '0x1',
                'blockNumber' => '0x65',
                'topics' => [$topic, $pad($from), $pad($address)],
                'data' => '0x' . str_pad('de0b6b3a7640000', 64, '0', STR_PAD_LEFT),
            ],
        ];

        Http::fake(function ($request) use ($logs) {
            $result = match ($request['method']) {
                'eth_chainId' => '0x13b2',
                'eth_blockNumber' => '0x66',
                'eth_getLogs' => $logs,
                default => null,
            };

            return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => $result], 200);
        });

        $this->artisan('arcflow:monitor')->assertExitCode(0);

        $this->assertSame(1, Payment::count());
        $this->assertSame('0xpaid', Payment::first()->transaction_hash);
        $this->assertSame('paid', $invoice->fresh()->status);
    }
}
