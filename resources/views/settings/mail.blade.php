@extends('layouts.app')

@section('content')
  <h1 style="margin:0 0 6px;">メール設定（発送完了）</h1>

  {{-- ★ IT初心者向けのやさしい説明 --}}
  @verbatim
  <div style="margin:0 0 14px; color:#475569; font-size:13px; line-height:1.7;">
    <strong>この画面では、お客様に送る「発送完了メール」の文章を自由に変更できます。</strong><br>
    文中の <code>{{注文番号}}</code> や <code>{{お届け先氏名}}</code> のような「かっこ」部分は、自動で注文の内容に置き換わります。
    難しい記号は不要です。次のルールだけ覚えればOKです。
    <ul style="margin:8px 0 0 20px;">
      <li>差し替えたいところに、<code>{{注文番号}}</code> のように書くと、自動で本当の値に変わります。</li>
      <li>商品が複数あるときは、表の中などで、次の囲みで繰り返せます：<br>
        <code>{{#明細}}</code>（ここから繰り返し）… <code>{{/明細}}</code>（ここまで）</li>
    </ul>
    <div style="color:#64748b; font-size:12px; margin-top:6px;">
      使える言葉（プレースホルダ）：
      <div style="margin-top:4px;">
        <strong>注文情報</strong>：
        <code>{{注文番号}}</code>、<code>{{購入者氏名}}</code>、<code>{{お届け先氏名}}</code>、<code>{{お届け先住所}}</code>、<code>{{お届け先電話}}</code>、<code>{{配送業者}}</code>、<code>{{配送希望日}}</code>、<code>{{配送希望時間帯}}</code>、<code>{{合計金額}}</code>、<code>{{店舗名}}</code>、<code>{{アプリ名}}</code><br>
        <strong>商品ごと（明細の中で使います）</strong>：
        <code>{{SKU}}</code>、<code>{{商品名}}</code>、<code>{{数量}}</code>、<code>{{追跡番号}}</code>、<code>{{単価}}</code>、<code>{{小計}}</code>
      </div>
    </div>
  </div>
  @endverbatim

  @if (session('status'))
    <div style="margin:8px 0; color:#166534; background:#dcfce7; border:1px solid #86efac; padding:10px; border-radius:8px;">
      {{ session('status') }}
    </div>
  @endif

  <form method="post" action="{{ route('settings.mail.update') }}" class="row" style="gap:24px;">
    @method('patch')
    @csrf

    <div style="flex:1; min-width:360px;">
      <h3>テンプレート編集</h3>

      <div class="field">
        <div class="label">件名（例： 【発送完了】ご注文 {{ '{{$'}}注文番号}} のお知らせ）</div>
        <input type="text" name="subject" value="{{ old('subject', $template->subject) }}" required>
        @error('subject')<p style="color:#b91c1c;">{{ $message }}</p>@enderror
      </div>

      <div class="field" style="margin-top:10px;">
        <div class="label">本文（Markdown対応。改行や表が使えます）</div>
        <textarea name="body_md" style="min-height:420px; font: 14px/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;" required>{{ old('body_md', $template->body_md) }}</textarea>
        @error('body_md')<p style="color:#b91c1c;">{{ $message }}</p>@enderror
      </div>

      <div class="actions" style="margin-top:10px;">
        <button class="btn primary">保存</button>
        <a class="btn ghost" href="{{ route('settings.mail.edit') }}">リセット</a>
      </div>
    </div>
  </form>
@endsection
