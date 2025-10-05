<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Support\AddressHelper;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Settings;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class PurchaseOrderController extends Controller
{
    /** storage/app/ 配下のテンプレ格納ディレクトリ */
    private const TEMPLATE_DIR = 'templates';
    /** 既定テンプレ名（見つからない場合は自動検出にフォールバック） */
    private const DEFAULT_TEMPLATE = 'order_form.xlsx';

    /**
     * 注文書発行画面
     */
    public function index(Request $request)
    {
        $q     = trim((string)$request->input('q', ''));
        $sort  = $request->input('sort', 'date_desc');
        $ym    = trim((string)$request->input('ym', ''));

        $year  = $request->integer('y');
        $month = $request->integer('m');
        if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
            [$year, $month] = array_map('intval', explode('-', $ym));
        }
        if ($year && ($year < 2000 || $year > 2100)) { $year = null; $month = null; }
        if ($month && ($month < 1 || $month > 12))   { $month = null; }

        $query = Order::query()->with('items');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('order_no', 'like', "%{$q}%")
                  ->orWhere('buyer_name', 'like', "%{$q}%")
                  ->orWhere('shop_name', 'like', "%{$q}%");
            });
        }

        if ($year) {
            $tz    = config('app.timezone', 'Asia/Tokyo');
            $start = CarbonImmutable::create($year, $month ?: 1, 1, 0, 0, 0, $tz);
            $end   = $month ? $start->addMonths(1) : $start->addYear();
            $query->where(function ($w) use ($start,$end,$year,$month) {
                $w->whereBetween('purchased_at', [$start, $end])
                  ->orWhere(function ($q2) use ($year,$month) {
                      if ($month) $q2->whereNull('purchased_at')->where('purchased_at_text','like',sprintf('%04d-%02d%%',$year,$month));
                      else        $q2->whereNull('purchased_at')->where('purchased_at_text','like',sprintf('%04d-%%',$year));
                  });
            });
        }

        switch ($sort) {
            case 'date_asc':   $query->orderBy('purchased_at', 'asc')->orderBy('id','asc'); break;
            case 'total_desc': $query->orderBy('total', 'desc'); break;
            default:           $query->orderBy('purchased_at', 'desc')->orderBy('id','desc'); break;
        }

        $orders = $query->paginate(20)->appends($request->query());

        // 実行プロセスの拡張状態をログ（診断用）
        $this->logZipDiag('po.index');

        // テンプレ解決（診断情報付き）
        [$templatePath, $checkedPaths, $candidates] = $this->resolveTemplatePathWithDiagnostics();
        $templateExists = $templatePath !== null;
        if (!$templateExists) {
            $this->logTemplateError(
                tag: 'index_template_not_found',
                checkedPaths: $checkedPaths,
                candidates: $candidates,
                extra: ['route' => 'po.index']
            );
        } else {
            Log::debug('[PO] template resolved (index)', [
                'route'   => 'po.index',
                'path'    => $templatePath,
                'cwd'     => getcwd(),
                'storage' => storage_path(),
                'env'     => env('PO_TEMPLATE', '(not set)'),
            ]);
        }

        return view('po.index', [
            'orders'        => $orders,
            'q'             => $q,
            'sort'          => $sort,
            'ym'            => $ym,
            'templateExists'=> $templateExists,
            'templatePath'  => $templatePath,
            'checkedPaths'  => $checkedPaths,
            'candidates'    => $candidates,
        ]);
    }

    /**
     * 注文書生成（Excel）
     */
    public function generate(Request $request)
    {
        // 実行前に必要拡張を確認
        if ($msg = $this->ensureSpreadsheetExtensions()) {
            return back()->withErrors([$msg]);
        }

        $data = $request->validate([
            'order_ids'      => ['required','array','min:1'],
            'order_ids.*'    => ['integer','distinct'],
            // ヘッダ項目
            'po_number'      => ['nullable','string','max:100'],
            'po_date'        => ['nullable','string','max:50'],
            'vendor_name'    => ['required','string','max:255'],
            'vendor_address' => ['nullable','string','max:500'],
            'vendor_tel'     => ['nullable','string','max:100'],
            'vendor_fax'     => ['nullable','string','max:100'],
            'attn'           => ['nullable','string','max:100'],
            'due_date'       => ['nullable','string','max:100'],
            'payment_terms'  => ['nullable','string','max:200'],
            'remarks'        => ['nullable','string','max:1000'],
        ],[],[
            'order_ids'   => '対象注文',
            'vendor_name' => '発注先名',
        ]);

        // テンプレ解決
        [$templatePath, $checkedPaths, $candidates] = $this->resolveTemplatePathWithDiagnostics();
        if (!$templatePath) {
            $this->logTemplateError(
                tag: 'generate_template_not_found',
                checkedPaths: $checkedPaths,
                candidates: $candidates,
                extra: ['route' => 'po.generate', 'selected_orders' => $data['order_ids'] ?? []]
            );
            $msg  = "テンプレートが見つかりません。\n";
            $msg .= "探した場所:\n - " . implode("\n - ", $checkedPaths) . "\n";
            if (!empty($candidates)) {
                $msg .= "見つかった候補:\n - " . implode("\n - ", $candidates) . "\n";
            } else {
                $msg .= "storage/app/".self::TEMPLATE_DIR." 配下に .xlsx または .xlsm を配置してください。";
            }
            $msg .= "\n（.env に PO_TEMPLATE=templates/ファイル名.xlsx のように明示指定できます）";
            return back()->withErrors([$msg]);
        }

        $orders = Order::whereIn('id', $data['order_ids'])
        ->orderBy('purchased_at','asc')->orderBy('id','asc')
        ->get(['id','order_no','buyer_name','purchased_at','purchased_at_text','total']);

        $itemsMap = OrderItem::whereIn('order_id', $orders->pluck('id'))
            ->orderBy('id')
            ->get(['id','order_id','sku','name','quantity','unit_price'])
            ->groupBy('order_id');

        if ($orders->isEmpty()) {
            Log::warning('[PO] no orders selected after validation', [
                'route' => 'po.generate',
                'order_ids' => $data['order_ids'] ?? [],
            ]);
            return back()->withErrors(["有効な注文が選択されていません。"]);
        }

        // Excelテンプレ読み込み
        try {
            $spreadsheet = IOFactory::load($templatePath);
        } catch (\Throwable $e) {
            Log::error('[PO] failed to load template', [
                'route' => 'po.generate',
                'template' => $templatePath,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return back()->withErrors(["テンプレートの読み込みに失敗しました：".$e->getMessage()]);
        }

        // シート（優先:「注文書」）
        $base = $spreadsheet->getSheetByName('注文書') ?: $spreadsheet->getActiveSheet();
        //$sheet->setCellValue([1, 1], '注文書');
        $pad = max(2, strlen((string)$orders->count()));
        $firstSheetName = null;
        $seq = 1;

        foreach ($orders as $o) {
            // 1) シート複製 & 連番リネーム
            $ws = clone $base;
            // 既に同名シートがある場合に備えてユニーク名を生成
            do {
                $name = str_pad((string)$seq, $pad, '0', STR_PAD_LEFT); // "01", "02", …
                $seq++;
            } while ($spreadsheet->getSheetByName($name) !== null);

            $ws->setTitle($name);               // ※シート名は31文字以内・一部記号不可。:contentReference[oaicite:1]{index=1}
            $spreadsheet->addSheet($ws);        // 複製をブックに追加

            $item_row = 15;
            $items = $itemsMap->get($o->id, collect());
            foreach ($items as $it) {
                $ws->setCellValue([$item_row, 6], $it->name);
                $ws->setCellValue([$item_row, 41], $it->quantity);
                $ws->setCellValue([$item_row, 64], $it->line_total);

                $item_row += 2;
            }

            $ws->setCellValue([32, 64], AddressHelper::extractPostalFromText($o->shipto_address_full));
            $ws->setCellValue([33, 64], $o->shipto_tel);
            $ws->setCellValue([34, 64], AddressHelper::addressWithoutPostal($o->shipto_address_full));
            $ws->setCellValue([33, 64], $o->shipto_name);
        }

        // 出力
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
        $tmp = $tmpDir.'/po_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.xlsx';

        try {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setPreCalculateFormulas(true);
            $writer->save($tmp);
        } catch (\Throwable $e) {
            Log::error('[PO] failed to write XLSX', [
                'route' => 'po.generate',
                'target' => $tmp,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return back()->withErrors(["Excel出力に失敗しました：".$e->getMessage()]);
        }

        $filename = '注文書_'.date('Ymd_His').'.xlsx';

        return response()->download($tmp, $filename)->deleteFileAfterSend(true);
    }

    /* ============================== ヘルパ ============================== */

    /**
     * 必須拡張の確認（不足時はメッセージを返す）
     */
    private function ensureSpreadsheetExtensions(): ?string
    {
        $missing = [];
        if (!extension_loaded('zip'))                  $missing[] = 'ext-zip';
        if (!class_exists(\ZipArchive::class, false))  $missing[] = 'ZipArchive';
        // 画像を扱うテンプレなら ext-gd 推奨
        // if (!extension_loaded('gd')) $missing[] = 'ext-gd';

        if ($missing) {
            Log::error('[PO] Missing PHP extensions', [
                'missing'  => $missing,
                'php'      => PHP_VERSION,
                'sapi'     => PHP_SAPI,
                'ini'      => php_ini_loaded_file(),
                'ext_dir'  => ini_get('extension_dir'),
            ]);
            return "Excelテンプレート読込に必要な拡張が不足: ".implode(', ', $missing)
                 . "\nWeb側の php.ini で extension=zip を有効化し、サーバを再起動してください。";
        }
        return null;
    }

    /**
     * Zip/拡張の診断ログ
     */
    private function logZipDiag(string $where): void
    {
        Log::debug('[PO] zip diag', [
            'where'       => $where,
            'php'         => PHP_VERSION,
            'sapi'        => PHP_SAPI,
            'ini'         => php_ini_loaded_file(),
            'ext_dir'     => ini_get('extension_dir'),
            'ext_zip'     => extension_loaded('zip'),
            'class_Zip'   => class_exists(\ZipArchive::class, false),
        ]);
    }


    /**
     * detail_template（1行）をクローンし、結合/書式/行高を保ったまま明細を埋める
     * - テンプレ側に 名前付き範囲 detail_template（例: 注文書!A16:I16）を設定してください
     * - セルに ${name} 形式のプレースホルダがあれば置換、無ければ列順で自動挿入
     * - detail_template が存在しない場合は getDetailStart を用いた旧方式で書き込み
     */
    /**
     * テンプレ解決＋診断情報
     * 優先度:
     *  1) .env PO_TEMPLATE（絶対／storage相対／"storage:"接頭）
     *  2) storage/app/templates/order_form.xlsx
     *  3) storage/app/templates/ 内の .xlsx/.xlsm（「注文書」「order_form」優先）
     */
    private function resolveTemplatePathWithDiagnostics(): array
    {
        $checked = [];
        $candidatesAbs = [];

        // 1) .env 指定
        $env = trim((string)env('PO_TEMPLATE', ''));
        if ($env !== '') {
            if (preg_match('/^(storage:|stor:)/i', $env)) {
                $rel = preg_replace('/^(storage:|stor:)/i', '', $env);
                $abs = Storage::disk('local')->path($rel);
                $checked[] = $abs;
                if (is_file($abs)) return [$abs, $checked, $candidatesAbs];
            } elseif (strpos($env, ':\\') !== false || strpos($env, ':/') !== false || substr($env,0,1) === '/') {
                $abs = $env; $checked[] = $abs;
                if (is_file($abs)) return [$abs, $checked, $candidatesAbs];
            } else {
                $abs = Storage::disk('local')->path($env);
                $checked[] = $abs;
                if (is_file($abs)) return [$abs, $checked, $candidatesAbs];
            }
        }

        // 2) 既定ファイル
        $defaultRel = self::TEMPLATE_DIR.'/'.self::DEFAULT_TEMPLATE;
        $defaultAbs = Storage::disk('local')->path($defaultRel);
        $checked[] = $defaultAbs;
        if (is_file($defaultAbs)) return [$defaultAbs, $checked, $candidatesAbs];

        // 3) ディレクトリ走査
        $dirAbs = Storage::disk('local')->path(self::TEMPLATE_DIR);
        if (is_dir($dirAbs)) {
            $files = @scandir($dirAbs) ?: [];
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (in_array($ext, ['xlsx','xlsm'], true)) {
                    $candidatesAbs[] = $dirAbs.DIRECTORY_SEPARATOR.$f;
                }
            }
            usort($candidatesAbs, function($a,$b){
                $score = function($x){
                    $n = strtolower(basename($x));
                    $s = 0;
                    if (str_contains($n, 'order_form')) $s += 2;
                    if (mb_strpos($n, '注文書') !== false) $s += 3;
                    return -$s;
                };
                return $score($a) <=> $score($b);
            });
            if (!empty($candidatesAbs)) {
                return [$candidatesAbs[0], $checked, $candidatesAbs];
            }
        } else {
            $checked[] = $dirAbs.' (directory missing)';
        }

        return [null, $checked, $candidatesAbs];
    }

    /**
     * エラー時に「探した場所」「候補」などをログ出力
     */
    private function logTemplateError(string $tag, array $checkedPaths, array $candidates, array $extra = []): void
    {
        $context = array_merge([
            'tag'        => $tag,
            'cwd'        => getcwd(),
            'base_path'  => base_path(),
            'storage'    => storage_path(),
            'env_PO_TEMPLATE' => env('PO_TEMPLATE', '(not set)'),
            'checked'    => $checkedPaths,
            'candidates' => $candidates,
        ], $extra);

        $templatesDir = Storage::disk('local')->path(self::TEMPLATE_DIR);
        $context['templates_dir'] = $templatesDir;
        if (is_dir($templatesDir)) {
            $list = [];
            foreach (@scandir($templatesDir) ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                $list[] = $f;
            }
            $context['templates_dir_listing'] = $list;
        } else {
            $context['templates_dir_exists'] = false;
        }

        Log::error('[PO] template not found diagnostics', $context);
    }


}
