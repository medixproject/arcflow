<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WebhookDelivery extends Model { protected $fillable=['webhook_endpoint_id','event_type','payload','signature','status_code','attempts','last_attempt_at','delivered_at']; protected $casts=['payload'=>'array','last_attempt_at'=>'datetime','delivered_at'=>'datetime']; public function endpoint(): BelongsTo{return $this->belongsTo(WebhookEndpoint::class,'webhook_endpoint_id');} }
