<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceBalance;
use Tests\TestCase;

class InvoiceBalanceTest extends TestCase
{
    private function invoice(string $amount, string $status, $expiresAt, array $payments = []): Invoice
    {
        $invoice = Invoice::make([
            'amount' => $amount,
            'currency' => 'USDC',
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
        $invoice->setRelation('payments', collect(array_map(
            fn (array $p) => Payment::make($p),
            $payments
        )));

        return $invoice;
    }

    public function test_partial_payment_reports_paid_and_remaining(): void
    {
        $invoice = $this->invoice('2.000000', 'underpaid', now()->addHour(), [
            ['amount' => '0.500000', 'status' => 'underpaid'],
        ]);

        $summary = (new InvoiceBalance())->summarize($invoice);

        $this->assertSame('0.500000', $summary['paid']);
        $this->assertSame('1.500000', $summary['remaining']);
        $this->assertSame('0.000000', $summary['excess']);
        $this->assertSame(25, $summary['percent']);
        $this->assertTrue($summary['partial']);
        $this->assertTrue($summary['accepting']);
        $this->assertSame('underpaid', $summary['status']);
    }

    public function test_several_counted_payments_add_up_and_late_review_is_ignored(): void
    {
        $invoice = $this->invoice('2.000000', 'paid', now()->addHour(), [
            ['amount' => '0.500000', 'status' => 'underpaid'],
            ['amount' => '1.500000', 'status' => 'confirmed'],
            ['amount' => '9.000000', 'status' => 'late_review'],
        ]);

        $summary = (new InvoiceBalance())->summarize($invoice);

        $this->assertSame('2.000000', $summary['paid']);
        $this->assertSame('0.000000', $summary['remaining']);
        $this->assertFalse($summary['partial']);
        $this->assertSame(100, $summary['percent']);
        $this->assertSame('paid', $summary['status']);
    }

    public function test_unsettled_invoice_past_expiry_is_displayed_as_expired(): void
    {
        $summary = (new InvoiceBalance())->summarize($this->invoice('1.000000', 'pending', now()->subMinute()));

        $this->assertSame('expired', $summary['status']);
        $this->assertTrue($summary['expired']);
        $this->assertFalse($summary['accepting']);
        $this->assertFalse($summary['has_paid']);
    }

    public function test_partially_paid_invoice_past_expiry_is_expired_but_keeps_its_paid_amount(): void
    {
        $summary = (new InvoiceBalance())->summarize($this->invoice('2.000000', 'underpaid', now()->subMinute(), [
            ['amount' => '0.500000', 'status' => 'underpaid'],
        ]));

        $this->assertSame('expired', $summary['status']);
        $this->assertTrue($summary['has_paid']);
        $this->assertSame('1.500000', $summary['remaining']);
        $this->assertFalse($summary['accepting']);
    }

    public function test_paid_confirming_and_overpaid_invoices_are_not_turned_into_expired(): void
    {
        $balance = new InvoiceBalance();

        $this->assertSame('paid', $balance->summarize($this->invoice('1.000000', 'paid', now()->subDay()))['status']);
        $this->assertSame('confirming', $balance->summarize($this->invoice('1.000000', 'confirming', now()->subDay()))['status']);
        $this->assertSame('overpaid', $balance->summarize($this->invoice('1.000000', 'overpaid', now()->subDay()))['status']);
    }

    public function test_overpayment_reports_the_excess(): void
    {
        $summary = (new InvoiceBalance())->summarize($this->invoice('1.000000', 'overpaid', now()->addHour(), [
            ['amount' => '1.250000', 'status' => 'overpaid'],
        ]));

        $this->assertSame('0.250000', $summary['excess']);
        $this->assertSame('0.000000', $summary['remaining']);
        $this->assertFalse($summary['partial']);
        $this->assertFalse($summary['accepting']);
    }
}
