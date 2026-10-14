<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id', 'network', 'token_contract', 'from_address', 'to_address',
        'amount', 'currency', 'transaction_hash', 'log_index', 'block_number', 'confirmations',
        'status', 'detected_at', 'confirmed_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'block_number' => 'integer',
        'log_index' => 'integer',
        'confirmations' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
