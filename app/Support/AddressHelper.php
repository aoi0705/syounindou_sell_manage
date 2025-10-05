<?php

namespace App\Support;

final class AddressHelper
{
    /* ===================== 基本ユーティリティ ===================== */

    /** 全角→半角（英数・スペース）+ 余計な前後空白の除去 */
    private static function nk(string $s): string
    {
        $t = mb_convert_kana($s, 'as', 'UTF-8');
        // CRLF/CR を LF に
        $t = preg_replace("/\r\n?/", "\n", $t);
        return trim($t);
    }

    /** 数字だけ取り出す */
    private static function digits(string $s): string
    {
        return preg_replace('/\D+/', '', $s);
    }

    /* ===================== 郵便番号 ===================== */

    /** テキストから郵便番号を抽出（〒 優先、無ければ最初の7桁を拾う）。ハイフン補完して返す。見つからなければ null。 */
    public static function extractPostalFromText(?string $text): ?string
    {
        if ($text === null || $text === '') return null;
        $t = self::nk($text);

        if (preg_match('/〒\s*(\d{3})-?(\d{4})/u', $t, $m)) {
            return $m[1] . '-' . $m[2];
        }
        if (preg_match('/郵便番号[:：]?\s*(\d{3})-?(\d{4})/u', $t, $m)) {
            return $m[1] . '-' . $m[2];
        }
        if (preg_match('/\b(\d{3})-?(\d{4})\b/u', $t, $m)) {
            return $m[1] . '-' . $m[2];
        }
        return null;
    }

    /** 住所内の「〒1234567 / 〒123-4567」を「〒123-4567」に正規化（他はそのまま） */
    public static function normalizePostalInAddress(string $text): string
    {
        $t = self::nk($text);
        return preg_replace('/〒\s*(\d{3})-?(\d{4})/u', '〒$1-$2', $t);
    }

    /** 郵便番号部分だけを住所から取り除いたテキストを返す（行ごと/行内両方の表記に対応） */
    public static function addressWithoutPostal(string $text, bool $trimEmptyLines = true): string
    {
        // まずは正規化
        $t = self::normalizePostalInAddress($text);

        $lines = preg_split("/\n/u", $t);
        $out = [];
        foreach ($lines as $line) {
            $L = trim($line);
            // 「〒123-4567」だけの行は丸ごと除去
            if ($L === '' || preg_match('/^〒\s*\d{3}-\d{4}$/u', $L)) {
                if (!$trimEmptyLines) $out[] = '';
                continue;
            }
            // 行内に混在している場合は削る（先頭/途中どちらも）
            $L = preg_replace('/〒\s*\d{3}-\d{4}\s*/u', '', $L);
            // 「郵便番号:」系の先頭に付く表記も除去
            $L = preg_replace('/^郵便番号[:：]?\s*/u', '', $L);
            $out[] = $L;
        }

        // 余分な空行を詰める（必要なら）
        if ($trimEmptyLines) {
            $out = array_values(array_filter($out, fn($l) => trim($l) !== ''));
        }
        return implode("\n", $out);
    }

    /* ===================== 電話番号 ===================== */

