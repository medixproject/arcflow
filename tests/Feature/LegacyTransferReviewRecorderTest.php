<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceAddressAllocation;
use App\Models\MerchantDerivationConfig;
use App\Models\Payment;
use App\Models\User;
use App\Services\LegacyTransferReviewRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyTransferReviewRecorderTest extends TestCase
{
    use RefreshDatabase;

    private int $merchantId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->merchantId = DB::table('merchants')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Legacy review test merchant',
            'slug' => 'legacy-review-' . uniqid(),
            'status' => 'active',
            'default_network' => 'mainnet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeInvoice(
        string $address,
        string $status = 'expired',
        ?\Illuminate\Support\Carbon $expiresAt = null
    ): Invoice {
        return Invoice::create([
            'merchant_id' => $this->merchantId,
            'amount' => '12.500000',
            'currency' => 'USDC',
            'network' => 'mainnet',
            'payment_address' => $address,
            'status' => $status,
            'expires_at' => $expiresAt ?? now()->subHour(),
        ]);
    }

    private function record(string $address, string $tx = '0xreview01', int $logIndex = 0): bool
    {
        return app(LegacyTransferReviewRecorder::class)->recordIfLegacyAddress(
            'mainnet',
            '0x' . str_repeat('a', 40),
            '0x' . str_repeat('b', 40),
            $address,
            '12.500000',
            100,
            $tx,
            105,
            $logIndex,
        );
    }

    public function test_expired_legacy_transfer_is_reviewed_without_marking_invoice_paid(): void
    {
        $invoice = $this->makeInvoice('0x' . str_repeat('1', 40));

        $this->assertTrue($this->record($invoice->payment_address));

        $review = DB::table('legacy_transfer_reviews')->first();
        $this->assertNotNull($review);
        $this->assertSame('late_legacy_transfer', $review->review_reason);
        $this->assertSame(
            json_encode([$invoice->id]),
            json_encode(json_decode($review->candidate_invoice_ids, true))
        );

        $this->assertSame('expired', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertSame(0, DB::table('payments')->count());
    }

    public function test_duplicate_legacy_address_is_flagged_ambiguous_and_not_auto_assigned(): void
    {
        $address = '0x' . str_repeat('2', 40);
        $first = $this->makeInvoice($address);
        $second = $this->makeInvoice($address, 'cancelled');

        $this->assertTrue($this->record($address, '0xduplicate01'));

        $review = DB::table('legacy_transfer_reviews')->first();
        $this->assertSame('duplicate_legacy_address', $review->review_reason);

        $ids = json_decode($review->candidate_invoice_ids, true);
        sort($ids);
        $expected = [$first->id, $second->id];
        sort($expected);
        $this->assertSame($expected, $ids);

        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame('expired', $first->fresh()->status);
        $this->assertSame('cancelled', $second->fresh()->status);
    }

    public function test_same_transfer_log_is_idempotent(): void
    {
        $address = '0x' . str_repeat('3', 40);
        $this->makeInvoice($address);

        $this->assertTrue($this->record($address, '0xrepeat01', 7));
        $this->assertTrue($this->record($address, '0xrepeat01', 7));

        $this->assertSame(1, DB::table('legacy_transfer_reviews')->count());
    }

    public function test_hd_allocated_address_is_not_recorded_as_legacy(): void
    {
        $address = '0x' . str_repeat('4', 40);
        $invoice = $this->makeInvoice($address);

        $config = new MerchantDerivationConfig([
            'merchant_id' => $this->merchantId,
            'network' => 'mainnet',
            'derivation_path' => "m/44'/60'/0'/0",
            'next_index' => 1,
            'is_active' => true,
        ]);
        $config->xpub = 'xpub-test-fixture';
        $config->save();

        InvoiceAddressAllocation::create([
            'invoice_id' => $invoice->id,
            'merchant_id' => $this->merchantId,
            'config_id' => $config->id,
            'network' => 'mainnet',
            'address' => $address,
            'derivation_index' => 0,
        ]);

        $this->assertFalse($this->record($address, '0xhdaddress01'));
        $this->assertSame(0, DB::table('legacy_transfer_reviews')->count());
    }

    public function test_unexpired_pending_invoice_is_not_sent_to_legacy_review(): void
    {
        $address = '0x' . str_repeat('5', 40);
        $this->makeInvoice($address, 'pending', now()->addHour());

        $this->assertFalse($this->record($address, '0xactiveinv01'));
        $this->assertSame(0, DB::table('legacy_transfer_reviews')->count());
    }

    public function test_existing_payment_event_is_not_also_recorded_as_legacy_review(): void
    {
        $address = '0x' . str_repeat('6', 40);
        $invoice = $this->makeInvoice($address);
        $tx = '0xexistingpay01';
        $logIndex = 7;

        Payment::create([
            'invoice_id' => $invoice->id,
            'network' => 'mainnet',
            'token_contract' => '0x' . str_repeat('a', 40),
            'from_address' => '0x' . str_repeat('b', 40),
            'to_address' => $address,
            'amount' => '12.500000',
            'currency' => 'USDC',
            'transaction_hash' => $tx,
            'log_index' => $logIndex,
            'block_number' => 100,
            'confirmations' => 6,
            'status' => 'late_review',
            'detected_at' => now(),
        ]);

        $this->assertFalse($this->record($address, $tx, $logIndex));
        $this->assertSame(1, DB::table('payments')->count());
        $this->assertSame(0, DB::table('legacy_transfer_reviews')->count());
    }


}
