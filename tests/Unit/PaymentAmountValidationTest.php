<?php

namespace Tests\Unit;

use App\Services\PaymentMatcher;
use Tests\TestCase;

class PaymentAmountValidationTest extends TestCase
{
    public function test_malformed_or_over_precision_amounts_are_rejected(): void
    {
        $matcher = new PaymentMatcher();
        $method = new \ReflectionMethod($matcher, 'compareDecimal');
        $method->setAccessible(true);

        foreach (['1.1234567', '-1', '1e3', 'abc', '1.2.3', ''] as $amount) {
            try {
                $method->invoke($matcher, $amount, '1.000000');
                $this->fail("Expected invalid amount to be rejected: {$amount}");
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('Payment amounts', $exception->getMessage());
            }
        }
    }
}
