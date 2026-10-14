<?php

namespace App\Services;

use InvalidArgumentException;
use Nimiq\XPub;

class XpubAddressDeriver
{
    public function validate(string $xpub): void
    {
        $xpub = trim($xpub);
        if ($xpub === '' || strlen($xpub) > 512) {
            throw new InvalidArgumentException('An extended public key is required.');
        }

        try {
            $root = XPub::fromString($xpub);
            // Force a child derivation and Ethereum address conversion to reject malformed key material.
            $address = $root->derive(0)->toAddress('eth');
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('The extended public key is invalid or cannot derive Ethereum addresses.', 0, $e);
        }

        if (!is_string($address) || !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new InvalidArgumentException('The extended public key did not produce a valid EVM address.');
        }
    }

    public function addressAt(string $xpub, int $index): string
    {
        if ($index < 0 || $index > 2147483647) {
            throw new InvalidArgumentException('Derivation index is outside the supported non-hardened range.');
        }
        $this->validate($xpub);
        try {
            $address = XPub::fromString(trim($xpub))->derive($index)->toAddress('eth');
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Unable to derive the requested child address.', 0, $e);
        }
        if (!is_string($address) || !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new InvalidArgumentException('Derived address has an invalid format.');
        }
        return strtolower($address);
    }
}
