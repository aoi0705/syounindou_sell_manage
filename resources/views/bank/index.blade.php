{{-- resources/views/bank/index.blade.php --}}
@extends('layouts.app')

@section('content')
  @php
    $tz = config('app.timezone','Asia/Tokyo');
    $thisMonth = \Carbon\Carbon::now($tz)->format('Y-m');
    $prevMonth = \Carbon\Carbon::now($tz)->subMonth()->format('Y-m');

    // BankTransferController は y/m を受ける実装なので、画面では ym を使いつつ内部で y,m に分解して送る
    $ymVal = (isset($year) && $year) ? sprintf('%04d-%02d', $year, $month ?: 1) : '';
  @endphp

  <h1 style="margin:0 0 12px;">振込記録（銀行振込の注文）</h1>

  <form method="get" class="toolbar filterbar" action="{{ route('bank.index') }}" onsubmit="return syncYmToHidden(this);">
    {{-- 検索キーワード --}}
    <div class="field" style="min-width:260px;">
      <div class="label">検索</div>
      <div class="input-icon">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="注文番号 / 購入者 / 店舗で検索">
      </div>
    </div>

    {{-- 年月（注文一覧と同じ見た目・“ボタンが上、入力が下” の並び） --}}
    <div class="field" style="min-width:220px; max-width:240px;">
      <div class="label">年月</div>

      {{-- クイックボタン（上） --}}
      <div class="actions" style="margin-bottom:6px; gap:6px;">
        <button class="btn ghost sm" type="button" onclick="setYm('{{ $thisMonth }}')">今月</button>
        <button class="btn ghost sm" type="button" onclick="setYm('{{ $prevMonth }}')">先月</button>
        <button class="btn ghost sm" type="button" onclick="setYm('')">クリア</button>
      </div>

      {{-- 入力（月ピッカー）（下） --}}
      <div class="input-icon">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <input type="month" name="ym" value="{{ $ymVal }}">
      </div>

      {{-- Controller が y,m を受けるための隠し項目（送信時に ym から埋める） --}}
      <input type="hidden" name="y" value="{{ isset($year) && $year ? $year : '' }}">
      <input type="hidden" name="m" value="{{ isset($month) && $month ? sprintf('%02d', $month) : '' }}">
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
          <th>店舗</th>
          <th>商品点数</th>
          <th>合計</th>
          <th style="width:180px;">操作</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($orders as $o)
          <tr>
            <td>
              <a class="btn ghost sm" href="{{ route('orders.show', $o) }}">{{ $o->order_no }}</a>
            </td>
            <td>{{ $o->purchased_at_text ?? optional($o->purchased_at)->format('Y-m-d H:i') }}</td>
            <td>{{ $o->buyer_name }}</td>
            <td>{{ $o->shop_name }}</td>
            <td>{{ $o->items_count }}</td>
            <td><strong>{{ number_format($o->total) }}円</strong></td>
            <td class="actions">
              <a class="btn secondary sm" href="{{ route('bank.show', $o) }}">振込記録</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" style="color:#64748b;">該当する注文がありません。</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:12px;">
    {{ $orders->links() }}
  </div>

  <script>
    function setYm(v){
      const el = document.querySelector('input[name="ym"]');
      if (el) el.value = v;
    }
    function syncYmToHidden(form){
      const ym = form.querySelector('input[name="ym"]')?.value || '';
      const y  = form.querySelector('input[name="y"]');
      const m  = form.querySelector('input[name="m"]');
      if (ym && /^\d{4}-\d{2}$/.test(ym)) {
        y.value = ym.slice(0,4);
        m.value = ym.slice(5,7);
      } else {
        y.value = '';
        m.value = '';
      }
      return true;
    }
  </script>
@endsection
