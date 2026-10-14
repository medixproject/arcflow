<?php

namespace App\Services;

use InvalidArgumentException;

/** Converts raw unsigned token integers to a decimal string with at most six fractional digits, without floats. */
class ExactTokenAmount
{
    public function toSixDecimals(string $raw, int $tokenDecimals): string
    {
        $raw = trim($raw);
        if ($tokenDecimals < 0 || $tokenDecimals > 255 || !preg_match('/^\d+$/', $raw)) {
            throw new InvalidArgumentException('Raw token amount or decimals are invalid.');
        }
        $raw = ltrim($raw, '0');
        if ($raw === '') $raw = '0';

        if ($tokenDecimals > 6) {
            $drop = $tokenDecimals - 6;
            $micro = strlen($raw) <= $drop ? '0' : substr($raw, 0, -$drop);
        } elseif ($tokenDecimals < 6) {
            $micro = $raw . str_repeat('0', 6 - $tokenDecimals);
        } else {
            $micro = $raw;
        }
        $micro = ltrim($micro, '0');
        if ($micro === '') $micro = '0';
        if (strlen($micro) <= 6) $micro = str_pad($micro, 7, '0', STR_PAD_LEFT);
        $whole = substr($micro, 0, -6);
        $fraction = substr($micro, -6);
        return $whole . '.' . $fraction;
    }
}
