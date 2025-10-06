@extends('layouts.app')

@section('content')
  @php
    $thisMonth = \Carbon\Carbon::now(config('app.timezone','Asia/Tokyo'))->format('Y-m');
    $prevMonth = \Carbon\Carbon::now(config('app.timezone','Asia/Tokyo'))->subMonth()->format('Y-m');
  @endphp

  <h1 style="margin:0 0 12px;">注文書発行</h1>

  <form method="get" class="toolbar filterbar" action="{{ route('po.index') }}">
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
        <div class="label">年月</div>

        <div class="actions" style="gap:6px;">
          <button class="btn ghost sm" type="button" onclick="setYm('{{ $thisMonth }}')">今月</button>
          <button class="btn ghost sm" type="button" onclick="setYm('{{ $prevMonth }}')">先月</button>
          <button class="btn ghost sm" type="button" onclick="setYm('')">クリア</button>
        </div>

        <div class="input-icon" style="margin-top:6px;">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          <input type="month" name="ym" value="{{ $ym ?? '' }}">
        </div>
      </div>

    <div class="field" style="min-width:200px; max-width:220px;">
      <div class="label">並び順</div>
      <div class="input-icon">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <polyline points="3 6 7 2 11 6"></polyline>
          <polyline points="13 18 17 22 21 18"></polyline>
          <line x1="7" y1="2" x2="7" y2="22"></line>
          <line x1="17" y1="2" x2="17" y2="22"></line>
        </svg>
        <select name="sort">
          <option value="date_desc"  {{ (isset($sort)&&$sort==='date_desc')?'selected':'' }}>新しい順</option>
          <option value="date_asc"   {{ (isset($sort)&&$sort==='date_asc')?'selected':'' }}>古い順</option>
          <option value="total_desc" {{ (isset($sort)&&$sort==='total_desc')?'selected':'' }}>合計が大きい順</option>
        </select>
      </div>
    </div>

    <div class="spacer"></div>
    <button class="btn primary">絞り込む</button>
    <a class="btn ghost" href="{{ route('po.index') }}">リセット</a>
  </form>

  <form method="post" action="{{ route('po.generate') }}" onsubmit="return confirmBeforeSubmit();">
    @csrf

    <div class="row">
      <div style="flex: 2 1 520px; min-width: 420px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin: 8px 0;">
          <strong>注文一覧（このページでチェックした注文が対象）</strong>
          <div class="actions">
            <button class="btn ghost sm" type="button" onclick="toggleAll(true)">全選択</button>
            <button class="btn ghost sm" type="button" onclick="toggleAll(false)">全解除</button>
          </div>
        </div>

        <div style="overflow:auto; border:1px solid #ececec; border-radius:10px;">
          <table>
            <thead>
              <tr>
                <th style="width:40px;">選択</th>
                <th>注文番号</th>
                <th>購入日</th>
                <th>購入者</th>
                <th>合計</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($orders as $o)
                <tr>
                  <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}"></td>
                  <td>{{ $o->order_no }}</td>
                  <td>{{ $o->purchased_at_text ?? optional($o->purchased_at)->format('Y-m-d') }}</td>
                  <td>{{ $o->buyer_name }}</td>
                  <td><strong>{{ number_format($o->total) }}円</strong></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div style="margin-top:8px;">
          {{ $orders->links() }}
        </div>
      </div>

      <div style="flex: 1 1 320px; min-width: 320px;">
        <div class="actions" style="margin-top:10px;">
          <button class="btn primary">注文書を発行</button>
        </div>
      </div>
    </div>
  </form>


  <script>
    function setYm(v){ const el = document.querySelector('input[name="ym"]'); if (el) el.value = v; }
    function toggleAll(on){
      document.querySelectorAll('input[type="checkbox"][name="order_ids[]"]').forEach(cb => cb.checked = !!on);
    }
    function confirmBeforeSubmit(){
      const any = Array.from(document.querySelectorAll('input[name="order_ids[]"]')).some(cb => cb.checked);
      if(!any){ alert('注文が選択されていません。'); return false; }
      return true;
    }
  </script>
@endsection
