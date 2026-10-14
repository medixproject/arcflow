<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
class Invoice extends Model { protected $fillable=['merchant_id','external_id','invoice_number','public_id','amount','currency','network','payment_address','description','status','expires_at','paid_at']; protected $casts=['expires_at'=>'datetime','paid_at'=>'datetime']; public function getRouteKeyName(): string{return 'public_id';} protected static function booted(){static::creating(function($m){$m->public_id ??= (string) Str::ulid(); $m->invoice_number ??= 'INV-'.strtoupper(Str::random(10));});} public function merchant(): BelongsTo{return $this->belongsTo(Merchant::class);} public function payments(): HasMany{return $this->hasMany(Payment::class);} }
