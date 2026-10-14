<?php
namespace Database\Seeders;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder { public function run(): void { $user=User::firstOrCreate(['email'=>'demo@arcflow.local'],['name'=>'ArcFlow Demo','password'=>Hash::make('change-me')]);$merchant=Merchant::firstOrCreate(['slug'=>'demo'],['user_id'=>$user->id,'name'=>'ArcFlow Demo Merchant','status'=>'active','default_network'=>config('arc.network')]); if(env('ARC_PAYMENT_WALLET_ADDRESS')) Wallet::firstOrCreate(['merchant_id'=>$merchant->id,'network'=>config('arc.network'),'address'=>strtolower(env('ARC_PAYMENT_WALLET_ADDRESS'))],['label'=>'Default payment wallet','is_active'=>true]); } }
