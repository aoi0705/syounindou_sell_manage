<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderImportRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderEmailParser;
use Illuminate\Support\Facades\DB;
use App\Support\AddressHelper;

class OrderImportController extends Controller
{
    public function create()
    {
        return view('orders.import');
    }

    public function store(OrderImportRequest $request, OrderEmailParser $parser)
    {
        $raw  = (string)$request->input('raw_body', '');
        $note = trim((string)$request->input('note', ''));

        // 受信メール本文をパース（実装は OrderEmailParser 側）
        $p = $parser->parse($raw);

        // 注文番号の重複チェック（ある場合のみ）
        if (!empty($p['order_no']) && Order::where('order_no', $p['order_no'])->exists()) {
            return back()
                ->withInput()
                ->withErrors(['raw_body' => '同じ注文番号（'.$p['order_no'].'）が既に登録されています。']);
        }

        // --- 住所/電話/メールの整形（ヘルパ利用） -----------------------
        // 購入者
        $buyerAddrSrc        = (string)($p['buyer']['address_full'] ?? '');
        $buyerAddressNormalized = AddressHelper::normalizePostalInAddress($buyerAddrSrc); // 〒1234567 → 〒123-4567
        $buyerPostal            = AddressHelper::extractPostalFromText($buyerAddrSrc);    // "270-1471" 等（列があれば保存可）
        $buyerTel               = AddressHelper::formatTel($p['buyer']['tel']    ?? '');
        $buyerMobile            = AddressHelper::formatTel($p['buyer']['mobile'] ?? '');
        $buyerEmail             = $p['buyer']['email'] ?? AddressHelper::extractEmail($raw);

        // お届け先
        $shipAddrSrc            = (string)($p['shipto']['address_full'] ?? '');
        $shipAddressNormalized  = AddressHelper::normalizePostalInAddress($shipAddrSrc);
        $shipPostal             = AddressHelper::extractPostalFromText($shipAddrSrc);
        $shipTel                = AddressHelper::formatTel($p['shipto']['tel'] ?? '');

        DB::beginTransaction();
        try {
            // 注文本体を作成
            $order = Order::create([
                'order_no'            => $p['order_no']           ?? null,
                'shop_name'           => $p['shop_name']          ?? null,
                'purchased_at'        => $p['purchased_at']       ?? null,   // モデル側で casts: datetime 推奨
                'purchased_at_text'   => $p['purchased_at_text']  ?? null,
                'payment_method'      => $p['payment_method']     ?? null,

                'subtotal'            => $p['totals']['subtotal'] ?? 0,
                'shipping_fee'        => $p['totals']['shipping'] ?? 0,
                'cool_fee'            => $p['totals']['cool']     ?? 0,
                'total'               => $p['totals']['total']    ?? 0,
                'tax10'               => $p['totals']['tax10']    ?? 0,
                'tax8'                => $p['totals']['tax8']     ?? 0,

                'ship_carrier'        => $p['shipping']['carrier']      ?? null,
                'ship_date_request'   => $p['shipping']['date_request'] ?? null,
                'ship_time_window'    => $p['shipping']['time_window']  ?? null,

                'buyer_name'          => $p['buyer']['name'] ?? null,
                'buyer_kana'          => $p['buyer']['kana'] ?? null,
                'buyer_address_full'  => $buyerAddressNormalized, // 〒ハイフンを正規化した住所全文
                'buyer_tel'           => $buyerTel ?: null,
                'buyer_mobile'        => $buyerMobile ?: null,
                'buyer_email'         => $buyerEmail ?: null,
                // 'buyer_postal'      => $buyerPostal, // ← カラムがあるなら有効化

                'shipto_name'         => $p['shipto']['name'] ?? null,
                'shipto_kana'         => $p['shipto']['kana'] ?? null,
                'shipto_address_full' => $shipAddressNormalized,
                'shipto_tel'          => $shipTel ?: null,
                // 'shipto_postal'     => $shipPostal, // ← カラムがあるなら有効化

                'mail_preference'     => $p['mail_preference'] ?? null,
                'is_shipped'          => false,
                'is_gift'             => false,

                'raw_body'            => $raw,   // 原文も保持
                'note'                => $note,  // フォームの備考
            ]);

            // 明細
            foreach ((array)($p['items'] ?? []) as $it) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'sku'        => $it['sku']        ?? null,
                    'name'       => $it['name']       ?? '',
                    'unit_price' => (int)($it['unit_price'] ?? 0),
                    'quantity'   => (int)($it['quantity']   ?? 0),
                    'line_total' => (int)($it['line_total'] ?? 0),
                    'reduced_tax'=> (bool)($it['reduced_tax'] ?? false),
                ]);
            }

            DB::commit();

            return redirect()
                ->route('orders.show', $order)
                ->with('status', '注文を取り込みました');

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()
                ->withInput()
                ->withErrors(['raw_body' => '保存時にエラーが発生しました。: '.$e->getMessage()]);
        }
    }

    public function show(Order $order)
    {
        $order->load('items');
        return view('orders.show', compact('order'));
    }

    private function nk(string $s): string
    {
        // a:英数, s:スペース, n:数字, k:片仮名半→全 などは用途に応じて
        return mb_convert_kana($s, 'as', 'UTF-8');
    }

    /** 数字だけを取り出す */
    private function digits(string $s): string
    {
        return preg_replace('/\D+/', '', $this->nk($s));
    }

    /** 郵便番号 7桁 → 123-4567 （7桁以外は変更しない） */
    private function formatPostal(?string $s): ?string
    {
        if ($s === null || $s === '') return $s;
        $d = $this->digits($s);
        if (strlen($d) === 7) return substr($d, 0, 3) . '-' . substr($d, 3);
        return $this->nk($s); // 既にハイフンあり/桁不足などはそのまま
    }

    /** 文章中の「〒1234567」を「〒123-4567」に直す（住所全文向け） */
    private function formatPostalInText(?string $text): ?string
    {
        if ($text === null) return null;
        $t = $this->nk($text);
        return preg_replace_callback('/(〒\s*)(\d{7})(?!-)/u', function($m){
            return $m[1] . substr($m[2],0,3) . '-' . substr($m[2],3);
        }, $t);
    }

    /** 日本の電話番号を可能な範囲で整形（桁不足はそのまま返す） */
    private function formatTel(?string $s): ?string
    {
        if ($s === null || $s === '') return $s;
        $d = $this->digits($s);

        // 特番/フリーダイヤル/ナビダイヤルなど
        if (preg_match('/^(0120|0800|0570|0990)(\d{3})(\d{3})$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
        // IP電話
        if (preg_match('/^(050)(\d{4})(\d{4})$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
        // 携帯
        if (preg_match('/^(070|080|090)(\d{4})(\d{4})$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
        // 11桁（予備）
        if (strlen($d) === 11) return substr($d,0,3) . '-' . substr($d,3,4) . '-' . substr($d,7);
        // 固定 03/06 は 2-4-4、それ以外10桁は 3-3-4（簡易ルール）
        if (strlen($d) === 10) {
            if (preg_match('/^(0[36])(\d{4})(\d{4})$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
            return substr($d,0,3) . '-' . substr($d,3,3) . '-' . substr($d,6);
        }

        // 桁不足や想定外は元の（半角化のみ）を返す
        return $this->nk($s);
    }
}
