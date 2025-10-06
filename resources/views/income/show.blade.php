@extends('layouts.app')

@section('content')
  <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
    <h1 style="margin:0;">収入明細（{{ $ym }}）</h1>
    <a class="btn ghost sm" href="{{ route('income.index', ['basis'=>$basis, 'mode'=>$mode]) }}">月一覧へ</a>
  </div>

  <div style="display:flex; gap:12px; flex-wrap:wrap;">
    <div style="padding:10px 12px; background:#fff; border:1px solid #e5e7eb; border-radius:10px;">
      <div style="font-size:12px; color:#64748b;">合計金額</div>
      <div style="font-size:20px; font-weight:700;">¥{{ number_format($sum['total']) }}</div>
    </div>
    <div style="padding:10px 12px; background:#fff; border:1px solid #e5e7eb; border-radius:10px;">
      <div style="font-size:12px; color:#64748b;">件数</div>
      <div style="font-size:20px; font-weight:700;">{{ $sum['count'] }} 件</div>
    </div>
  </div>

  <div style="overflow:auto; margin-top:12px;">
    <table>
      <thead>
        <tr>
          <th>日付</th>
          <th>注文番号</th>
          <th>購入者</th>
          <th>支払方法</th>
          <th>小計</th>
          <th>送料</th>
          <th>クール</th>
          <th>税10</th>
          <th>税8</th>
          <th>合計</th>
          <th>備考</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($rows as $r)
          <tr>
            <td>{{ $r['日付'] }}</td>
            <td>{{ $r['注文番号'] }}</td>
            <td>{{ $r['購入者'] }}</td>
            <td>{{ $r['支払方法'] }}</td>
            <td>{{ number_format($r['小計']) }}</td>
            <td>{{ number_format($r['送料']) }}</td>
            <td>{{ number_format($r['クール']) }}</td>
            <td>{{ number_format($r['税10']) }}</td>
            <td>{{ number_format($r['税8']) }}</td>
            <td><strong>{{ number_format($r['合計']) }}</strong></td>
            <td>{{ $r['備考'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection
