<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class MerchantDerivationConfig extends Model
{
    protected $fillable = ['merchant_id', 'network', 'encrypted_xpub', 'derivation_path', 'next_index', 'is_active'];

    protected $casts = ['next_index' => 'integer', 'is_active' => 'boolean'];

    protected $hidden = ['encrypted_xpub'];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function setXpubAttribute(string $value): void
    {
        $this->attributes['encrypted_xpub'] = Crypt::encryptString(trim($value));
    }

    public function getXpubAttribute(): string
    {
        return Crypt::decryptString($this->attributes['encrypted_xpub']);
    }
}
