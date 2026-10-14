<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\User;
use Tests\TestCase;

class PaymentLogIndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_log_index_migration_preserves_partial_indexes(): void
    {
        $this->assertTrue(Schema::hasColumn('payments', 'log_index'));
        if (DB::connection()->getDriverName() === 'pgsql') {
            $indexes = collect(DB::select(
                "SELECT indexname AS name, indexdef AS definition
                 FROM pg_indexes
                 WHERE schemaname = current_schema()
                   AND tablename = 'payments'"
            ))->keyBy('name');
            $this->assertTrue($indexes->has('payments_network_tx_log_unique'));
            $this->assertTrue($indexes->has('payments_network_tx_legacy_unique'));
            $this->assertFalse(
                $indexes->has('payments_network_transaction_hash_unique')
            );
            $logDefinition = $indexes['payments_network_tx_log_unique']->definition;
            $legacyDefinition = $indexes['payments_network_tx_legacy_unique']->definition;
            $this->assertStringContainsString(
                '(network, transaction_hash, log_index)',
                $logDefinition
            );
            $this->assertStringContainsString(
                'WHERE (log_index IS NOT NULL)',
                $logDefinition
            );
            $this->assertStringContainsString(
                '(network, transaction_hash)',
                $legacyDefinition
            );
            $this->assertStringContainsString(
                'WHERE (log_index IS NULL)',
                $legacyDefinition
            );
            return;
        }
        $indexes = collect(DB::select('PRAGMA index_list("payments")'))
            ->keyBy('name');
        $this->assertTrue($indexes->has('payments_network_tx_log_unique'));
        $this->assertTrue($indexes->has('payments_network_tx_legacy_unique'));
        $this->assertFalse(
            $indexes->has('payments_network_transaction_hash_unique')
        );
        $logColumns = collect(
            DB::select('PRAGMA index_info("payments_network_tx_log_unique")')
        )->pluck('name')->all();
        $legacyColumns = collect(
            DB::select('PRAGMA index_info("payments_network_tx_legacy_unique")')
        )->pluck('name')->all();
        $this->assertSame(
            ['network', 'transaction_hash', 'log_index'],
            $logColumns
        );
        $this->assertSame(['network', 'transaction_hash'], $legacyColumns);
        $logSql = collect(DB::select(
            "SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'payments_network_tx_log_unique'"
        ))->first()->sql;
        $legacySql = collect(DB::select(
            "SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'payments_network_tx_legacy_unique'"
        ))->first()->sql;
        $this->assertStringContainsString('WHERE log_index IS NOT NULL', $logSql);
        $this->assertStringContainsString('WHERE log_index IS NULL', $legacySql);
    }
    public function test_distinct_log_indexes_for_one_transaction_are_allowed(): void
    {
        $invoice = $this->makeInvoice();

        $this->insertPayment($invoice->id, '0xshared', 2);
        $this->insertPayment($invoice->id, '0xshared', 3);

        $this->assertSame(
            2,
            DB::table('payments')
                ->where('network', 'arc-testnet')
                ->where('transaction_hash', '0xshared')
                ->count()
        );
    }

    public function test_duplicate_log_index_is_rejected(): void
    {
        $invoice = $this->makeInvoice();

        $this->insertPayment($invoice->id, '0xduplicate', 4);

        try {
            DB::transaction(function () use ($invoice): void {
                $this->insertPayment($invoice->id, '0xduplicate', 4);
            });
            $this->fail('A duplicate transaction/log index should be rejected.');
        } catch (QueryException) {
            $this->assertSame(
                1,
                DB::table('payments')
                    ->where('transaction_hash', '0xduplicate')
                    ->count()
            );
        }
    }

    public function test_legacy_record_and_indexed_record_cannot_share_a_transaction(): void
    {
        $invoice = $this->makeInvoice();

        $this->insertPayment($invoice->id, '0xlegacy-first', null);

        try {
            DB::transaction(function () use ($invoice): void {
                $this->insertPayment($invoice->id, '0xlegacy-first', 1);
            });
            $this->fail('An indexed row must not coexist with a legacy row.');
        } catch (QueryException) {
            $this->assertSame(
                1,
                DB::table('payments')
                    ->where('transaction_hash', '0xlegacy-first')
                    ->count()
            );
        }
    }

    public function test_legacy_record_is_rejected_when_indexed_records_exist(): void
    {
        $invoice = $this->makeInvoice();

        $this->insertPayment($invoice->id, '0xindexed-first', 1);

        try {
            DB::transaction(function () use ($invoice): void {
                $this->insertPayment($invoice->id, '0xindexed-first', null);
            });
            $this->fail('A legacy row must not coexist with indexed rows.');
        } catch (QueryException) {
            $this->assertSame(
                1,
                DB::table('payments')
                    ->where('transaction_hash', '0xindexed-first')
                    ->count()
            );
        }
    }

    private function makeInvoice(): Invoice
    {
        $user = User::create([
            'name' => 'Index Test User',
            'email' => 'index-test@example.test',
            'password' => Hash::make('test-password'),
        ]);

        $merchant = Merchant::create([
            'user_id' => $user->id,
            'name' => 'Index Test Merchant',
            'slug' => 'index-test-merchant',
            'status' => 'active',
            'default_network' => 'arc-testnet',
        ]);

        return Invoice::create([
            'merchant_id' => $merchant->id,
            'amount' => '1.000000',
            'currency' => 'USDC',
            'network' => 'arc-testnet',
            'payment_address' => '0x1111111111111111111111111111111111111111',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
    }

    private function insertPayment(int $invoiceId, string $hash, ?int $logIndex): void
    {
        DB::table('payments')->insert([
            'invoice_id' => $invoiceId,
            'network' => 'arc-testnet',
            'token_contract' => '0xusdc',
            'from_address' => '0x2222222222222222222222222222222222222222',
            'to_address' => '0x1111111111111111111111111111111111111111',
            'amount' => '1.000000',
            'currency' => 'USDC',
            'transaction_hash' => $hash,
            'log_index' => $logIndex,
            'block_number' => 100,
            'confirmations' => 0,
            'status' => 'detected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
