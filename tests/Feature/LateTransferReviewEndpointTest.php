<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LateTransferReviewEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['arc.network' => 'mainnet']);
    }

    private function makeMerchant(string $name): array
    {
        $user = User::factory()->create();

        $merchantId = DB::table('merchants')->insertGetId([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . bin2hex(random_bytes(4)),
            'status' => 'active',
            'default_network' => 'mainnet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rawKey = 'af_' . bin2hex(random_bytes(24));

        ApiKey::create([
            'merchant_id' => $merchantId,
            'name' => 'Endpoint test key',
            'key_prefix' => substr($rawKey, 0, 20),
            'key_hash' => hash('sha256', $rawKey),
        ]);

        return [
            'id' => $merchantId,
            'key' => $rawKey,
        ];
    }

    private function makeInvoice(
        int $merchantId,
        string $network = 'mainnet',
        string $status = 'expired'
    ): Invoice {
        return Invoice::create([
            'merchant_id' => $merchantId,
            'amount' => '12.500000',
            'currency' => 'USDC',
            'network' => $network,
            'payment_address' => '0x' . bin2hex(random_bytes(20)),
            'status' => $status,
            'expires_at' => now()->subHour(),
        ]);
    }

    private function makeLatePayment(
        Invoice $invoice,
        string $network = 'mainnet'
    ): Payment {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'network' => $network,
            'token_contract' => '0x' . str_repeat('a', 40),
            'from_address' => '0x' . str_repeat('b', 40),
            'to_address' => $invoice->payment_address,
            'amount' => '12.500000',
            'currency' => 'USDC',
            'transaction_hash' => '0x' . bin2hex(random_bytes(32)),
            'log_index' => random_int(0, 1000000),
            'block_number' => 100,
            'confirmations' => 1,
            'status' => 'late_review',
        ]);
    }

    private function getReviews(string $key)
    {
        return $this->withToken($key)
            ->getJson('/api/v1/reviews/late-transfers');
    }

    public function test_endpoint_rejects_requests_without_an_api_key(): void
    {
        $this->getJson('/api/v1/reviews/late-transfers')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_endpoint_rejects_invalid_and_revoked_api_keys(): void
    {
        $this->withToken('af_' . str_repeat('f', 48))
            ->getJson('/api/v1/reviews/late-transfers')
            ->assertUnauthorized();

        $merchant = $this->makeMerchant('Revoked key merchant');

        ApiKey::where('key_hash', hash('sha256', $merchant['key']))
            ->update(['revoked_at' => now()]);

        $this->getReviews($merchant['key'])
            ->assertUnauthorized();
    }

    public function test_late_payments_are_scoped_to_the_authenticated_merchant_and_network(): void
    {
        $merchantA = $this->makeMerchant('Merchant Alpha');
        $merchantB = $this->makeMerchant('Merchant Beta');

        $invoiceA = $this->makeInvoice($merchantA['id']);
        $invoiceB = $this->makeInvoice($merchantB['id']);
        $invoiceOtherNetwork = $this->makeInvoice(
            $merchantA['id'],
            'another-network'
        );

        $paymentA = $this->makeLatePayment($invoiceA);
        $this->makeLatePayment($invoiceB);
        $this->makeLatePayment($invoiceOtherNetwork, 'another-network');

        $responseA = $this->getReviews($merchantA['key'])
            ->assertOk()
            ->assertJsonPath('meta.read_only', true)
            ->assertJsonPath('meta.limit_per_type', 100);

        $paymentsA = collect($responseA->json('data.late_payments'));

        $this->assertCount(1, $paymentsA);
        $this->assertSame(
            $invoiceA->public_id,
            $paymentsA->first()['invoice_id']
        );
        $this->assertSame($paymentA->id, $paymentsA->first()['id']);

        $responseB = $this->getReviews($merchantB['key'])->assertOk();

        $paymentsB = collect($responseB->json('data.late_payments'));

        $this->assertCount(1, $paymentsB);
        $this->assertSame(
            $invoiceB->public_id,
            $paymentsB->first()['invoice_id']
        );

        $this->assertNotSame(
            $paymentsA->first()['id'],
            $paymentsB->first()['id']
        );
    }

    public function test_legacy_review_exposes_only_the_authenticated_merchants_candidate_invoice_ids(): void
    {
        $merchantA = $this->makeMerchant('Legacy Alpha');
        $merchantB = $this->makeMerchant('Legacy Beta');

        $invoiceA = $this->makeInvoice($merchantA['id']);
        $invoiceB = $this->makeInvoice($merchantB['id']);

        $reviewId = DB::table('legacy_transfer_reviews')->insertGetId([
            'network' => 'mainnet',
            'token_contract' => '0x' . str_repeat('a', 40),
            'from_address' => '0x' . str_repeat('b', 40),
            'to_address' => '0x' . str_repeat('c', 40),
            'amount' => '12.500000',
            'transaction_hash' => '0x' . bin2hex(random_bytes(32)),
            'log_index' => 3,
            'block_number' => 100,
            'confirmations' => 1,
            'candidate_invoice_ids' => json_encode([
                $invoiceA->id,
                $invoiceB->id,
                999999999,
            ]),
            'review_reason' => 'duplicate_legacy_address',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $responseA = $this->getReviews($merchantA['key'])->assertOk();
        $reviewsA = collect($responseA->json('data.legacy_transfers'));

        $this->assertCount(1, $reviewsA);
        $this->assertSame($reviewId, $reviewsA->first()['id']);
        $this->assertSame(
            [$invoiceA->id],
            $reviewsA->first()['candidate_invoice_ids']
        );

        $responseB = $this->getReviews($merchantB['key'])->assertOk();
        $reviewsB = collect($responseB->json('data.legacy_transfers'));

        $this->assertCount(1, $reviewsB);
        $this->assertSame(
            [$invoiceB->id],
            $reviewsB->first()['candidate_invoice_ids']
        );
    }

    public function test_reading_reviews_does_not_change_invoice_or_payment_statuses(): void
    {
        $merchant = $this->makeMerchant('Read only merchant');
        $invoice = $this->makeInvoice($merchant['id'], 'mainnet', 'expired');
        $payment = $this->makeLatePayment($invoice);

        $this->assertSame('expired', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertSame('late_review', $payment->fresh()->status);

        $this->getReviews($merchant['key'])->assertOk();

        $this->assertSame('expired', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertSame('late_review', $payment->fresh()->status);
    }
}
