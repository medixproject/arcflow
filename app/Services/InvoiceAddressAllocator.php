<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceAddressAllocation;
use App\Models\Merchant;
use App\Models\MerchantDerivationConfig;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceAddressAllocator
{
    public function __construct(
        private readonly XpubAddressDeriver $deriver,
        private readonly InvoiceAddressReservation $reservations
    ) {}

    /**
     * Create an invoice and permanently register its derived address atomically.
     * Returns null when no active HD config exists, allowing legacy address handling.
     */
    public function createWithDerivedAddress(Merchant $merchant, string $network, callable $createInvoice): ?Invoice
    {
        return DB::transaction(function () use ($merchant, $network, $createInvoice) {
            /** @var MerchantDerivationConfig|null $config */
            $config = MerchantDerivationConfig::query()
                ->where('merchant_id', $merchant->id)
                ->where('network', $network)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$config) {
                return null;
            }

            $xpub = $config->xpub;
            $index = (int) $config->next_index;
            $address = null;

            for ($attempt = 0; $attempt < 1000; $attempt++, $index++) {
                if ($index > 2147483647) {
                    throw new RuntimeException('HD derivation index space exhausted.');
                }

                $candidate = strtolower($this->deriver->addressAt($xpub, $index));

                $existsInInvoices = Invoice::query()
                    ->where('network', $network)
                    ->whereRaw('LOWER(payment_address) = ?', [$candidate])
                    ->exists();

                $existsInRegistry = InvoiceAddressAllocation::query()
                    ->where('network', $network)
                    ->whereRaw('LOWER(address) = ?', [$candidate])
                    ->exists();

                if ($existsInInvoices || $existsInRegistry) {
                    continue;
                }

                // The unique reservation index prevents concurrent reuse.
                if (!$this->reservations->reserve($network, $candidate)) {
                    continue;
                }

                $address = $candidate;
                break;
            }

            if ($address === null) {
                throw new RuntimeException('Could not reserve an unused derived address after 1000 attempts.');
            }

            $config->next_index = $index + 1;
            $config->save();

            /** @var Invoice $invoice */
            $invoice = $createInvoice($address);

            if (!$invoice instanceof Invoice || !$invoice->exists) {
                throw new RuntimeException('Invoice callback did not return a persisted invoice.');
            }

            $this->reservations->associateInvoice($network, $address, (int) $invoice->id);

            InvoiceAddressAllocation::query()->create([
                'invoice_id' => $invoice->id,
                'merchant_id' => $merchant->id,
                'config_id' => $config->id,
                'network' => $network,
                'address' => $address,
                'derivation_index' => $index,
            ]);

            return $invoice;
        }, 5);
    }

    public function hasActiveConfig(int $merchantId, string $network): bool
    {
        return MerchantDerivationConfig::query()
            ->where('merchant_id', $merchantId)
            ->where('network', $network)
            ->where('is_active', true)
            ->exists();
    }
}
