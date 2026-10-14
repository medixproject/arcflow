<?php

namespace App\Support;

/** Builds block-explorer links, but only for configured http(s) explorers. */
final class Explorer
{
    public static function txUrl(?string $hash): ?string
    {
        $base = trim((string) config('arc.explorer_url'));

        if ($hash === null || $hash === '' || !preg_match('#^https?://#i', $base)) {
            return null;
        }

        if (!preg_match('/^0x[0-9a-fA-F]{8,100}$/', $hash)) {
            return null;
        }

        return rtrim($base, '/') . '/tx/' . $hash;
    }

    public static function shortHash(?string $hash): string
    {
        $hash = (string) $hash;

        return strlen($hash) > 18 ? substr($hash, 0, 10) . '…' . substr($hash, -6) : $hash;
    }
}
