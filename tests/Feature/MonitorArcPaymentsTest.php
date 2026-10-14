<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MonitorArcPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_scan_fails_closed_without_cursor_or_start_block_even_with_no_addresses(): void
    {
        config([
            'arc.network' => 'mainnet',
            'arc.start_block' => null,
        ]);

        Cache::forget('arcflow:last_block');

        $this->artisan('arcflow:monitor')
            ->expectsOutput('Initial scan blocked: set ARC_START_BLOCK to a verified starting block before monitoring.')
            ->assertExitCode(1);
    }
    public function test_initial_scan_fails_closed_for_invalid_start_block_values(): void
    {
        foreach (['', 'abc', '-1', '1.5', '1e3', '999999999999999999999999999999'] as $invalidStart) {
            config([
                'arc.network' => 'mainnet',
                'arc.start_block' => $invalidStart,
            ]);

            Cache::forget('arcflow:last_block');

            $this->artisan('arcflow:monitor')
                ->expectsOutput('Initial scan blocked: set ARC_START_BLOCK to a verified starting block before monitoring.')
                ->assertExitCode(1);
        }
    }

    public function test_initial_scan_accepts_zero_as_a_valid_start_block(): void
    {
        config([
            'arc.network' => 'mainnet',
            'arc.start_block' => '0',
        ]);

        Cache::forget('arcflow:last_block');

        // With no invoices or allocated addresses, a valid start block exits
        // before constructing an RPC adapter or contacting the network.
        $this->artisan('arcflow:monitor')
            ->expectsOutput('No active invoice or allocated payment addresses configured.')
            ->assertExitCode(0);
    }

}
