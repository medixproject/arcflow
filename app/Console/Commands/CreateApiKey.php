<?php
namespace App\Console\Commands;
use App\Models\ApiKey;
use App\Models\Merchant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
class CreateApiKey extends Command { protected $signature='arcflow:api-key {merchant_id} {name=default}'; protected $description='Create a one-time-display ArcFlow API key.'; public function handle(): int {$merchant=Merchant::find($this->argument('merchant_id'));if(!$merchant){$this->error('Merchant not found');return self::FAILURE;}$raw='af_live_'.Str::random(48);ApiKey::create(['merchant_id'=>$merchant->id,'name'=>$this->argument('name'),'key_prefix'=>substr($raw,0,16),'key_hash'=>hash('sha256',$raw)]);$this->newLine();$this->warn('Store this key now. It will not be shown again.');$this->line($raw);return self::SUCCESS;} }
