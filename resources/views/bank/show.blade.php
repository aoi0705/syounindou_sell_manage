@extends('layouts.app')

@section('content')
  <div class="row" style="justify-content:space-between; align-items:flex-start;">
    <h1 style="margin:0 0 12px;">振込記録：注文 #{{ $order->order_no }}</h1>
    <div class="btn-group">
      <a class="btn ghost" href="{{ route('bank.index') }}">一覧に戻る</a>
      <a class="btn ghost" href="{{ route('orders.show', $order) }}">注文詳細</a>
    </div>
  </div>

  <div class="row" style="gap:24px;">
    <div style="flex:1; min-width:320px;">
      <h3>注文概要</h3>
      <table>
        <tr><th>購入日</th><td>{{ $order->purchased_at_text ?? optional($order->purchased_at)->format('Y-m-d H:i') }}</td></tr>
        <tr><th>購入者</th><td>{{ $order->buyer_name }}</td></tr>
        <tr><th>合計</th><td><strong>{{ number_format($order->total) }}円</strong></td></tr>
        <tr><th>支払方法</th><td>{{ $order->payment_method }}</td></tr>
        <tr><th>商品点数</th><td>{{ $order->items->count() }}</td></tr>
      </table>
    </div>

    <div style="flex:1; min-width:360px;">
      <h3>振込記録の取り込み</h3>
      <form method="post" action="{{ route('bank.store', $order) }}">
        @csrf
        <label class="label">通帳明細や通知メールの本文を貼り付け</label>
        <textarea name="raw_body" style="min-height:220px; font: 14px/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;"
                  placeholder="例）取引日時／金額／銀行・支店／名義が含まれる文面を貼付け">{{ old('raw_body') }}</textarea>
        <div class="actions" style="margin-top:8px;">
          <button class="btn primary">取り込む</button>
        </div>
      </form>
    </div>
  </div>

  <h3 style="margin-top:20px;">振込記録一覧</h3>
  <div style="overflow:auto;">
    <table>
      <thead>
        <tr>
          <th>日時（原文）</th>
          <th>日時</th>
          <th>金額</th>
          <th>銀行</th>
          <th>支店</th>
          <th>名義</th>
          <th style="width:140px;">操作</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($transfers as $t)
          <tr>
            <td>{{ $t->transfer_at_text }}</td>
            <td>{{ optional($t->transfer_at)->format('Y-m-d H:i') }}</td>
            <td><strong>{{ number_format($t->amount) }}円</strong></td>
            <td>{{ $t->bank_name }}</td>
            <td>{{ $t->branch_name }}</td>
            <td>{{ $t->payer_name }}</td>
            <td class="actions">
              <form method="post" action="{{ route('bank.destroy', [$order, $t]) }}" class="inline" onsubmit="return confirm('削除してよろしいですか？');">
                @method('delete')
                @csrf
                <button class="btn danger sm">削除</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" style="color:#64748b;">振込記録はまだありません。</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
