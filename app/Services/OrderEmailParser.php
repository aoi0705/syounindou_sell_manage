<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class OrderEmailParser
{
    public function parse(string $raw): array
    {
        $text = $this->normalize($raw);

        // 1) ヘッダ系（行の最初のヒットのみ）
        $shopName      = $this->lineValue($text, 'ご利用店');
        $purchasedLine = $this->lineValue($text, 'ご購入日');       // 次の改行まで
        $orderNo       = $this->lineValue($text, '注文番号');       // 次の改行まで

        // 2) 金額（円・, を除去）
        $subtotal = $this->yenToInt($this->lineValue($text, '小 計'));
        $shipping = $this->yenToInt($this->lineValue($text, '送 料'));
        $cool     = $this->yenToInt($this->lineValue($text, 'クール料金'));
        $total    = $this->yenToInt($this->lineValue($text, '合 計'));
        $tax10    = $this->yenToInt($this->lineValueRegex($text, '/^\(10%対象\)\s*[:：]\s*(.+)$/mu'));
        $tax8     = $this->yenToInt($this->lineValueRegex($text, '/^\(\s*8%対象\)\s*[:：]\s*(.+)$/mu'));

        // 3) 支払方法：「■お支払方法\n------------------------\n」の次の行
        $payment = $this->blockFirstLine($text, '■お支払方法', '■購入者情報');

        // 4) 購入者ブロック
        $buyerBlock = $this->blockBetweenWithRule($text, '■購入者情報', '■お届け先情報');
        $buyer = [
            'name'          => $this->lineValue($buyerBlock, 'お名前'),
            'kana'          => $this->lineValue($buyerBlock, 'フリガナ'),
            'address_full'  => $this->addressBetweenTel($buyerBlock), // ご住所 : 〜 \nTEL : まで
            'tel'           => $this->lineValue($buyerBlock, 'TEL'),
            'mobile'        => $this->lineValue($buyerBlock, '携帯番号'),
            'email'         => $this->nextLineAfterLabel($buyerBlock, 'メールアドレス'),
        ];

        // 5) お届け先ブロック
        $shiptoBlock = $this->blockBetweenWithRule($text, '■お届け先情報', '■配送について');
        $shipto = [
            'name'          => $this->lineValue($shiptoBlock, 'お名前'),
            'kana'          => $this->lineValue($shiptoBlock, 'フリガナ'),
            'address_full'  => $this->addressBetweenTel($shiptoBlock),
            'tel'           => $this->lineValue($shiptoBlock, 'TEL'),
        ];

        // 6) 配送ブロック
        $shipBlock = $this->blockBetween($text, '■配送について', '■当店からのメールについて');
        $carrier   = $this->afterBracketHeader($shipBlock, '[配送便]');
        $dateReq   = $this->lineValue($shipBlock, '配送希望日');
        $timeWin   = $this->lineValue($shipBlock, '配送希望時間帯');

        // 7) メール受信有無
        $mailPref  = $this->blockFirstLine($text, '■当店からのメールについて', '■その他');

        // 8) 注文情報（商品明細）
        $items = $this->parseItemsByRule($text);

        // 9) 購入日時はテキスト保持＋パースできたら datetime
        $purchasedAtText = $purchasedLine;
        $purchasedAt = null;
        if ($purchasedAtText) {
            try {
                $purchasedAt = CarbonImmutable::parse($purchasedAtText)->toDateTimeString();
            } catch (\Throwable $e) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchasedAtText)) {
                    $purchasedAt = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $purchasedAtText.' 00:00:00')->toDateTimeString();
                }
            }
        }

        return [
            'shop_name'         => $shopName,
            'purchased_at_text' => $purchasedAtText,
            'purchased_at'      => $purchasedAt,
            'order_no'          => $orderNo,
            'payment_method'    => $payment,
            'totals' => [
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'cool'     => $cool,
                'total'    => $total,
                'tax10'    => $tax10,
                'tax8'     => $tax8,
            ],
            'buyer' => $buyer,
            'shipto' => $shipto,
            'shipping' => [
                'carrier'      => $carrier,
                'date_request' => $dateReq,
                'time_window'  => $timeWin,
            ],
            'mail_preference' => $mailPref,
            'items' => $items,
        ];
    }

    // ===== ヘルパ =====

    private function normalize(string $s): string
    {
        $enc = mb_detect_encoding($s, ['UTF-8','SJIS-win','CP932','EUC-JP','ISO-2022-JP','JIS','ASCII'], true) ?: 'UTF-8';
        if ($enc !== 'UTF-8') {
            $s = mb_convert_encoding($s, 'UTF-8', $enc);
        }
        $s = iconv('UTF-8', 'UTF-8//IGNORE', $s);
        $s = str_replace(["\r\n", "\r"], "\n", $s);                 // 改行は保持
        $s = preg_replace('/[\x{3000}\t]+/u', ' ', $s);            // 全角空白/タブ→半角空白
        $s = preg_replace('/[\x00-\x09\x0B-\x0C\x0E-\x1F\x7F]/u', '', $s); // \n 以外の制御を除去
        return $s;
    }

    private function lineValue(string $text, string $label): ?string
    {
        if (preg_match('/^'.preg_quote($label,'/').'\s*[:：]\s*(.+)$/mu', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function lineValueRegex(string $text, string $pattern): ?string
    {
        if (preg_match($pattern, $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function blockFirstLine(string $text, string $header, string $nextHeader): ?string
    {
        $block = $this->blockBetweenWithRule($text, $header, $nextHeader);
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^[-ー—]+$/u', $line)) continue;
            return $line;
        }
        return null;
    }

    private function blockBetweenWithRule(string $text, string $startHeader, string $endHeader): string
    {
        $pat = '/^'.preg_quote($startHeader,'/').'\s*$/mu';
        if (!preg_match($pat, $text, $m, PREG_OFFSET_CAPTURE)) return '';
        $startPos = $m[0][1];
        $rest = substr($text, $startPos);
        $rest = preg_replace('/^'.preg_quote($startHeader,'/').'\s*\n[-ー—]+\s*\n/u', '', $rest, 1);

        $endPat = '/^'.preg_quote($endHeader,'/').'\s*$/mu';
        if (preg_match($endPat, $rest, $m2, PREG_OFFSET_CAPTURE)) {
            return trim(substr($rest, 0, $m2[0][1]));
        }
        return trim($rest);
    }

    private function blockBetween(string $text, string $startHeader, string $endHeader): string
    {
        $pat = '/^'.preg_quote($startHeader,'/').'\s*$/mu';
        if (!preg_match($pat, $text, $m, PREG_OFFSET_CAPTURE)) return '';
        $startPos = $m[0][1] + strlen($m[0][0]);
        $rest = substr($text, $startPos);

        $endPat = '/^'.preg_quote($endHeader,'/').'\s*$/mu';
        if (preg_match($endPat, $rest, $m2, PREG_OFFSET_CAPTURE)) {
            return trim(substr($rest, 0, $m2[0][1]));
        }
        return trim($rest);
    }

    private function afterBracketHeader(string $block, string $header): ?string
    {
        $pat = '/^\['.preg_quote(trim($header,'[]'),'/').'\]\s*\n([^\n]*)/mu';
        if (preg_match($pat, $block, $m)) return trim($m[1]);
        return null;
    }

    private function addressBetweenTel(string $block): ?string
    {
        if (!preg_match('/^ご住所\s*[:：]\s*/mu', $block, $m, PREG_OFFSET_CAPTURE)) return null;
        $start = $m[0][1];
        $sub   = substr($block, $start);
        $sub   = preg_replace('/^ご住所\s*[:：]\s*/u', '', $sub, 1);
        $endPos = mb_strpos($sub, "\nTEL");
        if ($endPos === false) {
            $parts = preg_split("/\n{2,}/u", $sub, 2);
            $addr = $parts[0] ?? $sub;
        } else {
            $addr = mb_substr($sub, 0, $endPos);
        }
        return trim($addr);
    }

    private function nextLineAfterLabel(string $block, string $label): ?string
    {
        $pat = '/^'.preg_quote($label,'/').'\s*[:：]\s*$/mu';
        if (preg_match($pat, $block, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            $rest = substr($block, $pos);
            $lines = explode("\n", ltrim($rest, "\n"));
            return isset($lines[0]) ? trim($lines[0]) : null;
        }
        $inline = $this->lineValue($block, $label);
        return $inline ? trim($inline) : null;
    }

    private function yenToInt(?string $s): int
    {
        if ($s === null) return 0;
        $s = preg_replace('/[円,]/u', '', $s);
        $s = preg_replace('/\s+/u', '', $s);
        return $s === '' ? 0 : (int)$s;
    }

    /**
     * 注文情報（商品明細）を抽出
     * 仕様:
     * - 「注文番号 :」がある行の 3 行下から開始
     * - 「==========」→「小 計 :」が出る直前まで
     * - 先頭5桁の数字で始まる行が商品名行
     *   - 先頭5桁 = 商品番号
     *   - 半角スペース以降 = 商品名（行末まで）
     * - 次の1行が「単価円×個数=小計円」
     * - 各商品は空行で区切られることが多い（なくても2行ペアで認識）
     */
    private function parseItemsByRule(string $text): array
    {
        $lines = explode("\n", $text);

        // 「注文番号 :」行のインデックスを探す
        $startIdx = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^注文番号\s*[:：]/u', trim($line))) {
                $startIdx = $i + 3; // 3行下から
                break;
            }
        }
        if ($startIdx === null || $startIdx >= count($lines)) {
            return [];
        }

        // 終端（========== の行 または 「小 計 :」行）のインデックスを探す
        $endIdx = count($lines) - 1;
        for ($i = $startIdx; $i < count($lines); $i++) {
            $t = trim($lines[$i]);
            if ($t === '==========') {    // 直後に金額セクションが来る
                $endIdx = $i - 1;
                break;
            }
            if (preg_match('/^小\s*計\s*[:：]/u', $t)) {
                $endIdx = $i - 1;
                break;
            }
        }

        $items = [];
        $i = $startIdx;
        while ($i <= $endIdx) {
            $line = trim($lines[$i]);

            // 5桁数字で始まる商品行
            if (preg_match('/^(?<code>\d{5})\s+(?<name>.+)$/u', $line, $m)) {
                $code = $m['code'];
                $name = trim($m['name']);

                // 次行: 価格×数量=小計
                $priceLine = isset($lines[$i+1]) ? trim($lines[$i+1]) : '';
                if (preg_match('/(?<price>[\d,]+)\s*円\s*×\s*(?<qty>\d+)\s*=\s*(?<sum>[\d,]+)\s*円/u', $priceLine, $m2)) {
                    $unit = (int) preg_replace('/[^\d]/', '', $m2['price']);
                    $qty  = (int) $m2['qty'];
                    $sum  = (int) preg_replace('/[^\d]/', '', $m2['sum']);

                    $items[] = [
                        'sku'        => $code,
                        'name'       => $name,
                        'unit_price' => $unit,
                        'quantity'   => $qty,
                        'line_total' => $sum,
                    ];

                    // 2行消費＋（空行があれば）スキップ
                    $i += 2;
                    while ($i <= $endIdx && trim($lines[$i]) === '') { $i++; }
                    continue;
                } else {
                    // 価格行が想定外でも、とりあえず商品だけ記録（単価/数量/合計は 0）
                    $items[] = [
                        'sku'        => $code,
                        'name'       => $name,
                        'unit_price' => 0,
                        'quantity'   => 0,
                        'line_total' => 0,
                    ];
                    $i++;
                    continue;
                }
            }

            $i++;
        }

        return $items;
    }
}