    /** 日本の電話番号を整形（可能ならハイフン付与）。失敗時は半角化のみ返す。 */
    public static function formatTel(?string $s): ?string
    {
        if ($s === null || $s === '') return $s;
        $d = self::digits($s);

        // 特番/フリーダイヤル/ナビダイヤル
        if (preg_match('/^(0120|0800|0570|0990)(\d{3})(\d{3})$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
        // IP電話
        if (preg_match('/^(050)(\d{4})(\d{4})$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
        // 携帯
        if (preg_match('/^(070|080|090)(\d{4})(\d{4})$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
        // 11桁（予備）
        if (strlen($d) === 11) return substr($d,0,3).'-'.substr($d,3,4).'-'.substr($d,7);
        // 固定 03/06 = 2-4-4、それ以外10桁 = 3-3-4（簡易）
        if (strlen($d) === 10) {
            if (preg_match('/^(0[36])(\d{4})(\d{4})$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
            return substr($d,0,3).'-'.substr($d,3,3).'-'.substr($d,6);
        }
        return self::nk($s);
    }

    /** テキストから電話番号らしきものを広めに抽出し、重複除去して返す（整形済み） */
    public static function extractTelAll(string $text): array
    {
        $t = self::nk($text);
        // 数字とハイフンの塊を拾う（先頭0から）
        preg_match_all('/\b0[\d\-]{6,}\b/u', $t, $m);
        $cands = $m[0] ?? [];
        $out = [];
        foreach ($cands as $raw) {
            $fmt = self::formatTel($raw);
            if (preg_match('/\d{7,}/', $fmt)) $out[$fmt] = true; // 最低7桁以上
        }
        return array_values(array_keys($out));
    }

    /** 携帯/固定を推定しながら代表番号を返す */
    public static function extractMainTelAndMobile(string $text): array
    {
        $all = self::extractTelAll($text);
        $res = ['tel' => null, 'mobile' => null];
        foreach ($all as $n) {
            if ($res['mobile'] === null && preg_match('/^(070|080|090)-/', $n)) { $res['mobile'] = $n; continue; }
            if ($res['tel'] === null && !preg_match('/^(070|080|090)-/', $n))   { $res['tel'] = $n; }
            if ($res['tel'] && $res['mobile']) break;
        }
        if (!$res['tel'] && isset($all[0])) $res['tel'] = $all[0];
        if (!$res['mobile']) {
            foreach ($all as $n) if (preg_match('/^(070|080|090)-/', $n)) { $res['mobile'] = $n; break; }
        }
        return $res;
    }

    /* ===================== メール/氏名 ===================== */

    /** メールアドレスの最初の1件を抽出 */
    public static function extractEmail(?string $text): ?string
    {
        if (!$text) return null;
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text, $m)) {
            return $m[0];
        }
        return null;
    }

    /** 「お名前 :」「フリガナ:」の行から抽出（見つからなければ null） */
    public static function extractNameAndKana(string $text): array
    {
        $name = null; $kana = null;
        foreach (preg_split("/\n/u", self::nk($text)) as $line) {
            if ($name === null && preg_match('/^(?:お名前|氏名)\s*[:：]\s*(.+?)\s*(様)?$/u', $line, $m)) {
                $name = trim($m[1]);
            } elseif ($kana === null && preg_match('/^(?:フリガナ|ﾌﾘｶﾞﾅ)\s*[:：]\s*(.+?)\s*(様)?$/u', $line, $m)) {
                $kana = trim($m[1]);
            }
            if ($name && $kana) break;
        }
        return ['name' => $name, 'kana' => $kana];
    }

    /* ===================== 住所系 ===================== */

    /**
     * テキスト全体から「住所ブロック」を抽出して返す。
     * - 行頭に「ご住所/住所」がある行を起点に、TEL/携帯/メール/区切り線に遭遇するまで収集。
     * - 無ければ「〒」行を起点に近接2行を収集（郵便→都道府県→番地の典型3行）。
     */
    public static function extractAddressBlock(string $text): ?string
    {
        $lines = preg_split("/\n/u", self::nk($text));
        $n = count($lines);
        $idx = -1;
        for ($i=0; $i<$n; $i++) {
            if (preg_match('/^(?:ご?住所)\b/u', $lines[$i])) { $idx = $i; break; }
        }
        $parts = [];
        if ($idx >= 0) {
            for ($i=$idx; $i<$n; $i++) {
                $line = $lines[$i];
                if ($i === $idx) {
                    $line = preg_replace('/^.*?(ご?住所)\s*[:：]?\s*/u', '', $line);
                } else {
                    if (preg_match('/^(TEL|電話|携帯|メール|E-?mail|お名前|フリガナ|配送|■|▼|={3,}|-{3,})/ui', $line)) break;
                }
                $parts[] = $line;
            }
        } else {
            for ($i=0; $i<$n; $i++) {
                if (preg_match('/〒\s*\d{3}-?\d{4}/u', $lines[$i])) { $idx = $i; break; }
            }
            if ($idx >= 0) {
                $parts[] = $lines[$idx];
                if (isset($lines[$idx+1])) $parts[] = $lines[$idx+1];
                if (isset($lines[$idx+2])) $parts[] = $lines[$idx+2];
            }
        }
        if (!$parts) return null;
        // 連続スペースを1に
        $parts = array_map(fn($x)=>preg_replace('/\s{2,}/u',' ',trim($x)), $parts);
        return implode("\n", array_filter($parts, fn($x)=>$x!==''));
    }

    /** 都道府県/市区町村/以降に "だいたい" 分割（完全一致を保証しない簡易版） */
    public static function splitJapaneseAddress(string $addressWithoutPostal): array
    {
        $t = self::nk($addressWithoutPostal);
        $pref = null; $city = null; $rest = trim($t);

        // 都道府県を特定
        $pos = null;
        foreach (self::PREFS as $p) {
            $ppos = mb_strpos($t, $p);
            if ($ppos !== false && ($pos === null || $ppos < $pos)) {
                $pref = $p; $pos = $ppos;
            }
        }
        if ($pref !== null) {
            $after = trim(mb_substr($t, $pos + mb_strlen($pref)));
            if (preg_match('/^(.*?(?:市|区|郡|町|村))(.*)$/u', $after, $m)) {
                $city = trim($m[1]);
                $rest = trim($m[2]);
            } else {
                $city = null;
                $rest = trim($after);
            }
        }
        return ['pref'=>$pref, 'city'=>$city, 'rest'=>$rest];
    }

    /**
     * 住所ブロックから主要要素を一括抽出。
     * - postal: 例 "270-1471"
     * - normalized: 郵便番号を「〒123-4567」に正規化したブロック
     * - address_no_postal: 郵便番号を取り除いた住所（複数行のまま）
     * - pref/city/rest: 大まかな分割
     * - tel/mobile/email: 検出できた代表値
     */
    public static function parseAddressBundle(string $addressBlock): array
    {
        $normalized = self::normalizePostalInAddress($addressBlock);
        $postal = self::extractPostalFromText($normalized);
        $noPostal = self::addressWithoutPostal($normalized);
        $parts = self::splitJapaneseAddress($noPostal);
        $tm = self::extractMainTelAndMobile($addressBlock);
        $email = self::extractEmail($addressBlock);

        return [
            'postal'            => $postal,
            'normalized'        => $normalized,
            'address_no_postal' => $noPostal,
            'pref'              => $parts['pref'],
            'city'              => $parts['city'],
            'rest'              => $parts['rest'],
            'tel'               => $tm['tel'],
            'mobile'            => $tm['mobile'],
            'email'             => $email,
        ];
    }

    /* ===================== データ ===================== */

    /** 都道府県リスト */
    private const PREFS = [
        '北海道',
        '青森県','岩手県','宮城県','秋田県','山形県','福島県',
        '茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県',
        '新潟県','富山県','石川県','福井県','山梨県','長野県',
        '岐阜県','静岡県','愛知県','三重県',
        '滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県',
        '鳥取県','島根県','岡山県','広島県','山口県',
        '徳島県','香川県','愛媛県','高知県',
        '福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県',
        '沖縄県',
    ];
}
