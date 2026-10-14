<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class User extends Authenticatable implements MustVerifyEmail { use Notifiable, HasFactory; public function merchants(): HasMany{return $this->hasMany(Merchant::class);} protected $fillable=['name','email','password']; protected $hidden=['password','remember_token']; protected function casts(): array{return ['email_verified_at'=>'datetime','password'=>'hashed'];} }
