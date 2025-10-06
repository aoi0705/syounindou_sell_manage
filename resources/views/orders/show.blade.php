@extends('layouts.app')

@section('content')
@php
  /** @var \App\Models\Order $order */
  use App\Models\OrderItem as OI;

  // バッジの色
  function statusBadge($st) {
    return match($st) {
      OI::STATUS_PENDING       => ['bg'=>'#fee2e2','fg'=>'#991b1b','label'=>'未対応'],
      OI::STATUS_LABEL_ISSUED  => ['bg'=>'#e0e7ff','fg'=>'#3730a3','label'=>'送り状発行済み'],
      OI::STATUS_SHIPPED       => ['bg'=>'#dcfce7','fg'=>'#166534','label'=>'発送済み'],
      default => ['bg'=>'#e5e7eb','fg'=>'#334155','label'=>'-'],
    };
  }
@endphp

  <div class="row" style="justify-content:space-between; align-items:flex-start;">
    <h1 style="margin:0 0 12px;">注文 #{{ $order->order_no }}</h1>
    <div class="btn-group">
      <a class="btn ghost" href="{{ route('orders.index') }}">一覧に戻る</a>
      <form method="post" action="{{ route('orders.destroy', $order) }}" class="inline" onsubmit="return confirm('この注文を削除すると明細も削除されます。よろしいですか？');">
        @method('delete')
        @csrf
        <button class="btn danger">注文を削除</button>
      </form>
    </div>
  </div>

  <form method="post" action="{{ route('orders.update', $order) }}">
    @method('patch')
    @csrf

    <div class="row" style="gap:24px;">
      <div style="flex:1; min-width:320px;">
        <h3>注文情報（編集可）</h3>
        <table>
          <tr><th class="w-min">店舗</th><td>{{ $order->shop_name }}</td></tr>
          <tr><th>購入日(原文)</th><td>{{ $order->purchased_at_text }}</td></tr>
          <tr><th>購入日時</th><td>{{ optional($order->purchased_at)->format('Y-m-d H:i:s') }}</td></tr>
          <tr><th>支払方法</th><td><input type="text" name="payment_method" value="{{ old('payment_method', $order->payment_method) }}"></td></tr>
          <tr><th>メール受信</th><td><input type="text" name="mail_preference" value="{{ old('mail_preference', $order->mail_preference) }}"></td></tr>
          <tr><th>配送便</th><td><input type="text" name="ship_carrier" value="{{ old('ship_carrier', $order->ship_carrier) }}"></td></tr>
          <tr><th>配送希望日</th><td><input type="text" name="ship_date_request" value="{{ old('ship_date_request', $order->ship_date_request) }}"></td></tr>
          <tr><th>配送希望時間帯</th><td><input type="text" name="ship_time_window" value="{{ old('ship_time_window', $order->ship_time_window) }}"></td></tr>

          {{-- 発送済フラグ（注文全体） --}}
          <tr>
            <th>発送済</th>
            <td>
              <input type="hidden" name="is_shipped" value="0">
              <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_shipped" value="1" {{ $order->is_shipped ? 'checked' : '' }}>
                <span>発送済みにする（注文全体）</span>
              </label>
            </td>
          </tr>

          {{-- 贈答品フラグ --}}
          <tr>
            <th>贈答品</th>
            <td>
              <input type="hidden" name="is_gift" value="0">
              <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_gift" value="1" {{ $order->is_gift ? 'checked' : '' }}>
                <span>贈答扱い</span>
              </label>
            </td>
          </tr>
        </table>

        <h3>金額</h3>
        <table>
          <tr><th>小計</th><td>{{ number_format($order->subtotal) }}円</td></tr>
          <tr><th>送料</th><td>{{ number_format($order->shipping_fee) }}円</td></tr>
          <tr><th>クール料金</th><td>{{ number_format($order->cool_fee) }}円</td></tr>
          <tr><th>合計</th><td><strong>{{ number_format($order->total) }}円</strong></td></tr>
          <tr><th>税(10%)</th><td>{{ number_format($order->tax10) }}円</td></tr>
          <tr><th>税(8%)</th><td>{{ number_format($order->tax8) }}円</td></tr>
        </table>
      </div>

      <div style="flex:1; min-width:320px;">
        <h3>購入者（編集可）</h3>
        <table>
          <tr><th class="w-min">氏名</th><td><input type="text" name="buyer_name" value="{{ old('buyer_name', $order->buyer_name) }}"></td></tr>
          <tr><th>カナ</th><td><input type="text" name="buyer_kana" value="{{ old('buyer_kana', $order->buyer_kana) }}"></td></tr>
          <tr><th>住所</th><td><textarea name="buyer_address_full" style="min-height:80px;">{{ old('buyer_address_full', $order->buyer_address_full) }}</textarea></td></tr>
          <tr><th>TEL</th><td><input type="text" name="buyer_tel" value="{{ old('buyer_tel', $order->buyer_tel) }}"></td></tr>
          <tr><th>携帯</th><td><input type="text" name="buyer_mobile" value="{{ old('buyer_mobile', $order->buyer_mobile) }}"></td></tr>
          <tr><th>Email</th><td><input type="text" name="buyer_email" value="{{ old('buyer_email', $order->buyer_email) }}"></td></tr>
        </table>

        <h3>お届け先（編集可）</h3>
        <table>
          <tr><th class="w-min">氏名</th><td><input type="text" name="shipto_name" value="{{ old('shipto_name', $order->shipto_name) }}"></td></tr>
          <tr><th>カナ</th><td><input type="text" name="shipto_kana" value="{{ old('shipto_kana', $order->shipto_kana) }}"></td></tr>
          <tr><th>住所</th><td><textarea name="shipto_address_full" style="min-height:80px;">{{ old('shipto_address_full', $order->shipto_address_full) }}</textarea></td></tr>
          <tr><th>TEL</th><td><input type="text" name="shipto_tel" value="{{ old('shipto_tel', $order->shipto_tel) }}"></td></tr>
        </table>
      </div>
    </div>

    <div class="btn-group" style="margin-top:12px;">
      <button class="btn">注文情報を保存</button>
      <a class="btn ghost" href="{{ route('orders.show', $order) }}">リセット</a>
    </div>
  </form>

  {{-- ========================= 注文明細 ========================= --}}
  <h3 style="margin-top:24px;">注文明細（行ごとに編集/削除）</h3>
  <div style="overflow:auto;">
    <table id="tracking">
      <thead>
        <tr>
          <th>商品番号</th>
          <th>商品名</th>
          <th>単価</th>
          <th>数量</th>
          <th>小計</th>
          <th>ステータス</th>
          <th>追跡番号</th>
          <th style="width:260px;">操作</th>
        </tr>
      </thead>
      <tbody>
      @foreach ($order->items as $it)
        @php $sb = statusBadge($it->status); @endphp
        <tr>
          {{-- 明細編集フォーム（SKU/名称/単価/数量） --}}
          <form method="post" action="{{ route('orders.items.update', [$order, $it]) }}">
            @method('patch')
            @csrf
            <td><input class="w-min" type="text" name="sku" value="{{ $it->sku }}"></td>
            <td><input type="text" name="name" value="{{ $it->name }}" required></td>
            <td><input class="num" type="number" name="unit_price" value="{{ $it->unit_price }}" min="0" required oninput="recalcRow(this)"></td>
            <td><input class="num" type="number" name="quantity" value="{{ $it->quantity }}" min="0" required oninput="recalcRow(this)"></td>
            <td><input class="num" type="number" name="line_total_view" value="{{ $it->unit_price * $it->quantity }}" readonly></td>

            {{-- ステータス表示（バッジ） --}}
            <td>
              <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;
                           background:{{ $sb['bg'] }}; color:{{ $sb['fg'] }};">
                {{ $sb['label'] }}
              </span>
              @if ($it->status === OI::STATUS_SHIPPED && $it->shipped_at)
                <div style="font-size:12px;color:#64748b;">{{ $it->shipped_at->format('Y-m-d H:i') }}</div>
              @elseif ($it->status === OI::STATUS_LABEL_ISSUED && $it->label_issued_at)
                <div style="font-size:12px;color:#64748b;">{{ $it->label_issued_at->format('Y-m-d H:i') }}</div>
              @endif
            </td>

            {{-- 追跡番号（状態に応じてUI切替） --}}
            <td>
              @if ($it->status === OI::STATUS_LABEL_ISSUED)
                {{-- 送り状発行済み → 追跡番号を登録可能（登録すると発送済みへ遷移） --}}
                <form method="post" action="{{ route('order-items.tracking.update', $it) }}" style="display:flex; gap:6px;">
                  @csrf
                  @method('PATCH')
                  <input type="text" name="tracking_no" value="{{ old('tracking_no') }}" placeholder="追跡番号" style="width:160px;">
                  <button class="btn sm">登録</button>
                </form>
              @elseif ($it->status === OI::STATUS_SHIPPED)
                {{-- 発送済み → 追跡番号を表示 --}}
                @if ($it->tracking_no)
                  <div style="font-size:13px;">{{ $it->tracking_no }}</div>
                @else
                  <div style="color:#64748b;">（未登録）</div>
                @endif
              @else
                {{-- 未対応 --}}
                <div style="color:#64748b;">—</div>
              @endif
            </td>

            <td class="actions" style="display:flex; gap:6px; align-items:center;">
              <button class="btn secondary sm">保存</button>
          </form>
              {{-- 削除フォームは行内の別フォーム --}}
              <form method="post" action="{{ route('orders.items.destroy', [$order, $it]) }}" class="inline" onsubmit="return confirm('この明細を削除します。よろしいですか？');" style="margin:0;">
                @method('delete')
                @csrf
                <button class="btn danger sm">削除</button>
              </form>
            </td>
        </tr>
      @endforeach
      </tbody>
    </table>

    <h2 style="margin-top:16px;">備考</h2>
    @if(!empty($order->note))
      <div style="white-space:pre-wrap; padding:8px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa;">
        {{ $order->note }}
      </div>
    @else
      <p style="color:#64748b;">（備考は入力されていません）</p>
    @endif
  </div>

  <details style="margin-top:16px;">
    <summary>元メール本文（デバッグ）</summary>
    <pre style="white-space: pre-wrap;">{{ $order->raw_body }}</pre>
  </details>

  <script>
    function recalcRow(el){
      const tr   = el.closest('tr');
      const up   = tr.querySelector('input[name="unit_price"]');
      const qty  = tr.querySelector('input[name="quantity"]');
      const out  = tr.querySelector('input[name="line_total_view"]');
      if (!up || !qty || !out) return;
      const v = (parseInt(up.value||'0')||0) * (parseInt(qty.value||'0')||0);
      out.value = v;
    }
  </script>
@endsection
