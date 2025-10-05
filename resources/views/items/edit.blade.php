<?php /** @var \App\Models\OrderItem $item */ ?>
<?php $__env->startSection('content'); ?>
  <h1 style="margin:0 0 12px;">商品編集</h1>
  <p class="help" style="margin-top:0;">注文 #<?php echo e($item->order->order_no); ?> / 購入者: <?php echo e($item->order->buyer_name); ?></p>

  <form method="post" action="<?php echo e(route('items.update', $item)); ?>" class="row" style="gap:16px;">
    <?php echo method_field('patch'); ?>
    <?php echo csrf_field(); ?>

    <div style="flex:1;min-width:220px;">
      <label class="help">商品番号 (SKU)</label>
      <input type="text" name="sku" value="<?php echo e(old('sku', $item->sku)); ?>">
    </div>

    <div style="flex:2;min-width:320px;">
      <label class="help">商品名</label>
      <input type="text" name="name" value="<?php echo e(old('name', $item->name)); ?>" required>
    </div>

    <div style="flex:1;min-width:140px;">
      <label class="help">単価</label>
      <input type="number" name="unit_price" value="<?php echo e(old('unit_price', $item->unit_price)); ?>" min="0" required oninput="recalc()">
    </div>

    <div style="flex:1;min-width:120px;">
      <label class="help">数量</label>
      <input type="number" name="quantity" value="<?php echo e(old('quantity', $item->quantity)); ?>" min="0" required oninput="recalc()">
    </div>

    <div style="flex:1;min-width:160px;">
      <label class="help">小計（自動計算）</label>
      <input type="number" id="calc_total" value="<?php echo e($item->unit_price * $item->quantity); ?>" readonly>
    </div>

    <div style="width:100%; display:flex; gap:12px; margin-top:8px;">
      <button class="btn">保存</button>
      <a class="btn ghost" href="<?php echo e(route('items.index')); ?>">キャンセル</a>
    </div>
  </form>

  <script>
    function recalc(){
      const up = document.querySelector('input[name="unit_price"]');
      const q  = document.querySelector('input[name="quantity"]');
      const out= document.getElementById('calc_total');
      const v = (parseInt(up.value||'0')||0) * (parseInt(q.value||'0')||0);
      out.value = v;
    }
  </script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app')->render(); ?>
