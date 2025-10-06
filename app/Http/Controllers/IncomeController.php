<?php

namespace App\Http\Controllers;

use App\Services\IncomeService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use App\Models\Order;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class IncomeController extends Controller
{
    public function __construct(private IncomeService $svc) {}

    /** 月別ダッシュボード（直近12ヶ月） */
    public function index(Request $request)
    {
        $basis  = $request->query('basis', config('income.basis', 'order_date')); // 既存
        $mode   = $request->query('mode',  'any');                                 // 既存
        $months = (int)$request->query('months', 12);                              // 既存

        $summary = $this->svc->monthlySummary($months, $basis, $mode);

        // ★ 追加：ランキング用の入力（デフォルトは当月～当月）
        $tz = config('app.timezone','Asia/Tokyo');
        $thisMonth = now($tz)->format('Y-m');
        $rankStartYm = $request->query('rank_start', $thisMonth);
        $rankEndYm   = $request->query('rank_end',   $thisMonth);
        $rankLimit   = (int)$request->query('rank_limit', 50);

        $ranking = $this->svc->salesRanking($rankStartYm, $rankEndYm, 'order_date', 'any', $rankLimit);

        return view('income.index', compact(
            'summary','basis','mode',
            'rankStartYm','rankEndYm','rankLimit','ranking'
        ));
    }

    /** ある月の明細一覧 */
    public function show(Request $request, string $ym)
    {
        $basis = $request->query('basis', config('income.basis', 'order_date'));
        $mode  = $request->query('mode',  'any');

        $rows  = $this->svc->rowsForMonth($ym, $basis, $mode);
        $sum   = [
            'count' => count($rows),
            'total' => array_sum(array_column($rows, '合計')),
        ];

        return view('income.show', compact('ym','rows','sum','basis','mode'));
    }

    /** Excel用データ（JSON） */
    public function export(Request $request, string $ym)
    {
        $basis = $request->query('basis', config('income.basis', 'order_date'));
        $mode  = $request->query('mode',  'any');

        $rows  = $this->svc->rowsForMonth($ym, $basis, $mode);

        // 列順（Excelの列に合わせる想定。config で差し替え可）
        $columns = config('income.columns', ['日付','注文番号','購入者','支払方法','小計','送料','クール','税10','税8','合計','備考']);

        return response()->json([
            'ym'      => $ym,
            'basis'   => $basis,
            'mode'    => $mode,
            'columns' => $columns,
            'rows'    => array_map(function($r) use ($columns){
                return array_values(array_intersect_key($r, array_flip($columns)));
            }, $rows),
        ]);
    }


    public function exportXlsx(Request $request, string $ym)
    {
        // 1) 期間の解決（YYYY-MM）
        if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
            return back()->withErrors(['年月の形式が不正です（YYYY-MM）: '.$ym]);
        }
        $year  = (int)$m[1];
        $month = (int)$m[2];

        $tz    = config('app.timezone', 'Asia/Tokyo');
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $tz);
        $end   = $start->addMonths(1);

        // 2) DBから該当注文を取得（purchased_at または purchased_at_text で月を判定）
        $orders = Order::query()
            ->where(function($w) use ($start, $end, $year, $month) {
                $w->whereBetween('purchased_at', [$start, $end])
                  ->orWhere(function($q2) use ($year, $month) {
                      $prefix = sprintf('%04d-%02d', $year, $month);
                      $q2->whereNull('purchased_at')
                         ->where('purchased_at_text', 'like', $prefix.'%');
                  });
            })
            ->oldest()
            ->orderBy('purchased_at', 'asc')->orderBy('id','asc')
            ->get();

        // 5) テンプレ読込
        $template = storage_path('app/private/templates/income_form.xlsx');
        if (!is_file($template)) {
            return back()->withErrors(['テンプレートが見つかりません: '.$template]);
        }
        try {
            $spreadsheet = IOFactory::load($template);
        } catch (\Throwable $e) {
            return back()->withErrors(['テンプレート読込に失敗: '.$e->getMessage()]);
        }

        // 6) シート取得 & 書き込み開始行
        $sheet = $spreadsheet->getSheetByName('収支') ?: $spreadsheet->getActiveSheet();

        $income_arr = [];
        foreach ($orders as $o) {
            $year  = $o->created_at?->format('Y');       // 例: "2025"
            $month = $o->created_at?->format('m');       // 例: "03"
            $monthJa = $o->created_at?->format('m') . '月'; // 例: "03月"
            $monthJaNoPad = $o->created_at?->format('n') . '月'; // 例: "3月"

            $arr_key = $year . $month;

            if(isset($income_arr[$year][$monthJa])){
                $income_arr[$year][$monthJa] += $o->total;
            }else{
                $income_arr[$year][$monthJa] = $o->total;
            }
        }

        $row = 6;
        $already_year = [];
        foreach($income_arr as $key => $value){
            if(!in_array($key, $already_year)){
                $sheet->setCellValue([$row,1], $key);
                array_push($already_year, $key);

                $sheet->mergeCells('A'.$row.':A'.($row+5));
                $sheet->setCellValue([$row,2], "コスメ管理");
                $sheet->setCellValue([$row+1,2], "ふるさと");
                $sheet->setCellValue([$row+2,2], "ネット");
                $sheet->setCellValue([$row+3,2], "たね芋卸し");
                $sheet->setCellValue([$row+4,2], "委託アイエスリンク");
                $sheet->setCellValue([$row+5,2], "売上計");

                $sheet->setCellValue([$row-1,3], "4月");
                $sheet->setCellValue([$row-1,4], "5月");
                $sheet->setCellValue([$row-1,5], "6月");
                $sheet->setCellValue([$row-1,6], "7月");
                $sheet->setCellValue([$row-1,7], "8月");
                $sheet->setCellValue([$row-1,8], "9月");
                $sheet->setCellValue([$row-1,9], "10月");
                $sheet->setCellValue([$row-1,10], "11月");
                $sheet->setCellValue([$row-1,11], "12月");
                $sheet->setCellValue([$row-1,12], "1月");
                $sheet->setCellValue([$row-1,13], "2月");
                $sheet->setCellValue([$row-1,14], "3月");
                $sheet->setCellValue([$row-1,15], "total");
            }
            foreach($value as $k => $v){
                switch($k){
                    case "04月":
                        $sheet->setCellValue([$row,3], $v);
                        break;
                    case "05月":
                        $sheet->setCellValue([$row,4], $v);
                        break;
                    case "06月":
                        $sheet->setCellValue([$row,5], $v);
                        break;
                    case "07月":
                        $sheet->setCellValue([$row,6], $v);
                        break;
                    case "08月":
                        $sheet->setCellValue([$row,7], $v);
                        break;
                    case "09月":
                        $sheet->setCellValue([$row,8], $v);
                        break;
                    case "10月":
                        $sheet->setCellValue([$row,9], $v);
                        break;
                    case "11月":
                        $sheet->setCellValue([$row,10], $v);
                        break;
                    case "12月":
                        $sheet->setCellValue([$row,11], $v);
                        break;
                    case "01月":
                        $sheet->setCellValue([$row,12], $v);
                        break;
                    case "02月":
                        $sheet->setCellValue([$row,13], $v);
                        break;
                    case "03月":
                        $sheet->setCellValue([$row,14], $v);
                        break;
                }
            }
            $row += 7;
        }


        // 8) ダウンロード
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
        $filename = 'income_'.$ym.'.xlsx';
        $path = $tmpDir.'/'.$filename;

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(true);
        $writer->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function exportAllXlsx(\Illuminate\Http\Request $request)
    {
        // 1) 全期間の注文を古い順で取得
        $orders = Order::query()
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // 2) 収支テンプレ読込
        $template = storage_path('app/private/templates/income_form.xlsx');
        if (!is_file($template)) {
            return back()->withErrors(['テンプレートが見つかりません: '.$template]);
        }
        try {
            $spreadsheet = IOFactory::load($template);
        } catch (\Throwable $e) {
            return back()->withErrors(['テンプレート読込に失敗: '.$e->getMessage()]);
        }
        $sheet = $spreadsheet->getSheetByName('収支') ?: $spreadsheet->getActiveSheet();

        // 3) 年度×月で集計（created_at ベース）
        $income = [];
        foreach ($orders as $o) {
            $year     = $o->created_at?->format('Y');      // "2025"
            $monthKey = $o->created_at?->format('m').'月'; // "04月"〜"03月"
            if (!$year || !$monthKey) continue;
            $income[$year][$monthKey] = ($income[$year][$monthKey] ?? 0) + (int)($o->total ?? 0);
        }

        // 列の終端（15列目 = O列）
        $lastColIdx = 15;
        $lastColA1  = Coordinate::stringFromColumnIndex($lastColIdx);

        // 4) 転記 + 書式
        $row = 6; // ブロック開始行
        foreach ($income as $yyyy => $months) {

            // A列: 年を6行縦結合
            $sheet->mergeCells('A'.$row.':A'.($row + 5));
            $sheet->setCellValue([1, $row], $yyyy);

            // B列: 科目名（6行分）
            $labels = ['コスメ管理','ふるさと','ネット','たね芋卸し','委託アイエスリンク','売上計'];
            foreach ($labels as $i => $label) {
                $sheet->setCellValue([2, $row + $i], $label);
            }

            // 見出し（4月〜3月 + total）をブロック1行上に
            $headerRow = $row - 1;
            $headers   = ['4月','5月','6月','7月','8月','9月','10月','11月','12月','1月','2月','3月','total'];
            foreach ($headers as $j => $h) {
                // C列(3)から右に配置
                $sheet->setCellValue([3 + $j, $headerRow], $h);
            }

            // ★ 文字は全部太文字（この年ブロックの範囲を一括指定）
            //   A{headerRow} ～ O{row+5} を太字化
            $sheet->getStyle('A'.$headerRow.':'.$lastColA1.($row + 5))
                  ->getFont()->setBold(true);

            // 月 → 列マップ（C=3 から）
            $colMap = [
                '04月' => 3,  '05月' => 4,  '06月' => 5,  '07月' => 6,
                '08月' => 7,  '09月' => 8,  '10月' => 9,  '11月' => 10,
                '12月' => 11, '01月' => 12, '02月' => 13, '03月' => 14,
            ];

            // 月別金額 → コスメ管理行（= $row 行）に記入、右端(15列目)に合計
            $sum = 0;
            foreach ($months as $monthJa => $amount) {
                if (!isset($colMap[$monthJa])) continue;
                $sheet->setCellValue([$colMap[$monthJa], $row], (int)$amount);
                $sum += (int)$amount;
            }
            $sheet->setCellValue([15, $row], (int)$sum);

            // ★ データ部分のみ「\111,111」形式（C〜O の当該行に適用）
            //   Excelの表示形式としては  \\#,##0  を渡します（PHP文字列では '\\\\#,##0'）
            $dataRange = 'C'.$row.':'.$lastColA1.$row; // 例: C6:O6
            $sheet->getStyle($dataRange)->getNumberFormat()
                  ->setFormatCode('\\\\#,##0');

            // 次の年ブロックへ（6行 + ヘッダ1行ぶん進める）
            $row += 7;
        }

        // 5) ダウンロード
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
        $filename = 'income_ALL.xlsx';
        $path     = $tmpDir.'/'.$filename;

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(true);
        $writer->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }


}
