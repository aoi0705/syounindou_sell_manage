<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\CarbonImmutable;

// Excel
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

use App\Support\AddressHelper; // 郵便番号抽出・住所整形（前に作ったヘルパ）

class LabelExportController extends Controller
{
    /** ラベル出力テンプレ（任意・無ければ空ブックで出力） */
    private const TEMPLATE_REL = 'templates/label_form.xlsx';

    /** 一覧画面：注文アイテム単位で選択 */
    public function index(Request $request)
    {
        $q = trim((string)$request->input('q',''));

        // アイテム一覧（最新順でページネーション）
        $items = OrderItem::query()
            ->with(['order:id,order_no,shipto_name,shipto_address_full,shipto_tel,ship_time_window,ship_date_request,payment_method'])
            ->pending()
            ->when($q !== '', function($w) use ($q) {
                $w->where('name','like',"%{$q}%")
                  ->orWhere('sku','like',"%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->appends($request->query());

        return view('labels.index', compact('items','q'));
    }

    /** エクスポート（選択アイテムを並び替えてExcel出力） */
    public function export(Request $request)
    {
        $data = $request->validate([
            'order_item_ids'   => ['required','array','min:1'],
            'order_item_ids.*' => ['integer','distinct'],
        ],[],['order_item_ids'=>'対象アイテム']);

        $items = OrderItem::query()
            ->with(['order']) // shipto系/支払方法など参照
            ->whereIn('id', $data['order_item_ids'])
            ->get();

        if ($items->isEmpty()) {
            return back()->withErrors(['order_item_ids'=>'有効なアイテムが選択されていません。']);
        }

        // 並び替え：配送希望日が近い順（NULL/「指定しない」は最後）→ created_at 昇順
        $tz   = config('app.timezone','Asia/Tokyo');
        $fmtYmd = 'Y-m-d';

        $sorted = $items->sortBy(function(OrderItem $it) use ($tz, $fmtYmd) {
            $o = $it->order;
            [$shipYmd, $shipSortKey] = $this->normalizeShipDate($o?->ship_date_request, $tz, $fmtYmd);
            $createdKey = optional($it->created_at)->format('Y-m-d H:i:s') ?? '9999-12-31 23:59:59';
            // 配送日が無いものは 9999-12-31 で最後に回す
            return [$shipSortKey, $createdKey];
        });

        // テンプレ読込 or 新規
        $templateAbs = Storage::disk('local')->path(self::TEMPLATE_REL);
        if (is_file($templateAbs)) {
            $spreadsheet = IOFactory::load($templateAbs);
            $ws = $spreadsheet->getActiveSheet();
        } else {
            $spreadsheet = new Spreadsheet();
            $ws = $spreadsheet->getActiveSheet();
            // 見出しを 19 行目に仮で置く（テンプレがある場合は不要）
            $ws->setCellValue([2,19], '作成日');
            $ws->setCellValue([3,19], '配送希望日');
            $ws->setCellValue([4,19], '時間帯');
            $ws->setCellValue([5,19], 'お届け先氏名');
            $ws->setCellValue([6,19], '郵便番号');
            $ws->setCellValue([7,19], '住所');
            $ws->setCellValue([8,19], '電話');
            $ws->setCellValue([12,19],'支払方法');
            $ws->setCellValue([14,19],'SKU');
            $ws->setCellValue([15,19],'品名');
            $ws->setCellValue([16,19],'数量');
            $ws->setCellValue([17,19],'金額');
        }

        // 書き込み：データ開始は 20 行目
        $row = 20;

        foreach ($sorted as $it) {
            $o = $it->order;

            // B: created_at (MM/DD)
            $created = optional($it->created_at)->timezone($tz);
            $ws->setCellValue([2, $row], $created ? $created->format('m/d') : '');

            // C: 配送希望日 (MM/DD) ※ ship_date_request が「指定しない/空」の場合は空欄
            [$shipDate, ] = $this->normalizeShipDate($o?->ship_date_request, $tz, $fmtYmd);
            $ws->setCellValue([3, $row], $shipDate ? $shipDate->format('m/d') : '');

            // D: 配送希望時間帯
            $ws->setCellValue([4, $row], (string)($o->ship_time_window ?? ''));

            // E: お届け先氏名
            $ws->setCellValue([5, $row], (string)($o->shipto_name ?? ''));

            // F: お届け先郵便番号（住所から抽出、無ければ空）
            $postal = AddressHelper::extractPostalFromText($o->shipto_address_full ?? '');
            $ws->setCellValue([6, $row], (string)($postal ?? ''));

            // G: お届け先住所（郵便番号は除去した本文）
            $addr = AddressHelper::addressWithoutPostal($o->shipto_address_full ?? '', trimEmptyLines:true);
            $ws->setCellValue([7, $row], (string)$addr);

            // H: お届け先電話番号（念のため整形）
            $tel = AddressHelper::formatTel($o->shipto_tel ?? '');
            $ws->setCellValue([8, $row], (string)($tel ?? ''));

            // L: 支払方法
            $ws->setCellValue([12, $row], (string)($o->payment_method ?? ''));

            // N: SKU
            $ws->setCellValue([14, $row], (string)($it->sku ?? ''));

            // O: アイテム名
            $ws->setCellValue([15, $row], (string)($it->name ?? ''));

            // P: 個数、Q: line_total
            $qty   = (int)($it->quantity ?? 0);
            $total = (int)($it->line_total ?? 0);

            $ws->setCellValue([16, $row], $qty);
            $ws->setCellValue([17, $row], $total);

            // 表示形式（数量は整数、金額は桁区切り）
            $ws->getStyle([16, $row])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $ws->getStyle([17, $row])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

            $row++;
        }

        // 保存
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
        $tmp = $tmpDir.'/labels_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.xlsx';

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($tmp);

        $filename = '送り状_'.date('Ymd_His').'.xlsx';

        $ids = $sorted->pluck('id')->all();

        // … 生成と保存が成功した後に …
        OrderItem::whereIn('id', $ids)->update([
            'status'          => \App\Models\OrderItem::STATUS_LABEL_ISSUED,
            'label_issued_at' => now(),
        ]);

        return response()->download($tmp, $filename)->deleteFileAfterSend(true);
    }

    /**
     * ship_date_request を日付に正規化
     * - 正常: CarbonImmutable を返す
     * - "指定しない" や空: null を返し、並び替え用キーは '9999-12-31'
     * - "YYYY/MM/DD" や "YYYY-MM-DD" に大体対応
     */
    private function normalizeShipDate(?string $raw, string $tz, string $fmtYmd): array
    {
        $t = trim((string)$raw);
        if ($t === '' || $t === '指定しない') {
            return [null, '9999-12-31'];
        }
        // 許容フォーマットをざっくり置換してからパース
        $t = str_replace(['年','月','日','.'], ['-','-','', '-'], $t);
        $t = preg_replace('/\//', '-', $t);

        try {
            $c = CarbonImmutable::parse($t, $tz);
            return [$c, $c->format($fmtYmd)];
        } catch (\Throwable $e) {
            return [null, '9999-12-31'];
        }
    }
}
