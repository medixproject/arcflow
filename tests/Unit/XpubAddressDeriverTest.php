<?php

namespace Tests\Unit;

use App\Services\XpubAddressDeriver;
use InvalidArgumentException;
use Nimiq\XPub;
use Tests\TestCase;

class XpubAddressDeriverTest extends TestCase
{
    // Public test fixture: account-level xpub for m/44'/60'/0'.
    private const ACCOUNT_XPUB =
        'xpub6CadpMraPwst8ZHcjTq6M2FYQQ37kou4LFivCP4dhatoHQjHZgSGpm3eGARnAxnZwfv4U6rar7Zvrnksejd4kwLY2DLGYLdTVdowrQdeWjb';

    // Published Ethereum address for m/44'/60'/0'/0/0.
    private const EXPECTED_ADDRESS_0 =
        '0x2161dedc3be05b7bb5aa16154bcbd254e9e9eb68';

    private function changeLevelXpub(): string
    {
        return XPub::fromString(self::ACCOUNT_XPUB)
            ->derive(0)
            ->toString();
    }

    public function test_real_derivation_matches_known_ethereum_address(): void
    {
        $deriver = new XpubAddressDeriver();
        $changeXpub = $this->changeLevelXpub();

        $this->assertSame(
            self::EXPECTED_ADDRESS_0,
            $deriver->addressAt($changeXpub, 0)
        );

        $address1 = $deriver->addressAt($changeXpub, 1);

        $this->assertMatchesRegularExpression(
            '/^0x[a-f0-9]{40}$/',
            $address1
        );
        $this->assertNotSame(self::EXPECTED_ADDRESS_0, $address1);
    }

    public function test_malformed_extended_public_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new XpubAddressDeriver())->addressAt('not-an-xpub', 0);
    }

    public function test_hardened_or_out_of_range_index_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new XpubAddressDeriver())->addressAt(
            $this->changeLevelXpub(),
            2147483648
        );
    }
}
