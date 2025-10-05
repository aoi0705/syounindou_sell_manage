<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransfer extends Model
{
    protected $fillable = [
        'order_id',
        'transfer_at_text',
        'transfer_at',
        'amount',
        'bank_name',
        'branch_name',
        'payer_name',
        'raw_body',
    ];

    protected $casts = [
        'transfer_at' => 'datetime',
        'amount' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
