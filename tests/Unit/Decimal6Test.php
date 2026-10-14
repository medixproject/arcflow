<?php

namespace Tests\Unit;

use App\Support\Decimal6;
use PHPUnit\Framework\TestCase;

class Decimal6Test extends TestCase
{
    public function test_normalize_accepts_strings_ints_and_floats(): void
    {
        $this->assertSame('1.000000', Decimal6::normalize('1'));
        $this->assertSame('1.500000', Decimal6::normalize('1.5'));
        $this->assertSame('25.000000', Decimal6::normalize(25));
        $this->assertSame('0.500000', Decimal6::normalize(0.5));
        $this->assertSame('10.123456', Decimal6::normalize('10.123456'));
    }

    public function test_normalize_rejects_garbage_and_over_precision(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Decimal6::normalize('1.0000001');
    }

    public function test_compare(): void
    {
        $this->assertSame(0, Decimal6::cmp('1.000000', '1'));
        $this->assertSame(-1, Decimal6::cmp('0.999999', '1.000000'));
        $this->assertSame(1, Decimal6::cmp('10.000000', '9.999999'));
        $this->assertSame(1, Decimal6::cmp('100', '99.999999'));
    }

    public function test_add_and_sub_are_exact(): void
    {
        $this->assertSame('0.300000', Decimal6::add('0.1', '0.2'));
        $this->assertSame('1000000000000000000000000.000000', Decimal6::add('999999999999999999999999.999999', '0.000001'));
        $this->assertSame('1.500000', Decimal6::sub('2.000000', '0.500000'));
        $this->assertSame('0.000001', Decimal6::sub('1.000000', '0.999999'));
        $this->assertSame('0.000000', Decimal6::sub('1.000000', '1.000000'));
        $this->assertSame('0.000000', Decimal6::sub('0.500000', '2.000000'));
        $this->assertSame('99999999999999999999.999999', Decimal6::sub('100000000000000000000.000000', '0.000001'));
    }

    public function test_percent_is_bounded_and_never_shows_full_early(): void
    {
        $this->assertSame(0, Decimal6::percent('0', '2'));
        $this->assertSame(25, Decimal6::percent('0.5', '2'));
        $this->assertSame(1, Decimal6::percent('0.000001', '2'));
        $this->assertSame(99, Decimal6::percent('1.999999', '2'));
        $this->assertSame(100, Decimal6::percent('2', '2'));
        $this->assertSame(100, Decimal6::percent('3', '2'));
        $this->assertSame(0, Decimal6::percent('1', '0'));
    }

    public function test_display_formatting(): void
    {
        $this->assertSame('1.00', Decimal6::display('1.000000'));
        $this->assertSame('1.50', Decimal6::display('1.500000'));
        $this->assertSame('0.123456', Decimal6::display('0.123456'));
        $this->assertSame('1,234.50', Decimal6::display('1234.5'));
        $this->assertSame('0.00', Decimal6::display('0'));
    }
}
