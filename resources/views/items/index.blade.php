<?php /** @var \Illuminate\Pagination\LengthAwarePaginator $items */ ?>
<?php $__env->startSection('content'); ?>
  <h1 style="margin:0 0 12px;">商品一覧</h1>

  <form method="get" class="toolbar" action="<?php echo e(route('items.index')); ?>">
    <input type="search" name="q" value="<?php echo e($q ?? ''); ?>" placeholder="注文番号 / 購入者 / SKU / 商品名で検索">
    <select name="sort" style="padding:10px;border:1px solid #e5e7eb;border-radius:10px;">
      <option value="created_desc" <?php echo (isset($sort)&&$sort==='created_desc')?'selected':''; ?>>新しい順</option>
      <option value="created_asc"  <?php echo (isset($sort)&&$sort==='created_asc')?'selected':''; ?>>古い順</option>
      <option value="total_desc"   <?php echo (isset($sort)&&$sort==='total_desc')?'selected':''; ?>>金額が大きい順</option>
    </select>
    <div class="spacer"></div>
    <button class="btn">検索</button>
    <a class="btn ghost" href="<?php echo e(route('items.index')); ?>">リセット</a>
  </form>

  <div style="overflow:auto; margin-top:12px;">
    <table>
      <thead>
        <tr>
          <th>注文番号</th>
          <th>購入者</th>
          <th>SKU</th>
          <th>商品名</th>
          <th>単価</th>
          <th>数量</th>
          <th>小計</th>
          <th>支払方法</th>
          <th>購入日</th>
          <th style="width:160px;">操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><a href="<?php echo e(route('orders.show', $it->order)); ?>"><?php echo e($it->order->order_no); ?></a></td>
            <td><?php echo e($it->order->buyer_name); ?></td>
            <td><span class="pill"><?php echo e($it->sku); ?></span></td>
            <td><?php echo e($it->name); ?></td>
            <td><?php echo e(number_format($it->unit_price)); ?>円</td>
            <td><?php echo e($it->quantity); ?></td>
            <td><strong><?php echo e(number_format($it->line_total)); ?>円</strong></td>
            <td><?php echo e($it->order->payment_method); ?></td>
            <td><?php echo e($it->order->purchased_at_text ?? optional($it->order->purchased_at)->format('Y-m-d H:i')); ?></td>
            <td>
              <a class="btn secondary" href="<?php echo e(route('items.edit', $it)); ?>">編集</a>
              <form method="post" action="<?php echo e(route('items.destroy', $it)); ?>" class="inline" onsubmit="return confirm('削除してよろしいですか？');">
                <?php echo method_field('delete'); ?>
                <?php echo csrf_field(); ?>
                <button class="btn danger">削除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div style="margin-top:12px;">
    <?php echo $items->links(); ?>
  </div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app')->render(); ?>
