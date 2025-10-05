<?php /** @var \App\Models\Order $order */ ?>
<?php $__env->startSection('content'); ?>
  <div class="row" style="justify-content:space-between; align-items:flex-start;">
    <h1 style="margin:0 0 12px;">注文 #<?php echo e($order->order_no); ?></h1>
    <div class="btn-group">
      <a class="btn ghost" href="<?php echo e(route('orders.index')); ?>">一覧に戻る</a>
      <form method="post" action="<?php echo e(route('orders.destroy', $order)); ?>" class="inline" onsubmit="return confirm('この注文を削除すると明細も削除されます。よろしいですか？');">
        <?php echo method_field('delete'); ?>
        <?php echo csrf_field(); ?>
        <button class="btn danger">注文を削除</button>
      </form>
    </div>
  </div>

  <form method="post" action="<?php echo e(route('orders.update', $order)); ?>">
    <?php echo method_field('patch'); ?>
    <?php echo csrf_field(); ?>

    <div class="row" style="gap:24px;">
      <div style="flex:1; min-width:320px;">
        <h3>注文情報（編集可）</h3>
        <table>
          <tr><th class="w-min">店舗</th><td><?php echo e($order->shop_name); ?></td></tr>
          <tr><th>購入日(原文)</th><td><?php echo e($order->purchased_at_text); ?></td></tr>
          <tr><th>購入日時</th><td><?php echo e(optional($order->purchased_at)->format('Y-m-d H:i:s')); ?></td></tr>
          <tr><th>支払方法</th><td><input type="text" name="payment_method" value="<?php echo e(old('payment_method', $order->payment_method)); ?>"></td></tr>
          <tr><th>メール受信</th><td><input type="text" name="mail_preference" value="<?php echo e(old('mail_preference', $order->mail_preference)); ?>"></td></tr>
          <tr><th>配送便</th><td><input type="text" name="ship_carrier" value="<?php echo e(old('ship_carrier', $order->ship_carrier)); ?>"></td></tr>
          <tr><th>配送希望日</th><td><input type="text" name="ship_date_request" value="<?php echo e(old('ship_date_request', $order->ship_date_request)); ?>"></td></tr>
          <tr><th>配送希望時間帯</th><td><input type="text" name="ship_time_window" value="<?php echo e(old('ship_time_window', $order->ship_time_window)); ?>"></td></tr>
        <!-- 注文情報（編集可）テーブル内の任意の位置に追加 -->
        <tr>
            <th>発送済</th>
            <td>
            <input type="hidden" name="is_shipped" value="0">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_shipped" value="1" <?php echo e($order->is_shipped ? 'checked' : ''); ?>>
                <span>発送済みにする</span>
            </label>
            </td>
        </tr>
        <tr>
            <th>贈答品</th>
            <td>
            <input type="hidden" name="is_gift" value="0">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_gift" value="1" <?php echo e($order->is_gift ? 'checked' : ''); ?>>
                <span>贈答扱い</span>
            </label>
            </td>
        </tr>
        </table>

        <h3>金額</h3>
        <table>
          <tr><th>小計</th><td><?php echo e(number_format($order->subtotal)); ?>円</td></tr>
          <tr><th>送料</th><td><?php echo e(number_format($order->shipping_fee)); ?>円</td></tr>
          <tr><th>クール料金</th><td><?php echo e(number_format($order->cool_fee)); ?>円</td></tr>
          <tr><th>合計</th><td><strong><?php echo e(number_format($order->total)); ?>円</strong></td></tr>
          <tr><th>税(10%)</th><td><?php echo e(number_format($order->tax10)); ?>円</td></tr>
          <tr><th>税(8%)</th><td><?php echo e(number_format($order->tax8)); ?>円</td></tr>
        </table>
      </div>

      <div style="flex:1; min-width:320px;">
        <h3>購入者（編集可）</h3>
        <table>
          <tr><th class="w-min">氏名</th><td><input type="text" name="buyer_name" value="<?php echo e(old('buyer_name', $order->buyer_name)); ?>"></td></tr>
          <tr><th>カナ</th><td><input type="text" name="buyer_kana" value="<?php echo e(old('buyer_kana', $order->buyer_kana)); ?>"></td></tr>
          <tr><th>住所</th><td><textarea name="buyer_address_full" style="min-height:80px;"><?php echo e(old('buyer_address_full', $order->buyer_address_full)); ?></textarea></td></tr>
          <tr><th>TEL</th><td><input type="text" name="buyer_tel" value="<?php echo e(old('buyer_tel', $order->buyer_tel)); ?>"></td></tr>
          <tr><th>携帯</th><td><input type="text" name="buyer_mobile" value="<?php echo e(old('buyer_mobile', $order->buyer_mobile)); ?>"></td></tr>
          <tr><th>Email</th><td><input type="text" name="buyer_email" value="<?php echo e(old('buyer_email', $order->buyer_email)); ?>"></td></tr>
        </table>

        <h3>お届け先（編集可）</h3>
        <table>
          <tr><th class="w-min">氏名</th><td><input type="text" name="shipto_name" value="<?php echo e(old('shipto_name', $order->shipto_name)); ?>"></td></tr>
          <tr><th>カナ</th><td><input type="text" name="shipto_kana" value="<?php echo e(old('shipto_kana', $order->shipto_kana)); ?>"></td></tr>
          <tr><th>住所</th><td><textarea name="shipto_address_full" style="min-height:80px;"><?php echo e(old('shipto_address_full', $order->shipto_address_full)); ?></textarea></td></tr>
          <tr><th>TEL</th><td><input type="text" name="shipto_tel" value="<?php echo e(old('shipto_tel', $order->shipto_tel)); ?>"></td></tr>
        </table>
      </div>
    </div>

    <div class="btn-group" style="margin-top:12px;">
      <button class="btn">注文情報を保存</button>
      <a class="btn ghost" href="<?php echo e(route('orders.show', $order)); ?>">リセット</a>
    </div>
  </form>

  <h3 style="margin-top:24px;">注文明細（行ごとに編集/削除）</h3>
  <div style="overflow:auto;">
    <table>
      <thead>
        <tr>
          <th>商品番号</th>
          <th>商品名</th>
          <th>単価</th>
          <th>数量</th>
          <th>小計</th>
          <th style="width:220px;">操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($order->items as $it): ?>
        <tr>
          <form method="post" action="<?php echo e(route('orders.items.update', [$order, $it])); ?>">
            <?php echo method_field('patch'); ?>
            <?php echo csrf_field(); ?>
            <td><input class="w-min" type="text" name="sku" value="<?php echo e($it->sku); ?>"></td>
            <td><input type="text" name="name" value="<?php echo e($it->name); ?>" required></td>
            <td><input class="num" type="number" name="unit_price" value="<?php echo e($it->unit_price); ?>" min="0" required oninput="recalcRow(this)"></td>
            <td><input class="num" type="number" name="quantity" value="<?php echo e($it->quantity); ?>" min="0" required oninput="recalcRow(this)"></td>
            <td><input class="num" type="number" name="line_total_view" value="<?php echo e($it->unit_price * $it->quantity); ?>" readonly></td>
            <td class="actions">
              <button class="btn secondary sm">保存</button>
          </form>
              <form method="post" action="<?php echo e(route('orders.items.destroy', [$order, $it])); ?>" class="inline" onsubmit="return confirm('この明細を削除します。よろしいですか？');" style="margin:0;">
                <?php echo method_field('delete'); ?>
                <?php echo csrf_field(); ?>
                <button class="btn danger sm">削除</button>
              </form>
            </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <h2 style="margin-top:16px;">備考</h2>
    @if(!empty($order->note))
      <div style="white-space:pre-wrap; padding:8px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa;">
        {{ $order->note }}
      </div>
    @else
      <p style="color:#64748b;">（備考は入力されていません）</p>
    @endif
  </div>

  <details style="margin-top:16px;">
    <summary>元メール本文（デバッグ）</summary>
    <pre style="white-space: pre-wrap;"><?php echo e($order->raw_body); ?></pre>
  </details>

  <script>
    function recalcRow(el){
      const tr   = el.closest('tr');
      const up   = tr.querySelector('input[name="unit_price"]');
      const qty  = tr.querySelector('input[name="quantity"]');
      const out  = tr.querySelector('input[name="line_total_view"]');
      const v = (parseInt(up.value||'0')||0) * (parseInt(qty.value||'0')||0);
      out.value = v;
    }
  </script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app')->render(); ?>
