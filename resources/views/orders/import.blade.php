@extends('layouts.app')

@section('content')
<style>
  .gift-toggle { margin-top:12px; display:block; }
  .gift-toggle .switch-wrap{
    display:flex; align-items:center; gap:12px;
    padding:14px 16px; border:1px solid #e5e7eb; border-radius:12px;
    background:#fff; cursor:pointer; transition: box-shadow .2s, border-color .2s, transform .03s;
  }
  .gift-toggle .switch-wrap:hover{ box-shadow:0 2px 12px rgba(0,0,0,.06); border-color:#d1d5db; }
  .gift-toggle .switch-wrap:active{ transform: scale(.998); }

  /* 視覚的スイッチ */
  .gift-cb{ position:absolute; opacity:0; width:1px; height:1px; }
  .gift-cb:focus-visible + .switch-wrap{ box-shadow:0 0 0 3px #bfdbfe; border-color:#93c5fd; outline:0; }

  .switch{ position:relative; width:56px; height:32px; border-radius:9999px; background:#e5e7eb; flex:0 0 auto; transition:background .2s; }
  .switch::after{
    content:""; position:absolute; top:4px; left:4px; width:24px; height:24px; border-radius:50%;
    background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.25); transition:transform .2s;
  }
  .gift-cb:checked + .switch-wrap .switch{ background:#16a34a; }
  .gift-cb:checked + .switch-wrap .switch::after{ transform: translateX(24px); }

  .switch-label{ font-weight:700; }
  .switch-sub{ color:#64748b; font-size:12px; }
</style>
  <h1 style="margin:0 0 12px;">注文取り込み</h1>

  <form method="post" action="{{ route('orders.import.store') }}">
    @csrf

    <label class="label">メール本文</label>
    <textarea
      name="raw_body"
      style="min-height: 560px; font: 14px/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace;"
      placeholder="受注メール本文を丸ごと貼り付け">{{ old('raw_body') }}</textarea>

    {{-- ★ 追加：備考（任意） --}}
    <div style="margin-top:12px;">
      <label class="label">備考（任意）</label>
      <textarea
        name="note"
        style="min-height: 100px; font: 14px/1.6 system-ui, -apple-system, Segoe UI, Roboto;"
        placeholder="社内向けメモや注意事項など">{{ old('note') }}</textarea>
      @error('note')
        <p style="color:#b91c1c; margin-top:4px;">{{ $message }}</p>
      @enderror
    </div>

    <div style="margin-top:12px;">
        <label class="gift-toggle">
            <input
              class="gift-cb"
              type="checkbox"
              name="is_gift"
              value="1"
              {{ old('is_gift') ? 'checked' : '' }}
              aria-label="贈答品として扱う"
            >
            <div class="switch-wrap">
              <div class="switch" aria-hidden="true"></div>
              <div>
                <div class="switch-label">贈答品として扱う</div>
              </div>
            </div>
          </label>
    </div>

    <div class="actions" style="margin-top:10px;">
      <button class="btn primary">取り込む</button>
      <a class="btn ghost" href="{{ route('orders.index') }}">注文一覧へ</a>
    </div>
  </form>
@endsection
