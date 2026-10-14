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

class PaymentAccumulationTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '0x2222222222222222222222222222222222222222';

    private function invoice(string $amount, string $address): Invoice
    {
        $user = User::create([
            'name' => 'Accumulation User',
            'email' => 'accumulation@example.test',
            'password' => Hash::make('password'),
        ]);
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'name' => 'Accumulation Merchant',
            'slug' => 'accumulation-test',
            'status' => 'active',
            'default_network' => 'arc-testnet',
        ]);

        return Invoice::create([
            'merchant_id' => $merchant->id,
            'amount' => $amount,
            'currency' => 'USDC',
            'network' => 'arc-testnet',
            'payment_address' => $address,
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
    }

    private function pay(Invoice $invoice, string $amount, int $block, string $tx, int $latest): ?Payment
    {
        return app(PaymentMatcher::class)->matchTransfer(
            'arc-testnet', '0xusdc', self::FROM, $invoice->payment_address,
            $amount, $block, $tx, $latest, 0
        );
    }

    public function test_partial_payments_accumulate_until_the_invoice_is_paid(): void
    {
        config(['arc.required_confirmations' => 1]);
        $invoice = $this->invoice('2.000000', '0x7777777777777777777777777777777777777777');

        $first = $this->pay($invoice, '0.500000', 100, '0xtx-part-a', 100);
        $this->assertSame('underpaid', $first->status);
        $this->assertSame('underpaid', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);

        $second = $this->pay($invoice, '1.500000', 101, '0xtx-part-b', 101);
        $this->assertSame('confirmed', $second->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
        $this->assertSame(2, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_partial_payments_that_exceed_the_invoice_are_overpaid_not_paid(): void
    {
        config(['arc.required_confirmations' => 1]);
        $invoice = $this->invoice('2.000000', '0x8888888888888888888888888888888888888888');

        $this->pay($invoice, '0.500000', 100, '0xtx-over-a', 100);
        $second = $this->pay($invoice, '2.000000', 101, '0xtx-over-b', 101);

        $this->assertSame('overpaid', $second->status);
        $this->assertSame('overpaid', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
    }

    public function test_top_up_waits_for_required_confirmations_before_marking_paid(): void
    {
        config(['arc.required_confirmations' => 3]);
        $invoice = $this->invoice('1.000000', '0x9999999999999999999999999999999999999999');

        $this->pay($invoice, '0.400000', 100, '0xtx-conf-a', 100);
        $second = $this->pay($invoice, '0.600000', 101, '0xtx-conf-b', 101);

        $this->assertSame('confirming', $second->status);
        $this->assertSame('confirming', $invoice->fresh()->status);

        $confirmed = app(PaymentMatcher::class)->confirmPendingPayments('arc-testnet', 103);

        $this->assertCount(1, $confirmed);
        $this->assertSame('confirmed', $second->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_extra_transfer_while_confirming_keeps_the_invoice_overpaid(): void
    {
        config(['arc.required_confirmations' => 3]);
        $invoice = $this->invoice('1.000000', '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $first = $this->pay($invoice, '1.000000', 100, '0xtx-extra-a', 100);
        $this->assertSame('confirming', $first->status);

        $extra = $this->pay($invoice, '0.500000', 101, '0xtx-extra-b', 101);
        $this->assertSame('overpaid', $extra->status);
        $this->assertSame('overpaid', $invoice->fresh()->status);

        $confirmed = app(PaymentMatcher::class)->confirmPendingPayments('arc-testnet', 102);

        $this->assertCount(0, $confirmed);
        $this->assertSame('confirmed', $first->fresh()->status);
        $this->assertSame('overpaid', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
    }

    public function test_decimal_addition_is_exact(): void
    {
        $matcher = new PaymentMatcher();
        $method = new \ReflectionMethod($matcher, 'addDecimal');
        $method->setAccessible(true);

        $this->assertSame('0.300000', $method->invoke($matcher, '0.100000', '0.200000'));
        $this->assertSame('0.000000', $method->invoke($matcher, '0.000000', '0.000000'));
        $this->assertSame('2.000000', $method->invoke($matcher, '0.500000', '1.500000'));
        $this->assertSame(
            '1000000000000000000000000.000000',
            $method->invoke($matcher, '999999999999999999999999.999999', '0.000001')
        );
    }
}
