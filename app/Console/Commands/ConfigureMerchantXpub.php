<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\MerchantDerivationConfig;
use App\Models\InvoiceAddressAllocation;
use App\Services\XpubAddressDeriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConfigureMerchantXpub extends Command
{
    protected $signature = 'arcflow:merchant-xpub {merchant_id} {network?}';
    protected $description = 'Securely configure an Ethereum-compatible merchant extended public key';

    public function handle(XpubAddressDeriver $deriver): int
    {
        $merchant = Merchant::find($this->argument('merchant_id'));
        if (!$merchant) {
            $this->error('Merchant not found.');
            return self::FAILURE;
        }
        $network = (string) ($this->argument('network') ?: config('arc.network'));
        $path = "m/44'/60'/0'/0";
        $this->warn('Provide only the public extended key exported at parent path '.$path.'. Never provide a seed phrase or private key.');
        $xpub = $this->secret('Extended public key (input hidden):');
        if (!is_string($xpub) || trim($xpub) === '') {
            $this->error('No key supplied.');
            return self::FAILURE;
        }
        try {
            $deriver->validate(trim($xpub));
        } catch (Throwable $e) {
            $this->error('Key validation failed: '.$e->getMessage());
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($merchant, $network, $path, $xpub) {
                $config = MerchantDerivationConfig::query()->where('merchant_id', $merchant->id)
                    ->where('network', $network)->lockForUpdate()->first();
                if ($config && InvoiceAddressAllocation::where('config_id', $config->id)->exists()) {
                    throw new \RuntimeException('This configuration already has allocations. Use a reviewed key-rotation procedure; do not replace its xpub in place.');
                }
                if (!$config) {
                    $config = new MerchantDerivationConfig();
                    $config->merchant_id = $merchant->id;
                    $config->network = $network;
                }
                $config->xpub = trim($xpub);
                $config->derivation_path = $path;
                $config->next_index = 0;
                $config->is_active = true;
                $config->save();
            });
        } catch (Throwable $e) {
            $this->error('Configuration was not saved: '.$e->getMessage());
            return self::FAILURE;
        }
        $this->info('Encrypted xpub saved for merchant '.$merchant->id.' on '.$network.'. No private key or seed was stored.');
        return self::SUCCESS;
    }
}
