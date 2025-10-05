<?php /** @var \App\Models\Order $order */ ?>
<?php $__env->startSection('content'); ?>
  <h1 style="margin:0 0 12px;">注文編集</h1>
  <p class="help" style="margin-top:0;">注文 #<?php echo e($order->order_no); ?> / <?php echo e($order->purchased_at_text ?? optional($order->purchased_at)->format('Y-m-d H:i')); ?></p>

  <form method="post" action="<?php echo e(route('orders.update', $order)); ?>" class="row" style="gap:16px;">
    <?php echo method_field('patch'); ?>
    <?php echo csrf_field(); ?>

    <div style="flex:1;min-width:260px;">
      <label class="help">購入者氏名</label>
      <input type="text" name="buyer_name" value="<?php echo e(old('buyer_name', $order->buyer_name)); ?>">
    </div>
    <div style="flex:1;min-width:260px;">
      <label class="help">購入者カナ</label>
      <input type="text" name="buyer_kana" value="<?php echo e(old('buyer_kana', $order->buyer_kana)); ?>">
    </div>
    <div style="width:100%;">
      <label class="help">購入者住所</label>
      <textarea name="buyer_address_full" style="min-height:80px;"><?php echo e(old('buyer_address_full', $order->buyer_address_full)); ?></textarea>
    </div>
    <div style="flex:1;min-width:180px;">
      <label class="help">TEL</label>
      <input type="text" name="buyer_tel" value="<?php echo e(old('buyer_tel', $order->buyer_tel)); ?>">
    </div>
    <div style="flex:1;min-width:180px;">
      <label class="help">携帯</label>
      <input type="text" name="buyer_mobile" value="<?php echo e(old('buyer_mobile', $order->buyer_mobile)); ?>">
    </div>
    <div style="flex:1;min-width:260px;">
      <label class="help">Email</label>
      <input type="text" name="buyer_email" value="<?php echo e(old('buyer_email', $order->buyer_email)); ?>">
    </div>

    <div style="flex:1;min-width:260px;">
      <label class="help">お届け先氏名</label>
      <input type="text" name="shipto_name" value="<?php echo e(old('shipto_name', $order->shipto_name)); ?>">
    </div>
    <div style="flex:1;min-width:260px;">
      <label class="help">お届け先カナ</label>
      <input type="text" name="shipto_kana" value="<?php echo e(old('shipto_kana', $order->shipto_kana)); ?>">
    </div>
    <div style="width:100%;">
      <label class="help">お届け先住所</label>
      <textarea name="shipto_address_full" style="min-height:80px;"><?php echo e(old('shipto_address_full', $order->shipto_address_full)); ?></textarea>
    </div>
    <div style="flex:1;min-width:180px;">
      <label class="help">お届け先TEL</label>
      <input type="text" name="shipto_tel" value="<?php echo e(old('shipto_tel', $order->shipto_tel)); ?>">
    </div>

    <div style="flex:1;min-width:220px;">
      <label class="help">支払方法</label>
      <input type="text" name="payment_method" value="<?php echo e(old('payment_method', $order->payment_method)); ?>">
    </div>
    <div style="flex:1;min-width:220px;">
      <label class="help">配送便</label>
      <input type="text" name="ship_carrier" value="<?php echo e(old('ship_carrier', $order->ship_carrier)); ?>">
    </div>
    <div style="flex:1;min-width:180px;">
      <label class="help">配送希望日</label>
      <input type="text" name="ship_date_request" value="<?php echo e(old('ship_date_request', $order->ship_date_request)); ?>">
    </div>
    <div style="flex:1;min-width:180px;">
      <label class="help">配送希望時間帯</label>
      <input type="text" name="ship_time_window" value="<?php echo e(old('ship_time_window', $order->ship_time_window)); ?>">
    </div>
    <div style="flex:1;min-width:220px;">
      <label class="help">メール受信可否</label>
      <input type="text" name="mail_preference" value="<?php echo e(old('mail_preference', $order->mail_preference)); ?>">
    </div>

    <div style="width:100%; display:flex; gap:12px; margin-top:8px;">
      <button class="btn">保存</button>
      <a class="btn ghost" href="<?php echo e(route('orders.index')); ?>">キャンセル</a>
    </div>
  </form>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app')->render(); ?>
