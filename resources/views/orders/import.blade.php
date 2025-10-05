@extends('layouts.app')

@section('content')
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

    <div class="actions" style="margin-top:10px;">
      <button class="btn primary">取り込む</button>
      <a class="btn ghost" href="{{ route('orders.index') }}">注文一覧へ</a>
    </div>
  </form>
@endsection
