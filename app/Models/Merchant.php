<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Merchant extends Model { protected $fillable=['user_id','name','slug','status','default_network']; public function user(): BelongsTo{return $this->belongsTo(User::class);} public function invoices(): HasMany{return $this->hasMany(Invoice::class);} public function wallets(): HasMany{return $this->hasMany(Wallet::class);} public function webhookEndpoints(): HasMany{return $this->hasMany(WebhookEndpoint::class);} }
