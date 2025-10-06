@extends('layouts.app')

@section('content')
  @php
    $thisMonth = \Carbon\Carbon::now(config('app.timezone','Asia/Tokyo'))->format('Y-m');
    $prevMonth = \Carbon\Carbon::now(config('app.timezone','Asia/Tokyo'))->subMonth()->format('Y-m');

    // クエリ組み立て用（現在の検索条件を維持したまま item_status を上書き）
    $makeUrl = function(array $overrides = []) {
        $params = array_merge(request()->except('page'), $overrides);
        // null/空文字は除外してスッキリ
        $params = array_filter($params, fn($v) => !is_null($v) && $v !== '');
        return route('orders.index', $params);
    };
    $ist = request('item_status'); // pending / label_issued / shipped / null
  @endphp

  <h1 style="margin:0 0 12px;">注文一覧</h1>

  <form method="get" class="toolbar filterbar" action="{{ route('orders.index') }}">
    {{-- キーワード --}}
    <div class="field" style="min-width:260px;">
      <div class="label">検索</div>
      <div class="input-icon">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="注文番号 / 購入者 / 店舗で検索">
      </div>
    </div>

    {{-- 年月（月ピッカー） --}}
    <div class="field" style="min-width:220px; max-width:240px;">
      <div class="label">年月</div>
      <div class="input-icon">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <input type="month" name="ym" value="{{ $ym ?? '' }}">
      </div>
      <div class="actions" style="margin-top:6px; gap:6px;">
        <button class="btn ghost sm" type="button" onclick="setYm('{{ $thisMonth }}')">今月</button>
        <button class="btn ghost sm" type="button" onclick="setYm('{{ $prevMonth }}')">先月</button>
        <button class="btn ghost sm" type="button" onclick="setYm('')">クリア</button>
      </div>
    </div>

    {{-- 並び替え --}}
    <div class="field" style="min-width:200px; max-width:220px;">
      <div class="label">並び順</div>
      <div class="input-icon">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <polyline points="3 6 7 2 11 6"></polyline>
          <polyline points="13 18 17 22 21 18"></polyline>
          <line x1="7" y1="2" x2="7" y2="22"></line>
          <line x1="17" y1="2" x2="17" y2="22"></line>
        </svg>
        <select name="sort" title="並び替え">
          <option value="date_desc"  {{ (isset($sort)&&$sort==='date_desc')?'selected':'' }}>新しい順</option>
          <option value="date_asc"   {{ (isset($sort)&&$sort==='date_asc')?'selected':'' }}>古い順</option>
          <option value="total_desc" {{ (isset($sort)&&$sort==='total_desc')?'selected':'' }}>合計が大きい順</option>
        </select>
      </div>
    </div>

    <div class="spacer"></div>
    <button class="btn primary">検索</button>
    <a class="btn ghost" href="{{ route('orders.index') }}">リセット</a>
  </form>

  <div class="toolbar" style="margin-top:10px; display:flex; gap:6px; flex-wrap:wrap;">
    @php
      $makeUrl = function($key) {
        return route('orders.index', array_merge(request()->except('page'), ['status' => $key]));
      };
      $is = fn($key) => (request('status') === $key);
    @endphp

    <a href="{{ $makeUrl(null) }}" class="btn {{ request('status') ? 'ghost' : 'primary' }}">すべて</a>

    {{-- 既存３種 --}}
    <a href="{{ $makeUrl('pending') }}" class="btn {{ $is('pending') ? 'primary' : 'ghost' }}">未対応</a>
    <a href="{{ $makeUrl('labeled') }}" class="btn {{ $is('labeled') ? 'primary' : 'ghost' }}">送り状発行済み</a>
    <a href="{{ $makeUrl('shipped') }}" class="btn {{ $is('shipped') ? 'primary' : 'ghost' }}">発送済み</a>

    {{-- ★ 追加２種（右側に追加） --}}
    <a href="{{ $makeUrl('partial_labeled') }}" class="btn {{ $is('partial_labeled') ? 'primary' : 'ghost' }}">一部送り状発行済み</a>
    <a href="{{ $makeUrl('partial_shipped') }}" class="btn {{ $is('partial_shipped') ? 'primary' : 'ghost' }}">一部発送済み</a>
  </div>

  <div style="overflow:auto; margin-top:12px;">
    <table>
      <thead>
        <tr>
          <th>注文番号</th>
          <th>購入日</th>
          <th>購入者</th>
          <th>支払方法</th>
          <th>商品点数</th>
          <th>合計</th>
          <th>発送</th>
          <th>贈答</th>
          <th style="width:260px;">操作</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($orders as $o)
          <tr>
            <td><a class="btn ghost sm" href="{{ route('orders.show', $o) }}">{{ $o->order_no }}</a></td>
            <td>{{ $o->purchased_at_text ?? optional($o->purchased_at)->format('Y-m-d H:i') }}</td>
            <td>{{ $o->buyer_name }}</td>
            <td>{{ $o->payment_method }}</td>
            <td>{{ $o->items_count }}</td>
            <td><strong>{{ number_format($o->total) }}円</strong></td>
            <td>@if($o->is_shipped)<span class="pill">発送済</span>@endif</td>
            <td>@if($o->is_gift)<span class="pill">🎁 贈答</span>@endif</td>
            <td class="actions" style="display:flex; gap:6px; flex-wrap:wrap;">
              <a class="btn secondary sm" href="{{ route('orders.show', $o) }}">詳細/編集</a>
              <a class="btn sm" href="{{ route('orders.show', $o) }}#tracking">追跡番号入力</a>
              <form method="post" action="{{ route('orders.destroy', $o) }}" class="inline" onsubmit="return confirm('この注文を削除すると明細も削除されます。よろしいですか？');">
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

  <div style="margin-top:12px;">
    {{ $orders->links() }}
  </div>

  <script>
    function setYm(v){
      const el = document.querySelector('input[name="ym"]');
      if (el) { el.value = v; }
    }
  </script>
@endsection
