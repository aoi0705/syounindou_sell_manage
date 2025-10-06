@extends('layouts.app')

@section('content')
  <h1 style="margin:0 0 12px;">銀行振込の注文一覧</h1>

  <form method="get" class="toolbar filterbar" action="{{ route('bank.index') }}">
    <div class="field" style="min-width:260px;">
      <div class="label">検索</div>
      <div class="input-icon">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="注文番号 / 購入者 / 店舗で検索">
      </div>
    </div>

    <div class="field" style="min-width:220px; max-width:240px;">
      <div class="label">年・月</div>
      <div class="row" style="gap:8px;">
        <input type="number" name="y" value="{{ $year }}" placeholder="YYYY" min="2000" max="2100" style="width:120px;">
        <input type="number" name="m" value="{{ $month }}" placeholder="MM" min="1" max="12" style="width:90px;">
      </div>
    </div>

    <div class="spacer"></div>
    <button class="btn primary">絞り込む</button>
    <a class="btn ghost" href="{{ route('bank.index') }}">リセット</a>
  </form>

  <div style="overflow:auto; margin-top:12px;">
    <table>
      <thead>
        <tr>
          <th>注文番号</th>
          <th>購入日</th>
          <th>購入者</th>
          <th>合計</th>
          <th>支払方法</th>
          <th style="width:160px;">操作</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($orders as $o)
          <tr>
            <td>{{ $o->order_no }}</td>
            <td>{{ $o->purchased_at_text ?? optional($o->purchased_at)->format('Y-m-d') }}</td>
            <td>{{ $o->buyer_name }}</td>
            <td><strong>{{ number_format($o->total) }}円</strong></td>
            <td>{{ $o->payment_method }}</td>
            <td class="actions">
              <a class="btn secondary sm" href="{{ route('bank.show', $o) }}">振込記録</a>
              <a class="btn ghost sm" href="{{ route('orders.show', $o) }}">注文詳細</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div style="margin-top:12px;">
    {{ $orders->links() }}
  </div>
@endsection
