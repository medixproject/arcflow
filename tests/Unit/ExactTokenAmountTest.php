<?php

namespace Tests\Unit;

use App\Services\ExactTokenAmount;
use PHPUnit\Framework\TestCase;

class ExactTokenAmountTest extends TestCase
{
    public function test_converts_eighteen_decimal_raw_amounts_without_floats(): void
    {
        $converter = new ExactTokenAmount();
        self::assertSame('1.000000', $converter->toSixDecimals('1000000000000000000', 18));
        self::assertSame('1.234567', $converter->toSixDecimals('1234567890123456789', 18));
        self::assertSame('0.000000', $converter->toSixDecimals('999999999999', 18));
        self::assertSame('9007199.254740', $converter->toSixDecimals('9007199254740993123456789', 18));
    }

    public function test_handles_six_and_lower_decimals(): void
    {
        $converter = new ExactTokenAmount();
        self::assertSame('12.345678', $converter->toSixDecimals('12345678', 6));
        self::assertSame('12.340000', $converter->toSixDecimals('1234', 2));
    }
}
