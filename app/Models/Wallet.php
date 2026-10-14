<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Wallet extends Model { protected $fillable=['merchant_id','network','address','label','is_active']; protected $casts=['is_active'=>'boolean']; public function merchant(): BelongsTo{return $this->belongsTo(Merchant::class);} }
