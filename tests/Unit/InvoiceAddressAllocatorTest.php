<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceAddressAllocation;
use App\Models\Merchant;
use App\Models\MerchantDerivationConfig;
use App\Models\User;
use App\Services\InvoiceAddressAllocator;
use App\Services\XpubAddressDeriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class InvoiceAddressAllocatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeMerchant(): Merchant
    {
        $user = User::create([
            'name' => 'HD Test User',
            'email' => 'hd-test@example.test',
            'password' => Hash::make('test-password'),
        ]);

        return Merchant::create([
            'user_id' => $user->id,
            'name' => 'HD Test Merchant',
            'slug' => 'hd-test-merchant',
            'status' => 'active',
            'default_network' => 'arc-testnet',
        ]);
    }

    private function makeConfig(Merchant $merchant, string $xpub = 'test-public-key'): MerchantDerivationConfig
    {
        $config = new MerchantDerivationConfig();
        $config->merchant_id = $merchant->id;
        $config->network = 'arc-testnet';
        $config->xpub = $xpub;
        $config->derivation_path = "m/44'/60'/0'/0";
        $config->next_index = 0;
        $config->is_active = true;
        $config->save();

        return $config;
    }

    private function makeInvoice(Merchant $merchant, string $address): Invoice
    {
        return Invoice::create([
            'merchant_id' => $merchant->id,
            'amount' => '1.000000',
            'currency' => 'USDC',
            'network' => 'arc-testnet',
            'payment_address' => $address,
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_xpub_is_encrypted_at_rest_and_decrypted_by_model(): void
    {
        $merchant = $this->makeMerchant();
        $config = $this->makeConfig($merchant, 'public-test-key');

        $stored = DB::table('merchant_derivation_configs')
            ->where('id', $config->id)
            ->value('encrypted_xpub');

        $this->assertNotSame('public-test-key', $stored);
        $this->assertSame('public-test-key', $config->fresh()->xpub);
    }

    public function test_allocator_skips_address_already_used_by_an_old_invoice(): void
    {
        $merchant = $this->makeMerchant();
        $config = $this->makeConfig($merchant);
        $oldAddress = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $newAddress = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

        $this->makeInvoice($merchant, strtoupper(substr($oldAddress, 0, 2)) . substr($oldAddress, 2));

        $this->mock(XpubAddressDeriver::class, function ($mock) use ($oldAddress, $newAddress) {
            $mock->shouldReceive('addressAt')->with('test-public-key', 0)
                ->once()->andReturn($oldAddress);
            $mock->shouldReceive('addressAt')->with('test-public-key', 1)
                ->once()->andReturn($newAddress);
        });

        $invoice = app(InvoiceAddressAllocator::class)->createWithDerivedAddress(
            $merchant,
            'arc-testnet',
            fn (string $address) => $this->makeInvoice($merchant, $address),
        );

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame($newAddress, $invoice->payment_address);
        $this->assertSame(2, $config->fresh()->next_index);

        $allocation = InvoiceAddressAllocation::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame($newAddress, $allocation->address);
        $this->assertSame(1, $allocation->derivation_index);
        $this->assertSame(1, InvoiceAddressAllocation::count());
    }

    public function test_failed_invoice_creation_rolls_back_index_and_allocation(): void
    {
        $merchant = $this->makeMerchant();
        $config = $this->makeConfig($merchant);
        $address = '0xcccccccccccccccccccccccccccccccccccccccc';

        $this->mock(XpubAddressDeriver::class, function ($mock) use ($address) {
            $mock->shouldReceive('addressAt')->with('test-public-key', 0)
                ->once()->andReturn($address);
        });

        try {
            app(InvoiceAddressAllocator::class)->createWithDerivedAddress(
                $merchant,
                'arc-testnet',
                fn (string $derivedAddress) => throw new RuntimeException('Simulated invoice failure'),
            );
            $this->fail('Expected invoice creation to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated invoice failure', $e->getMessage());
        }

        $this->assertSame(0, $config->fresh()->next_index);
        $this->assertSame(0, InvoiceAddressAllocation::count());
        $this->assertSame(0, Invoice::count());
    }

    public function test_allocator_returns_null_when_no_active_config_exists(): void
    {
        $merchant = $this->makeMerchant();

        $result = app(InvoiceAddressAllocator::class)->createWithDerivedAddress(
            $merchant,
            'arc-testnet',
            fn (string $address) => $this->makeInvoice($merchant, $address),
        );

        $this->assertNull($result);
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, InvoiceAddressAllocation::count());
    }
}
