<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class BankTransferParser
{
    public function parse(string $raw): array
    {
        $text  = $this->normalize($raw);
        $lines = preg_split('/\R/u', $text);

        $l1 = $lines[0] ?? '';
        $l2 = $lines[1] ?? '';
        $l4 = $lines[3] ?? '';
        $l5 = $lines[4] ?? '';
        $l6 = $lines[5] ?? '';

        // 1行目: 先頭3文字を空文字に（別コード除去）
        $transferText = trim(mb_substr($l1, 3));

        // 2行目: （と）の間、スペース、, を空文字に
        $amountLine = preg_replace('/（.*?）/u', '', $l2);
        $amountLine = preg_replace('/[ ,\t　]/u', '', $amountLine);
        $amount     = (int) preg_replace('/\D/u', '', $amountLine);

        // 4,5,6行目
        $bank   = trim($l4);
        $branch = trim($l5);
        $payer  = trim($l6);

        // 日付パース（例: 2024年04月03日）
        $transferAt = null;
        if ($transferText !== '') {
            try {
                $dt = CarbonImmutable::createFromFormat(
                    'Y年m月d日',
                    $transferText,
                    config('app.timezone', 'Asia/Tokyo')
                );
                if ($dt) {
                    $transferAt = $dt->startOfDay()->toDateTimeString();
                }
            } catch (\Throwable $e) {
                $transferAt = null;
            }
        }

        return [
            'transfer_at_text' => $transferText ?: null,
            'transfer_at'      => $transferAt,
            'amount'           => $amount,
            'bank_name'        => $bank ?: null,
            'branch_name'      => $branch ?: null,
            'payer_name'       => $payer ?: null,
        ];
    }

    private function normalize(string $s): string
    {
        $enc = mb_detect_encoding($s, ['UTF-8','SJIS-win','CP932','EUC-JP','ISO-2022-JP','JIS','ASCII'], true) ?: 'UTF-8';
        if ($enc !== 'UTF-8') $s = mb_convert_encoding($s, 'UTF-8', $enc);
        $s = iconv('UTF-8', 'UTF-8//IGNORE', $s);
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        return $s;
    }
}
