<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_no','shop_name',
        'purchased_at','purchased_at_text',
        'payment_method',
        'subtotal','shipping_fee','cool_fee','total','tax10','tax8',
        'raw_body',
        'ship_carrier','ship_date_request','ship_time_window',
        'buyer_name','buyer_kana','buyer_address_full','buyer_tel','buyer_mobile','buyer_email',
        'shipto_name','shipto_kana','shipto_address_full','shipto_tel',
        'mail_preference',
        // 追加
        'is_shipped','is_gift',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        // 追加
        'is_shipped' => 'boolean',
        'is_gift'    => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transfers()
    {
        return $this->hasMany(\App\Models\BankTransfer::class);
    }

    public function bankTransfers()
    {
        return $this->hasMany(\App\Models\BankTransfer::class);
    }
}
