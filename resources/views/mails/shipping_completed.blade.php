@php
  /** @var \App\Models\Order $order */
  /** @var \App\Models\OrderItem[] $items */
@endphp

@component('mail::message')
# 発送完了のお知らせ

{{ $order->buyer_name ?? 'お客様' }} 様

ご注文 **{{ $order->order_no }}** の商品を発送いたしました。

## お届け先
- 氏名：{{ $order->shipto_name ?? $order->buyer_name }}
- 住所：{{ $order->shipto_address_full }}
- 電話：{{ $order->shipto_tel }}

## 配送情報
- 配送業者：{{ $order->ship_carrier ?? '—' }}
- 希望日：{{ $order->ship_date_request ?? '—' }}
- 時間帯：{{ $order->ship_time_window ?? '—' }}

## 発送商品
@component('mail::table')
| SKU | 商品名 | 数量 | 追跡番号 |
|:---:|:--|:--:|:--|
@foreach ($items as $it)
| {{ $it->sku ?? '-' }} | {{ $it->name }} | {{ $it->quantity }} | {{ $it->tracking_no ?? '-' }} |
@endforeach
@endcomponent

お荷物の到着まで今しばらくお待ちください。
{{ config('app.name') }}
@endcomponent
