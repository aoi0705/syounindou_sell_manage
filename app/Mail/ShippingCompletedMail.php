<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\TemplateEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ShippingCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;
    /** @var OrderItem[] */
    public array $items;

    public function __construct(Order $order, array $items)
    {
        $this->order = $order;
        $this->items = $items;
    }

    public function build()
    {
        $tpl = EmailTemplate::where('key', 'shipping_completed')->first();

        // テンプレがあれば：日本語プレースホルダ → Markdown → HTML
        if ($tpl) {
            $subjectRaw = TemplateEngine::render($tpl->subject,  $this->order, $this->items);
            $bodyMd     = TemplateEngine::render($tpl->body_md,  $this->order, $this->items);
            $html       = Str::markdown($bodyMd);

            return $this->subject($subjectRaw)->html($html);
        }

        // フォールバック：簡易定型
        $fallbackMd = TemplateEngine::render(<<<'MD'
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

{{アプリ名}}
MD, $this->order, $this->items);

        return $this->subject('【発送完了】ご注文 ' . ($this->order->order_no ?? '—') . ' のお知らせ')
            ->html(Str::markdown($fallbackMd));
    }
}
