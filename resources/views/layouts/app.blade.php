<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>販売管理</title>
  <style>
    * { box-sizing: border-box; }
    :root{
      --bg: #f7f8fb; --panel: #ffffff; --muted: #64748b; --border: #e5e7eb; --ink: #0f172a;
      --brand: #2563eb; --accent: #0ea5e9; --danger: #ef4444; --shadow: 0 6px 18px rgba(0,0,0,.06); --radius: 12px;
    }
    body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif; background: var(--bg); color: #222; }
    .app { display: grid; grid-template-columns: 220px 1fr; min-height: 100vh; }
    aside { background: #0f172a; color: #e5e7eb; padding: 16px; }
    .brand { font-weight: 700; margin: 0 0 12px; }
    nav a { display:block; padding:10px 12px; border-radius:10px; color:#d1d5db; text-decoration:none; margin-bottom:6px; }
    nav a:hover, nav a.active { background:#1f2937; color:#fff; }
    main { padding: 20px; }
    .card { background:var(--panel); border-radius:var(--radius); box-shadow:var(--shadow); padding: 16px; }
    .toolbar { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
    .toolbar.filterbar { background:#f8fafc; border:1px solid var(--border); border-radius: var(--radius); padding: 12px; }
    .spacer { flex: 1 1 auto; }
    .row { display:flex; gap:14px; flex-wrap:wrap; align-items:flex-start; }
    textarea, input[type="text"], input[type="number"], input[type="search"], input[type="month"], select {
      width:100%; padding:12px; border:1px solid var(--border); border-radius:10px; font-size:14px; background:#fff;
    }
    table { width:100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border-bottom:1px solid #ececec; padding:8px; text-align:left; vertical-align:top; }
    th { background:#fafafa; }
    .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; border:none; cursor:pointer; text-decoration:none; padding:10px 14px; border-radius:10px; font-weight:600; }
    .btn.primary   { background:var(--brand); color:#fff; }
    .btn.secondary { background:var(--accent); color:#fff; }
    .btn.ghost     { background:#f1f5f9; color:var(--ink); }
    .btn.danger    { background:var(--danger); color:#fff; }
    .btn.sm        { padding:6px 10px; border-radius:8px; font-size:13px; }
    .actions { display:flex; gap:8px; flex-wrap:wrap; }
    .flash { background:#ecfeff; border:1px solid #a5f3fc; color:#155e75; padding:8px 12px; border-radius:10px; margin-bottom:12px; }
    .error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:8px 12px; border-radius:10px; margin-bottom:12px; white-space: pre-wrap; }
    .pill { display:inline-block; padding:4px 8px; border-radius:999px; background:#f1f5f9; color:var(--ink); font-size:12px; }

    .field { display:flex; flex-direction:column; gap:6px; min-width:200px; }
    .label { font-size:12px; color:var(--muted); font-weight:700; letter-spacing:.02em; }
    .input-icon { position:relative; }
    .input-icon > .icon {
      position:absolute; left:10px; top:50%; transform:translateY(-50%); width:18px; height:18px; opacity:.6; pointer-events:none;
    }
    .input-icon > input, .input-icon > select { padding-left: 36px; }
    input[type="month"]::-webkit-calendar-picker-indicator { opacity:0; cursor:pointer; }
    input[type="month"] { appearance: textfield; }
    @media (max-width: 780px) {
      .app { grid-template-columns: 1fr; }
      aside { position: sticky; top: 0; z-index: 10; }
    }
  </style>
</head>
<body>
<div class="app">
  <aside>
    <h2 class="brand">笑人堂<br>販売管理システム</h2>
    <nav>
      <a href="{{ route('orders.import.create') }}" class="{{ request()->routeIs('orders.import.*') ? 'active' : '' }}">注文取り込み</a>
      <a href="{{ route('orders.index') }}" class="{{ request()->routeIs('orders.index') ? 'active' : '' }}">注文一覧</a>
      <a href="{{ route('bank.index') }}" class="{{ request()->routeIs('bank.*') ? 'active' : '' }}">振り込み記録</a>
      <a href="{{ route('po.index') }}" class="{{ request()->routeIs('po.*') ? 'active' : '' }}">注文書発行</a>
      <a href="{{ route('labels.index') }}">送り状発行</a>
      <a href="{{ route('shipping.index') }}">発送処理</a>
      <a href="{{ route('income.index') }}">収支</a>
    </nav>
  </aside>
  <main>
    @if (session('status'))
      <div class="flash">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="error">{{ implode("\n", $errors->all()) }}</div>
    @endif
    <div class="card">
      @yield('content')
    </div>
  </main>
</div>
</body>
</html>
