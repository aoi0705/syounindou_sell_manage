<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;

class TemplateEngine
{
    /**
     * 日本語プレースホルダを展開して Markdown テキストを返す。
     * 例）{{注文番号}}、{{#明細}}...{{/明細}}
     */
    public static function render(string $text, Order $order, array $items): string
    {
        $text = str_replace("\r\n", "\n", $text);

        // 1) 明細セクション {{#明細}} ... {{/明細}}
        $text = preg_replace_callback('/\{\{#明細\}\}([\s\S]*?)\{\{\/明細\}\}/u', function ($m) use ($order, $items) {
            $rowTpl = $m[1];
            $out = [];
            foreach ($items as $it) {
                $out[] = self::replaceInline($rowTpl, self::itemMap($order, $it));
            }
            return implode('', $out);
        }, $text);

        // 2) 単発トークン（注文情報など）
        $text = self::replaceInline($text, self::orderMap($order));

        return $text;
    }

    /** 単発 {{...}} を置換（#明細, /明細 はスキップ） */
    private static function replaceInline(string $text, array $map): string
    {
        return preg_replace_callback('/\{\{([^#\/][^}]*)\}\}/u', function ($m) use ($map) {
            $key = trim($m[1]);
            return array_key_exists($key, $map) ? (string) $map[$key] : $m[0]; // 未定義はそのまま残す
        }, $text);
    }

    private static function safeString($v): string
    {
        $v = is_null($v) ? '' : (string) $v;
        $v = trim($v);
        return $v === '' ? '—' : $v;
    }

    /** 注文側プレースホルダ */
    private static function orderMap(Order $order): array
    {
        return [
            '注文番号'       => self::safeString($order->order_no),
            '購入者氏名'     => self::safeString($order->buyer_name),
            'お届け先氏名'   => self::safeString($order->shipto_name ?: $order->buyer_name),
            'お届け先住所'   => self::safeString($order->shipto_address_full),
            'お届け先電話'   => self::safeString($order->shipto_tel),
            '配送業者'       => self::safeString($order->ship_carrier),
            '配送希望日'     => self::safeString($order->ship_date_request),
            '配送希望時間帯' => self::safeString($order->ship_time_window),
            '店舗名'         => self::safeString($order->shop_name),
            '合計金額'       => number_format((int) ($order->total ?? 0)),
            'アプリ名'       => config('app.name'),
        ];
    }

    /** 明細行用（行内でも注文側トークンを使えるように合成） */
    private static function itemMap(Order $order, OrderItem $it): array
    {
        return [
            'SKU'     => self::safeString($it->sku),
            '商品名'   => self::safeString($it->name),
            '数量'     => (string) ($it->quantity ?? 0),
            '追跡番号' => self::safeString($it->tracking_no),
            '単価'     => number_format((int) ($it->unit_price ?? 0)),
            '小計'     => number_format((int) ($it->line_total ?? 0)),
        ] + self::orderMap($order);
    }
}
