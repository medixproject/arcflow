<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_payment_waits_for_required_confirmations(): void
    {
        config(['arc.required_confirmations' => 3]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'matcher@example.test',
            'password' => Hash::make('password'),
        ]);
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'name' => 'Test Merchant',
            'slug' => 'matcher-test',
            'status' => 'active',
            'default_network' => 'arc-testnet',
        ]);
        $invoice = Invoice::create([
            'merchant_id' => $merchant->id,
            'amount' => '1.000000',
            'currency' => 'USDC',
            'network' => 'arc-testnet',
            'payment_address' => '0x1111111111111111111111111111111111111111',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $payment = app(PaymentMatcher::class)->matchTransfer(
            'arc-testnet',
            '0xusdc',
            '0x2222222222222222222222222222222222222222',
            $invoice->payment_address,
            '1.000000',
            100,
            '0xtx-confirming',
            101,
        );

        $this->assertSame('confirming', $payment->status);
        $this->assertSame(2, $payment->confirmations);
        $this->assertSame('confirming', $invoice->fresh()->status);

        $confirmed = app(PaymentMatcher::class)->confirmPendingPayments('arc-testnet', 102);

        $this->assertCount(1, $confirmed);
        $this->assertSame('confirmed', $payment->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(3, $payment->fresh()->confirmations);
    }

    public function test_expired_invoice_payment_moves_to_late_review_instead_of_paid(): void
    {
        config(['arc.required_confirmations' => 2]);

        $user = User::create([
            'name' => 'Expiry User',
            'email' => 'expiry@example.test',
            'password' => Hash::make('password'),
        ]);
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'name' => 'Expiry Merchant',
            'slug' => 'expiry-test',
            'status' => 'active',
            'default_network' => 'arc-testnet',
        ]);
        $invoice = Invoice::create([
            'merchant_id' => $merchant->id,
            'amount' => '1.000000',
            'currency' => 'USDC',
            'network' => 'arc-testnet',
            'payment_address' => '0x5555555555555555555555555555555555555555',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $payment = app(PaymentMatcher::class)->matchTransfer(
            'arc-testnet',
            '0xusdc',
            '0x6666666666666666666666666666666666666666',
            $invoice->payment_address,
            '1.000000',
            300,
            '0xtx-expires-before-confirmation',
            300,
        );

        $this->assertNotNull($payment);
        $this->assertSame('confirming', $payment->status);

        // Simulate expiry between first detection and the next confirmation pass.
        $invoice->update(['expires_at' => now()->subMinute()]);

        $confirmed = app(PaymentMatcher::class)
            ->confirmPendingPayments('arc-testnet', 301);

        $this->assertCount(0, $confirmed);
        $this->assertSame('late_review', $payment->fresh()->status);
        $this->assertNotSame('paid', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
    }

    public function test_duplicate_transaction_is_ignored(): void
    {
        $user = User::create([
            'name' => 'Duplicate User',
            'email' => 'duplicate@example.test',
            'password' => Hash::make('password'),
        ]);
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'name' => 'Duplicate Merchant',
            'slug' => 'duplicate-test',
            'status' => 'active',
            'default_network' => 'arc-testnet',
        ]);
        $invoice = Invoice::create([
            'merchant_id' => $merchant->id,
            'amount' => '1.000000',
            'currency' => 'USDC',
            'network' => 'arc-testnet',
            'payment_address' => '0x3333333333333333333333333333333333333333',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $matcher = app(PaymentMatcher::class);
        $first = $matcher->matchTransfer('arc-testnet', '0xusdc', '0x4444444444444444444444444444444444444444', $invoice->payment_address, '1.000000', 200, '0xtx-duplicate', 200);
        $second = $matcher->matchTransfer('arc-testnet', '0xusdc', '0x4444444444444444444444444444444444444444', $invoice->payment_address, '1.000000', 200, '0xtx-duplicate', 200);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, Payment::where('transaction_hash', '0xtx-duplicate')->count());
    }

    public function test_distinct_transfer_logs_in_one_transaction_are_recorded_once_each(): void
    {
        config(['arc.required_confirmations' => 1]);

        $user = User::create([
            'name' => 'Log Index User',
            'email' => 'log-index@example.test',
            'password' => Hash::make('password'),
        ]);
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'name' => 'Log Index Merchant',
            'slug' => 'log-index-test',
            'status' => 'active',
            'default_network' => 'arc-testnet',
        ]);

        $invoiceA = Invoice::create([
            'merchant_id' => $merchant->id,
            'amount' => '1.000000',
            'currency' => 'USDC',
            'network' => 'arc-testnet',
            'payment_address' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
        $invoiceB = Invoice::create([
            'merchant_id' => $merchant->id,
            'amount' => '2.000000',
            'currency' => 'USDC',
            'network' => 'arc-testnet',
            'payment_address' => '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $matcher = app(PaymentMatcher::class);
        $first = $matcher->matchTransfer(
            'arc-testnet', '0xusdc', '0xcccccccccccccccccccccccccccccccccccccccc',
            $invoiceA->payment_address, '1.000000', 400, '0xshared-tx', 400, 2
        );
        $second = $matcher->matchTransfer(
            'arc-testnet', '0xusdc', '0xdddddddddddddddddddddddddddddddddddddddd',
            $invoiceB->payment_address, '2.000000', 400, '0xshared-tx', 400, 3
        );
        $replay = $matcher->matchTransfer(
            'arc-testnet', '0xusdc', '0xcccccccccccccccccccccccccccccccccccccccc',
            $invoiceA->payment_address, '1.000000', 400, '0xshared-tx', 400, 2
        );

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNull($replay);
        $this->assertSame(2, Payment::where('network', 'arc-testnet')
            ->where('transaction_hash', '0xshared-tx')->count());
        $this->assertSame([2, 3], Payment::where('transaction_hash', '0xshared-tx')
            ->orderBy('log_index')->pluck('log_index')->all());
    }

}
