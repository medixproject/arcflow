<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAddressAllocation extends Model
{
    protected $fillable = ['invoice_id', 'merchant_id', 'config_id', 'network', 'address', 'derivation_index'];

    protected $casts = ['invoice_id' => 'integer', 'merchant_id' => 'integer', 'config_id' => 'integer', 'derivation_index' => 'integer'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(MerchantDerivationConfig::class, 'config_id');
    }
}
