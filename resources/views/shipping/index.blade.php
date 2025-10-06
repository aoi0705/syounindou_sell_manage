@extends('layouts.app')

@section('content')
  @php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $items */
  @endphp

<h1 style="margin:0 0 12px; display:flex; align-items:center; gap:10px;">
    発送処理
    <a class="btn ghost sm" href="{{ route('settings.mail.edit') }}" title="発送完了メールの文面を編集">メール設定</a>
</h1>

  {{-- 検索 --}}
  <form method="get" class="toolbar" action="{{ route('shipping.index') }}">
    <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="注文番号 / 購入者 / SKU / 商品名で検索">
    <div class="spacer"></div>
    <button class="btn">検索</button>
    <a class="btn ghost" href="{{ route('shipping.index') }}">リセット</a>
  </form>

  {{-- 発送処理フォーム：一覧 → チェック → 追跡番号入力 --}}
  <form method="post" action="{{ route('shipping.dispatch') }}" id="dispatchForm">
    @csrf

    <div style="overflow:auto; margin-top:12px;">
      <table>
        <thead>
          <tr>
            <th style="width:34px;"><input type="checkbox" id="checkAll"></th>
            <th>注文番号</th>
            <th>購入者</th>
            <th>配送希望日/時間帯</th>
            <th>お届け先</th>
            <th>電話</th>
            <th>SKU</th>
            <th>商品名</th>
            <th>数量</th>
            <th>追跡番号</th>
          </tr>
        </thead>
        <tbody id="itemTbody">
        @forelse ($items as $it)
          <tr
            data-item-id="{{ $it->id }}"
            data-order-no="{{ $it->order->order_no }}"
            data-email="{{ $it->order->buyer_email }}"
            data-name="{{ $it->name }}"
            data-qty="{{ $it->quantity }}"
          >
            <td>
              <label style="display:inline-flex;align-items:center;gap:6px;opacity:1;" class="row-label">
                <input type="checkbox" name="ids[]" value="{{ $it->id }}" class="row-check">
              </label>
            </td>
            <td><a class="btn ghost sm" href="{{ route('orders.show', $it->order) }}">{{ $it->order->order_no }}</a></td>
            <td>{{ $it->order->buyer_name }}</td>
            <td>
              {{ $it->order->ship_date_request ?? '—' }}<br>
              <span style="color:#64748b;">{{ $it->order->ship_time_window ?? '' }}</span>
            </td>
            <td style="white-space:pre-wrap; max-width:320px;">{{ $it->order->shipto_name }} / {{ $it->order->shipto_address_full }}</td>
            <td>{{ $it->order->shipto_tel }}</td>
            <td><span class="pill">{{ $it->sku }}</span></td>
            <td>{{ $it->name }}</td>
            <td style="text-align:right;">{{ $it->quantity }}</td>
            <td>
              <input
                type="text"
                name="tracking_no[{{ $it->id }}]"
                placeholder="追跡番号"
                value="{{ old('tracking_no.'.$it->id) ?? $it->tracking_no }}"
                class="tracking-input"
                style="width:160px;"
              >
            </td>
          </tr>
        @empty
          <tr><td colspan="10" style="text-align:center; color:#64748b;">（送り状発行済みの明細はありません）</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div style="display:flex; align-items:center; gap:10px; margin-top:12px; flex-wrap:wrap;">
      {{ $items->links() }}
      <div class="spacer"></div>

      {{-- ★ 追跡番号を保存（formaction で別エンドポイントへ POST） --}}
      <button type="submit"
              class="btn"
              formaction="{{ route('shipping.saveTracking') }}"
              formmethod="post">
        追跡番号を保存
      </button>

      {{-- 発送処理（確認モーダル） --}}
      <button class="btn primary" id="openConfirm">選択した明細を発送処理</button>
    </div>

    {{-- 確認モーダル --}}
    <div id="confirmModal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:50; align-items:center; justify-content:center; padding:16px;">
      <div style="background:#fff; border-radius:14px; max-width:840px; width:100%; padding:18px; box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <h3 style="margin:0 0 8px;">発送処理の確認</h3>
        <p style="color:#475569; margin: 6px 0 12px;">以下の明細について発送完了メールを送信し、ステータスを「発送済み」に更新します。本当に実行しますか？</p>

        <div id="confirmList" style="max-height:360px; overflow:auto; border:1px solid #e5e7eb; border-radius:10px;">
          {{-- JSで詰めます --}}
        </div>

        <div style="display:flex; gap:10px; margin-top:12px; justify-content:flex-end;">
          <button type="button" class="btn ghost" id="cancelConfirm">キャンセル</button>
          <button type="submit" class="btn danger">確定して発送処理</button>
        </div>
      </div>
    </div>
  </form>

  @if ($errors->any())
    <div style="margin-top:12px; color:#b91c1c;">
      <ul style="margin:0; padding-left:18px;">
        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  @if (session('status'))
    <div style="margin-top:12px; color:#166534; background:#dcfce7; border:1px solid #86efac; padding:10px; border-radius:8px;">
      {{ session('status') }}
    </div>
  @endif

  <script>
    const form    = document.getElementById('dispatchForm');
    const modal   = document.getElementById('confirmModal');
    const cancel  = document.getElementById('cancelConfirm');
    const listBox = document.getElementById('confirmList');

    // 行の状態反映：追跡未入力はチェック不可＆グレーアウト
    function refreshRowState(tr) {
      const id  = tr.dataset.itemId;
      const cb  = tr.querySelector('.row-check');
      const lab = tr.querySelector('.row-label');
      const tn  = (form.querySelector(`[name="tracking_no[${id}]"]`)?.value || '').trim();

      if (!cb || !lab) return;

      if (tn === '') {
        cb.checked  = false;
        cb.disabled = true;
        lab.style.opacity = .4;
        lab.title = '追跡番号を入力すると選択できます';
      } else {
        cb.disabled = false;
        lab.style.opacity = 1;
        lab.title = '';
      }
    }

    function refreshAllRows() {
      document.querySelectorAll('#itemTbody tr').forEach(refreshRowState);
    }

    // 初期＆入力の都度
    document.addEventListener('DOMContentLoaded', refreshAllRows);
    document.querySelectorAll('.tracking-input').forEach(inp => {
      inp.addEventListener('input', (e) => {
        const tr = e.target.closest('tr');
        refreshRowState(tr);
      });
    });

    // 全選択は disabled を除外
    document.getElementById('checkAll')?.addEventListener('change', function(){
      document.querySelectorAll('.row-check').forEach(cb => {
        if (!cb.disabled) cb.checked = this.checked;
      });
    });

    // モーダル
    document.getElementById('openConfirm')?.addEventListener('click', function(ev){
      ev.preventDefault();
      const rows = Array.from(document.querySelectorAll('#itemTbody tr'));
      const picked = rows.filter(tr => tr.querySelector('.row-check')?.checked);

      if (picked.length === 0) {
        alert('発送処理する明細を選択してください。');
        return;
      }

      // 念のため追跡番号の再チェック
      for (const tr of picked) {
        const id = tr.dataset.itemId;
        const tn = (form.querySelector(`[name="tracking_no[${id}]"]`)?.value || '').trim();
        if (!tn) { alert(`明細ID ${id} の追跡番号が未入力です。`); return; }
      }

      // 明細リストを表示
      let html = '<table style="width:100%"><thead><tr>' +
                 '<th style="text-align:left;">注文番号</th>' +
                 '<th style="text-align:left;">メール宛先</th>' +
                 '<th style="text-align:left;">商品名</th>' +
                 '<th style="text-align:right;">数量</th>' +
                 '<th style="text-align:left;">追跡番号</th>' +
                 '</tr></thead><tbody>';

      for (const tr of picked) {
        const id   = tr.dataset.itemId;
        const ord  = tr.dataset.orderNo;
        const mail = tr.dataset.email || '（未登録）';
        const name = tr.dataset.name;
        const qty  = tr.dataset.qty;
        const tn   = (form.querySelector(`[name="tracking_no[${id}]"]`)?.value || '').trim();
        html += `<tr>
          <td>${ord}</td>
          <td>${mail}</td>
          <td>${name}</td>
          <td style="text-align:right;">${qty}</td>
          <td>${tn}</td>
        </tr>`;
      }
      html += '</tbody></table>';
      listBox.innerHTML = html;
      modal.style.display = 'flex';
    });

    cancel?.addEventListener('click', () => modal.style.display = 'none');
    modal?.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
  </script>
@endsection
