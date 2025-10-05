<!doctype html>
<html lang="ja" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>販売管理 | 注文内容取り込み</title>
  <!-- Tailwind CDN（プロトタイプ用：本番はビルド推奨） -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Alpine.js（軽量な状態管理） -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full bg-gray-50 text-gray-900" x-data="app()"
      x-init="init()"
      @hashchange.window="routeFromHash()">

  <!-- App Shell -->
  <div class="min-h-screen flex">
    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
           class="fixed md:static z-40 inset-y-0 left-0 w-72 bg-white border-r border-gray-200 transform transition-transform md:transition-none">
      <div class="h-16 px-5 flex items-center justify-between border-b">
        <div>
          <h1 class="text-lg font-semibold">販売管理</h1>
          <p class="text-xs text-gray-500">Laravel Front (UI)</p>
        </div>
        <button class="md:hidden p-2 rounded hover:bg-gray-100" @click="sidebarOpen=false" aria-label="メニューを閉じる">
          ✕
        </button>
      </div>

      <nav class="py-2">
        <a href="#/orders/import"
           :class="navClass('/orders/import')"
           class="block px-5 py-3 text-sm">注文内容取り込み</a>

        <!-- 予定メニュー（後で実装） -->
        <a href="#/orders/list" :class="navClass('/orders/list')" class="block px-5 py-3 text-sm">受注一覧（予定）</a>
        <a href="#/customers"    :class="navClass('/customers')"    class="block px-5 py-3 text-sm">顧客（予定）</a>
        <a href="#/inventory"    :class="navClass('/inventory')"    class="block px-5 py-3 text-sm">在庫（予定）</a>
        <a href="#/reports"      :class="navClass('/reports')"      class="block px-5 py-3 text-sm">レポート（予定）</a>
      </nav>

      <div class="mt-auto p-4 text-xs text-gray-500 hidden md:block">
        © 2025 Your Company
      </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 md:ml-0">
      <!-- Top bar -->
      <header class="h-16 bg-white border-b flex items-center px-4 md:px-6 gap-3">
        <button class="md:hidden p-2 rounded hover:bg-gray-100" @click="sidebarOpen=true" aria-label="メニューを開く">☰</button>
        <h2 class="text-base font-semibold" x-text="pageTitle"></h2>
        <div class="ml-auto text-xs text-gray-500" x-show="currentRoute === '/orders/import'">
          <span>文字数: <span x-text="mailBody.length"></span></span>
          <span class="mx-2">|</span>
          <span>行数: <span x-text="mailBody.split('\n').length"></span></span>
        </div>
      </header>

      <!-- Content wrapper -->
      <main class="p-4 md:p-6">
        <!-- ====== 注文内容取り込み ====== -->
        <section x-show="currentRoute === '/orders/import'" x-cloak>
          <div class="mx-auto max-w-4xl">
            <!-- Card -->
            <div class="bg-white border rounded-xl shadow-sm">
              <div class="px-5 py-4 border-b">
                <h3 class="font-semibold">メール本文を貼り付け</h3>
                <p class="mt-1 text-sm text-gray-500">
                  改行を保持したまま後工程へ渡せるように保存時はプレーンテキストのまま扱います。
                  表示時は <code class="bg-gray-100 px-1 rounded">white-space: pre-wrap;</code> を使用すると改行がそのまま表示されます。
                </p>
              </div>

              <div class="p-5 space-y-4">
                <label for="mail_body" class="block text-sm font-medium">メール本文</label>
                <textarea id="mail_body" x-model="mailBody" rows="16"
                          class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm leading-6"
                          placeholder="ここに注文メール本文を貼り付けてください（改行そのまま）"></textarea>

                <div class="flex flex-wrap items-center gap-2">
                  <button type="button"
                          class="rounded-lg bg-indigo-600 text-white px-4 py-2 hover:bg-indigo-700"
                          @click="togglePreview()">
                    <span x-text="preview ? 'プレビューを隠す' : 'プレビュー表示'"></span>
                  </button>

                  <button type="button"
                          class="rounded-lg bg-white border px-4 py-2 hover:bg-gray-50"
                          @click="copyBody()">
                    コピー
                  </button>

                  <button type="button"
                          class="rounded-lg bg-white border px-4 py-2 hover:bg-gray-50"
                          @click="clearBody()">
                    クリア
                  </button>

                  <!-- 疑似送信：バックエンド無し -->
                  <button type="button"
                          class="rounded-lg bg-white border px-4 py-2 hover:bg-gray-50"
                          @click="fakeSubmit()">
                    ダミー送信（UIのみ）
                  </button>

                  <span class="text-xs text-gray-500 ml-auto">自動保存: <span x-text="autosavedAt"></span></span>
                </div>

                <!-- Preview -->
                <div x-show="preview" x-cloak class="mt-4">
                  <div class="text-sm text-gray-700 mb-2">プレビュー（改行保持）</div>
                  <div class="whitespace-pre-wrap font-mono text-sm leading-6 p-4 rounded-lg border bg-gray-50"
                       x-text="mailBody || '（本文が空です）'"></div>
                </div>
              </div>
            </div>

            <!-- Tips -->
            <div class="mt-4 text-xs text-gray-500">
              <ul class="list-disc pl-5 space-y-1">
                <li>後でサーバに送信する際は、そのままプレーンテキストで送れば改行は保持されます。</li>
                <li>表示時の改行保持は <code>CSS: whitespace-pre-wrap</code> または <code>nl2br()</code>（サーバ側）を利用します。</li>
                <li>この画面はUIのみです（API/サーバ保存は未実装）。</li>
              </ul>
            </div>
          </div>
        </section>

        <!-- ====== 予定ページの空プレースホルダ（UIだけ存在） ====== -->
        <section x-show="currentRoute === '/orders/list'" x-cloak>
          <div class="mx-auto max-w-4xl text-gray-600">受注一覧（UIのみ・後日実装）</div>
        </section>
        <section x-show="currentRoute === '/customers'" x-cloak>
          <div class="mx-auto max-w-4xl text-gray-600">顧客（UIのみ・後日実装）</div>
        </section>
        <section x-show="currentRoute === '/inventory'" x-cloak>
          <div class="mx-auto max-w-4xl text-gray-600">在庫（UIのみ・後日実装）</div>
        </section>
        <section x-show="currentRoute === '/reports'" x-cloak>
          <div class="mx-auto max-w-4xl text-gray-600">レポート（UIのみ・後日実装）</div>
        </section>
      </main>
    </div>
  </div>

  <script>
    function app() {
      return {
        /* ---- State ---- */
        sidebarOpen: false,
        currentRoute: '/orders/import',
        pageTitle: '注文内容取り込み',
        mailBody: '',
        preview: true,
        autosavedAt: '―',

        /* ---- Init & Router ---- */
        init() {
          this.routeFromHash();
          // 復元
          const saved = localStorage.getItem('order_mail_body');
          if (saved !== null) this.mailBody = saved;
          const savedAt = localStorage.getItem('order_mail_saved_at');
          if (savedAt) this.autosavedAt = savedAt;
          // 自動保存（デバウンス）
          this.$watch('mailBody', this.debounce(() => {
            localStorage.setItem('order_mail_body', this.mailBody);
            const now = new Date();
            this.autosavedAt = now.toLocaleString();
            localStorage.setItem('order_mail_saved_at', this.autosavedAt);
          }, 300));
        },

        routeFromHash() {
          const hash = location.hash.replace('#', '') || '/orders/import';
          this.currentRoute = hash;
          const titles = {
            '/orders/import': '注文内容取り込み',
            '/orders/list':   '受注一覧（予定）',
            '/customers':     '顧客（予定）',
            '/inventory':     '在庫（予定）',
            '/reports':       'レポート（予定）',
          };
          this.pageTitle = titles[hash] || '画面';
        },

        navClass(route) {
          const base = 'text-gray-700 hover:bg-gray-100';
          const active = 'bg-indigo-50 text-indigo-700 font-semibold';
          return (this.currentRoute === route) ? active : base;
        },

        /* ---- Actions ---- */
        togglePreview() { this.preview = !this.preview; },
        clearBody() {
          if (!this.mailBody) return;
          if (confirm('本文をクリアしますか？')) this.mailBody = '';
        },
        async copyBody() {
          try {
            await navigator.clipboard.writeText(this.mailBody);
            this.toast('コピーしました');
          } catch {
            this.toast('コピーに失敗しました');
          }
        },
        fakeSubmit() {
          // 実APIがないため、ペイロード例をコンソールへ
          console.log('[Dummy Submit]', { mail_body: this.mailBody });
          this.toast('ダミー送信（コンソールに出力）');
        },

        /* ---- Utils ---- */
        toast(msg) {
          // 簡易トースト
          const el = document.createElement('div');
          el.textContent = msg;
          el.className = 'fixed bottom-4 left-1/2 -translate-x-1/2 bg-black text-white text-sm px-3 py-2 rounded-lg opacity-0 transition';
          document.body.appendChild(el);
          requestAnimationFrame(() => { el.classList.remove('opacity-0'); });
          setTimeout(() => {
            el.classList.add('opacity-0');
            setTimeout(() => el.remove(), 300);
          }, 1500);
        },
        debounce(fn, wait = 300) {
          let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), wait); };
        },
      }
    }
  </script>

  <style>
    /* x-cloak を使って非表示状態のチラつきを防止 */
    [x-cloak] { display: none !important; }
  </style>
</body>
</html>
