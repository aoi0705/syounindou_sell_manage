<?php

return [
    // 収入計上の基準（初期値）
    // - order_date : 注文日ベース
    // - labeled    : 送り状発行済みを計上（items.status >= 1）
    // - shipped    : 発送済みを計上      （items.status >= 2）
    'basis' => 'order_date',

    // 合計に送料・クール料金を含めるか（売上高として扱うか）
    'include_shipping_in_total' => true,
    'include_cool_in_total'     => true,

    // エクスポート列の順序（Excel側の列順に合わせて調整可）
    'columns' => ['日付','注文番号','購入者','支払方法','小計','送料','クール','税10','税8','合計','備考'],
];
