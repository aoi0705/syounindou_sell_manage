@extends('layouts.app')

@section('content')
  <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
    <h1 style="margin:0;">収支（月別ダッシュボード）</h1>
    <a class="btn ghost sm" href="{{ route('orders.index') }}">注文一覧へ</a>
  </div>

  <form method="get" class="toolbar" action="{{ route('income.index') }}" style="gap:10px;">
    <label>基準</label>
    <select name="basis">
      <option value="order_date" {{ $basis==='order_date'?'selected':'' }}>注文日ベース</option>
      <option value="labeled"    {{ $basis==='labeled'?'selected':'' }}>送り状発行済みベース</option>
      <option value="shipped"    {{ $basis==='shipped'?'selected':'' }}>発送済みベース</option>
    </select>
    <label>条件</label>
    <select name="mode">
      <option value="any" {{ $mode==='any'?'selected':'' }}>一部でも満たせば計上</option>
      <option value="all" {{ $mode==='all'?'selected':'' }}>全明細が満たす場合のみ</option>
    </select>
    <div class="spacer"></div>
    <button class="btn">反映</button>
    <a class="btn secondary sm" href="{{ route('income.export.all') }}">
        収支 Excel 出力（全期間）
      </a>
  </form>

  {{-- 月カード（直近Nヶ月） --}}
  <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:12px; margin-top:12px;">
    @foreach ($summary as $m)
      <a href="{{ route('income.show', ['ym' => $m['ym'], 'basis' => $basis, 'mode' => $mode]) }}"
         style="display:block; padding:12px; border:1px solid #e5e7eb; border-radius:12px; text-decoration:none; color:inherit; background:#fff;">
        <div style="font-size:12px; color:#64748b;">{{ $m['ym'] }}</div>
        <div style="font-size:20px; font-weight:700; margin-top:4px;">¥{{ number_format($m['total_sum']) }}</div>
        <div style="font-size:12px; color:#64748b; margin-top:2px;">注文数: {{ $m['orders'] }}</div>

        {{-- 支払方法Top1だけ表示（省スペース） --}}
        @if (!empty($m['by_method'][0]))
          <div style="font-size:12px; color:#64748b; margin-top:6px;">
            {{ $m['by_method'][0]['method'] }}: ¥{{ number_format($m['by_method'][0]['total']) }}（{{ $m['by_method'][0]['count'] }}件）
          </div>
        @endif
      </a>
    @endforeach
  </div>

  <h1 style="margin:18px 0 8px;">売上ランキング</h1>

  <form method="get" action="{{ route('income.index') }}" class="toolbar" style="gap:10px; flex-wrap:wrap;">
    {{-- 基準/モード/対象月数など既存のクエリも維持したい場合は hidden で持ち回り --}}
    <input type="hidden" name="basis"  value="{{ $basis }}">
    <input type="hidden" name="mode"   value="{{ $mode }}">
    <input type="hidden" name="months" value="{{ request('months', 12) }}">

    <div class="field">
      <div class="label">開始年月</div>
      <input type="month" name="rank_start" value="{{ $rankStartYm }}" required>
    </div>
    <div class="field">
      <div class="label">終了年月</div>
      <input type="month" name="rank_end" value="{{ $rankEndYm }}" required>
    </div>
    <div class="field">
      <div class="label">件数</div>
      <input type="number" name="rank_limit" value="{{ $rankLimit }}" min="1" max="500" style="width:120px;">
    </div>
    <div class="spacer"></div>
    <button class="btn">この期間で集計</button>
  </form>

  <div style="overflow:auto; margin-top:12px;">
    <table>
      <thead>
        <tr>
          <th style="width:70px;">順位</th>
          <th>SKU</th>
          <th>商品名</th>
          <th style="text-align:right;">数量</th>
          <th style="text-align:right;">売上合計</th>
          <th style="text-align:right;">注文数</th>
          <th style="text-align:right;">明細数</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($ranking as $i => $r)
          <tr>
            <td>{{ $i+1 }}</td>
            <td><span class="pill">{{ $r['sku'] }}</span></td>
            <td>{{ $r['name'] }}</td>
            <td style="text-align:right;">{{ number_format($r['qty']) }}</td>
            <td style="text-align:right;"><strong>¥{{ number_format($r['total']) }}</strong></td>
            <td style="text-align:right;">{{ number_format($r['orders']) }}</td>
            <td style="text-align:right;">{{ number_format($r['rows']) }}</td>
          </tr>
        @empty
          <tr><td colspan="7" style="color:#64748b;">該当期間のデータがありません。</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
