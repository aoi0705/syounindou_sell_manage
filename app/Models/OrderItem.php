<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public const STATUS_PENDING     = 0;
    public const STATUS_LABEL_ISSUED= 1;
    public const STATUS_SHIPPED     = 2;

    protected $casts = [
        'status'         => 'int',
        'label_issued_at'=> 'datetime',
        'shipped_at'     => 'datetime',
    ];

    // （使っていれば）$fillable に追加
    protected $fillable = [
        'order_id','sku','name','unit_price','quantity','line_total','reduced_tax',
        'status','tracking_no','label_issued_at','shipped_at',
    ];

    public function scopePending($q)      { return $q->where('status', self::STATUS_PENDING); }
    public function scopeLabelIssued($q)  { return $q->where('status', self::STATUS_LABEL_ISSUED); }
    public function scopeShipped($q)      { return $q->where('status', self::STATUS_SHIPPED); }

    // 表示用ラベル（お好みで）
    public function getStatusLabelAttribute(): string
    {
        return [
            self::STATUS_PENDING      => '未対応',
            self::STATUS_LABEL_ISSUED => '送り状発行済み',
            self::STATUS_SHIPPED      => '発送済み',
        ][$this->status] ?? '-';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
