<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
