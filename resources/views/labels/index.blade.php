@extends('layouts.app')

@section('content')
  <h1 style="margin:0 0 12px;">送り状発行</h1>

  <form method="get" action="{{ route('labels.index') }}" class="toolbar filterbar">
    <div class="field" style="min-width:260px;">
      <div class="label">検索（SKU / 品名）</div>
      <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="SKUや品名で検索">
    </div>
    <div class="spacer"></div>
    <button class="btn">絞り込む</button>
    <a class="btn ghost" href="{{ route('labels.index') }}">リセット</a>
  </form>

  <form method="post" action="{{ route('labels.export') }}" onsubmit="return confirmSel();">
    @csrf

    <div style="display:flex; align-items:center; justify-content:space-between; margin: 8px 0;">
      <strong>注文アイテム一覧（チェックした行が対象）</strong>
      <div class="actions">
        <button class="btn ghost sm" type="button" onclick="toggleAll(true)">全選択</button>
        <button class="btn ghost sm" type="button" onclick="toggleAll(false)">全解除</button>
        <button class="btn primary sm" type="submit">選択アイテムをExcel出力</button>
      </div>
    </div>

    <div style="overflow:auto; border:1px solid #ececec; border-radius:10px;">
      <table>
        <thead>
          <tr>
            <th style="width:40px;">選択</th>
            <th>注文番号</th>
            <th>購入者</th>
            <th>お届け先</th>
            <th>SKU</th>
            <th>品名</th>
            <th>数量</th>
            <th>配送希望日</th>
            <th>時間帯</th>
            <th>作成日</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($items as $it)
            @php $o = $it->order; @endphp
            <tr>
              <td><input type="checkbox" name="order_item_ids[]" value="{{ $it->id }}"></td>
              <td>{{ $o->order_no ?? '-' }}</td>
              <td>{{ $o->buyer_name ?? '-' }}</td>
              <td>{{ $o->shipto_name ?? '-' }}</td>
              <td>{{ $it->sku }}</td>
              <td>{{ $it->name }}</td>
              <td>{{ $it->quantity }}</td>
              <td>{{ $o->ship_date_request ?? '指定なし' }}</td>
              <td>{{ $o->ship_time_window ?? '-' }}</td>
              <td>{{ optional($it->created_at)->format('Y-m-d') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div style="margin-top:8px;">
      {{ $items->links() }}
    </div>
  </form>

  <script>
    function toggleAll(on){
      document.querySelectorAll('input[type="checkbox"][name="order_item_ids[]"]')
        .forEach(cb => cb.checked = !!on);
    }
    function confirmSel(){
      const any = Array.from(document.querySelectorAll('input[name="order_item_ids[]"]'))
                  .some(cb => cb.checked);
      if(!any){ alert('アイテムが選択されていません。'); return false; }
      return true;
    }
  </script>
@endsection
