<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ApiKey extends Model { protected $fillable=['merchant_id','name','key_prefix','key_hash','last_used_at','revoked_at']; protected $casts=['last_used_at'=>'datetime','revoked_at'=>'datetime']; public function merchant(): BelongsTo{return $this->belongsTo(Merchant::class);} }
