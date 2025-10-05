@extends('layouts.app')

@section('content')
  <h1 style="margin:0 0 12px;">銀行振込の注文</h1>

  <form method="get" class="toolbar" action="{{ route('bank.index') }}">
    <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="注文番号 / 購入者 / 店舗で検索">
    <select name="y" title="年">
      <option value="">年(すべて)</option>
      @for ($yy = date('Y')+1; $yy >= date('Y')-5; $yy--)
        <option value="{{ $yy }}" {{ (isset($year) && $year == $yy) ? 'selected' : '' }}>{{ $yy }}年</option>
      @endfor
    </select>
    <select name="m" title="月">
      <option value="">月(すべて)</option>
      @for ($mm = 1; $mm <= 12; $mm++)
        <option value="{{ $mm }}" {{ (isset($month) && $month == $mm) ? 'selected' : '' }}>{{ $mm }}月</option>
      @endfor
    </select>
    <div class="spacer"></div>
    <button class="btn primary">検索</button>
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
