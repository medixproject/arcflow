<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class WebhookEndpoint extends Model { protected $fillable=['merchant_id','url','secret_hash','active']; protected $casts=['active'=>'boolean']; public function merchant(): BelongsTo{return $this->belongsTo(Merchant::class);} public function deliveries(): HasMany{return $this->hasMany(WebhookDelivery::class);} }
