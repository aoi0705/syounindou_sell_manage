<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function edit(Request $request)
    {
        // 既定テンプレ（日本語プレースホルダ）を初期投入
        $tpl = EmailTemplate::firstOrCreate(
            ['key' => 'shipping_completed'],
            [
                'subject'    => '【発送完了】ご注文 {{注文番号}} のお知らせ',
                'body_md'    => $this->defaultMarkdown(),
                'updated_by' => $request->user()->name ?? 'system',
            ]
        );

        // もし古い Blade 形式（{{ $order->... }} / @foreach ...）が残っていれば自動で置換
        if ($this->looksBladeStyle($tpl->subject) || $this->looksBladeStyle($tpl->body_md)) {
            $tpl->subject = $this->convertBladeToJP($tpl->subject);
            $tpl->body_md = $this->convertBladeToJP($tpl->body_md);
            $tpl->save();
        }

        return view('settings.mail', [
            'template' => $tpl,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required','string','max:255'],
            'body_md' => ['required','string'],
        ],[],[
            'subject' => '件名',
            'body_md' => '本文',
        ]);

        $tpl = EmailTemplate::where('key','shipping_completed')->firstOrFail();
        $tpl->fill($data);
        $tpl->updated_by = $request->user()->name ?? 'admin';
        $tpl->save();

        return back()->with('status', '発送完了メールのテンプレートを保存しました。');
    }

    private function defaultMarkdown(): string
    {
        return <<<'MD'
# 発送完了のお知らせ

{{購入者氏名}} 様

ご注文 **{{注文番号}}** の商品を発送いたしました。

## お届け先
- 氏名：{{お届け先氏名}}
- 住所：{{お届け先住所}}
- 電話：{{お届け先電話}}

## 配送情報
- 配送業者：{{配送業者}}
- 希望日：{{配送希望日}}
- 時間帯：{{配送希望時間帯}}

## 発送商品
| SKU | 商品名 | 数量 | 追跡番号 |
|:---:|:--|:--:|:--|
{{#明細}}
| {{SKU}} | {{商品名}} | {{数量}} | {{追跡番号}} |
{{/明細}}

お荷物の到着まで今しばらくお待ちください。
{{アプリ名}}
MD;
    }

    /** Blade っぽい表記が混じっているか簡易判定 */
    private function looksBladeStyle(string $text): bool
    {
        return str_contains($text, '$order->') || str_contains($text, '$it->') || str_contains($text, '@foreach');
    }

    /** 旧Blade記法 → 日本語プレースホルダ へざっくり変換 */
    private function convertBladeToJP(string $text): string
    {
        // 明細ループ
        $text = preg_replace('/@foreach\s*\(\s*\$items\s+as\s+\$it\s*\)/', '{{#明細}}', $text);
        $text = preg_replace('/@endforeach/', '{{/明細}}', $text);

        // アイテム側
        $reItem = [
            '/\{\{\s*\$it->sku[^}]*\}\}/'        => '{{SKU}}',
            '/\{\{\s*\$it->name[^}]*\}\}/'       => '{{商品名}}',
            '/\{\{\s*\$it->quantity[^}]*\}\}/'   => '{{数量}}',
            '/\{\{\s*\$it->tracking_no[^}]*\}\}/'=> '{{追跡番号}}',
            '/\{\{\s*\$it->unit_price[^}]*\}\}/' => '{{単価}}',
            '/\{\{\s*\$it->line_total[^}]*\}\}/' => '{{小計}}',
        ];
        $text = preg_replace(array_keys($reItem), array_values($reItem), $text);

        // 注文側
        $reOrder = [
            '/\{\{\s*\$order->order_no[^}]*\}\}/'          => '{{注文番号}}',
            '/\{\{\s*\$order->buyer_name[^}]*\}\}/'        => '{{購入者氏名}}',
            '/\{\{\s*\$order->shipto_name[^}]*\}\}/'       => '{{お届け先氏名}}',
            '/\{\{\s*\$order->shipto_address_full[^}]*\}\}/' => '{{お届け先住所}}',
            '/\{\{\s*\$order->shipto_tel[^}]*\}\}/'        => '{{お届け先電話}}',
            '/\{\{\s*\$order->ship_carrier[^}]*\}\}/'      => '{{配送業者}}',
            '/\{\{\s*\$order->ship_date_request[^}]*\}\}/' => '{{配送希望日}}',
            '/\{\{\s*\$order->ship_time_window[^}]*\}\}/'  => '{{配送希望時間帯}}',
        ];
        $text = preg_replace(array_keys($reOrder), array_values($reOrder), $text);

        // アプリ名
        $text = preg_replace('/\{\{\s*config\(\s*[\'"]app\.name[\'"]\s*\)\s*\}\}/', '{{アプリ名}}', $text);

        // 余った Blade の {{ ... }} はそのまま残しておく（ユーザーが見つけやすいように）
        return $text;
    }
}
