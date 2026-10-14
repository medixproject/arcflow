<?php

namespace App\Support;

/**
 * Exact arithmetic on USDC amounts with six decimal places.
 * Pure string maths: no floats and no bcmath, so values of any realistic size stay exact.
 */
final class Decimal6
{
    /** Normalise a DB/driver value (string, int or float) to "I.FFFFFF". */
    public static function normalize(mixed $value): string
    {
        if (is_int($value)) {
            return $value . '.000000';
        }

        if (is_float($value)) {
            return number_format($value, 6, '.', '');
        }

        $value = trim((string) $value);

        if (preg_match('/^[0-9]+$/D', $value)) {
            return $value . '.000000';
        }

        if (preg_match('/^([0-9]+)\.([0-9]{1,6})$/D', $value, $m)) {
            return $m[1] . '.' . str_pad($m[2], 6, '0');
        }

        throw new \InvalidArgumentException('Unexpected amount format.');
    }

    /** Amount as a digit string in millionths, without leading zeros ("0" for zero). */
    private static function micro(mixed $value): string
    {
        [$integer, $fraction] = explode('.', self::normalize($value), 2);
        $digits = ltrim($integer . $fraction, '0');

        return $digits === '' ? '0' : $digits;
    }

    private static function fromMicro(string $digits): string
    {
        $digits = str_pad(ltrim($digits, '0'), 7, '0', STR_PAD_LEFT);

        return substr($digits, 0, -6) . '.' . substr($digits, -6);
    }

    /** Returns -1, 0 or 1. */
    public static function cmp(mixed $a, mixed $b): int
    {
        $x = self::micro($a);
        $y = self::micro($b);

        if (strlen($x) !== strlen($y)) {
            return strlen($x) < strlen($y) ? -1 : 1;
        }

        return strcmp($x, $y) <=> 0;
    }

    public static function add(mixed $a, mixed $b): string
    {
        $x = self::micro($a);
        $y = self::micro($b);
        $length = max(strlen($x), strlen($y));
        $x = str_pad($x, $length, '0', STR_PAD_LEFT);
        $y = str_pad($y, $length, '0', STR_PAD_LEFT);

        $carry = 0;
        $out = '';
        for ($i = $length - 1; $i >= 0; $i--) {
            $sum = (int) $x[$i] + (int) $y[$i] + $carry;
            $out = ($sum % 10) . $out;
            $carry = intdiv($sum, 10);
        }

        if ($carry > 0) {
            $out = $carry . $out;
        }

        return self::fromMicro($out);
    }

    /** a - b, never below zero. */
    public static function sub(mixed $a, mixed $b): string
    {
        if (self::cmp($a, $b) <= 0) {
            return '0.000000';
        }

        $x = self::micro($a);
        $y = str_pad(self::micro($b), strlen($x), '0', STR_PAD_LEFT);

        $borrow = 0;
        $out = '';
        for ($i = strlen($x) - 1; $i >= 0; $i--) {
            $digit = (int) $x[$i] - (int) $y[$i] - $borrow;
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $out = $digit . $out;
        }

        return self::fromMicro($out);
    }

    /** Whole-number percentage of $part in $whole, 0..100 (display only). */
    public static function percent(mixed $part, mixed $whole): int
    {
        if (self::cmp($whole, '0') <= 0 || self::cmp($part, '0') <= 0) {
            return 0;
        }

        if (self::cmp($part, $whole) >= 0) {
            return 100;
        }

        $percent = (int) floor(((float) self::micro($part)) * 100 / (float) self::micro($whole));

        return max(1, min(99, $percent));
    }

    /** Human display: thousands separators, at least two decimals, trailing zeros trimmed. */
    public static function display(mixed $value): string
    {
        [$integer, $fraction] = explode('.', self::normalize($value), 2);
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integer);
        $fraction = str_pad(rtrim($fraction, '0'), 2, '0');

        return $integer . '.' . $fraction;
    }
}
