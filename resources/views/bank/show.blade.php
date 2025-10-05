@extends('layouts.app')

@section('content')
  <div class="row" style="justify-content:space-between;">
    <h1 style="margin:0 0 12px;">振込記録：注文 #{{ $order->order_no }}</h1>
    <div class="actions">
      <a class="btn ghost" href="{{ route('bank.index') }}">銀行振込一覧へ</a>
      <a class="btn ghost" href="{{ route('orders.show', $order) }}">注文詳細へ</a>
    </div>
  </div>

  <div class="row">
    <div style="flex:1; min-width:320px;">
      <h3>注文情報</h3>
      <table>
        <tr><th class="w-min">購入者</th><td>{{ $order->buyer_name }}</td></tr>
        <tr><th>購入日</th><td>{{ $order->purchased_at_text ?? optional($order->purchased_at)->format('Y-m-d') }}</td></tr>
        <tr><th>合計</th><td><strong>{{ number_format($order->total) }}円</strong></td></tr>
        <tr><th>支払</th><td>{{ $order->payment_method }}</td></tr>
      </table>
    </div>

    <div style="flex:1; min-width:320px;">
      <h3>振込取り込み</h3>
      <form method="post" action="{{ route('bank.store', $order) }}">
        @csrf
        <label class="help">例文のようにコピペしてください（6行）</label>
        <textarea name="raw_body" style="min-height:140px;" placeholder="例：
001	2024年04月03日
（2024年04月03日）		132,240
振込入金
ﾐﾂﾋﾞｼUFJ
ﾋｶﾞｼ
ｶ)ｼﾏｼﾖｳ"></textarea>
        <div class="actions" style="margin-top:10px;">
          <button class="btn primary">登録</button>
        </div>
      </form>
    </div>
  </div>

  <h3 style="margin-top:18px;">振込記録</h3>
  <div style="overflow:auto;">
    <table>
      <thead>
        <tr>
          <th>振込日(原文)</th>
          <th>振込日時</th>
          <th>金額</th>
          <th>銀行</th>
          <th>支店</th>
          <th>振込元</th>
          <th style="width:120px;">操作</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($transfers as $t)
          <tr>
            <td>{{ $t->transfer_at_text }}</td>
            <td>{{ optional($t->transfer_at)->format('Y-m-d H:i:s') }}</td>
            <td><strong>{{ number_format($t->amount) }}円</strong></td>
            <td>{{ $t->bank_name }}</td>
            <td>{{ $t->branch_name }}</td>
            <td>{{ $t->payer_name }}</td>
            <td class="actions">
              <form method="post" action="{{ route('bank.destroy', [$order, $t]) }}" onsubmit="return confirm('この振込記録を削除します。よろしいですか？');">
                @method('delete')
                @csrf
                <button class="btn danger sm">削除</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection
