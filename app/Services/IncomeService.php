<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IncomeService
{
    /**
     * 収入計上の基準:
     * - order_date（デフォルト）: 注文日の属する月に計上
     * - labeled: 明細に「送り状発行済み(1)」が1件以上ある注文を対象
     * - shipped: 明細が「発送済み(2)」の注文を対象（全件 or 一部、は後述の mode で調整可）
     *
     * mode:
     * - any : 一部でも該当すれば対象
     * - all : すべて該当する注文のみ
     */
    public function monthlySummary(int $months = 12, string $basis = null, string $mode = 'any'): Collection
    {
        $basis = $basis ?? config('income.basis', 'order_date');
        $tz    = config('app.timezone', 'Asia/Tokyo');

        // 直近Nヶ月（今月含む）を作る
        $now = CarbonImmutable::now($tz)->startOfMonth();
        $list = collect();
        for ($i = 0; $i < $months; $i++) {
            $month = $now->subMonths($i);
            $ym = $month->format('Y-m');
            $list->push([
                'ym'    => $ym,
                'start' => $month,
                'end'   => $month->addMonth(),
            ]);
        }

        // まとめて集計
        return $list->map(function ($m) use ($basis, $mode) {
            $sum = $this->sumForMonth($m['start'], $m['end'], $basis, $mode);
            return array_merge($m, $sum);
        });
    }

    /** ある月の合計を返す（画面のカードなど用） */
    public function sumForMonth(CarbonImmutable $start, CarbonImmutable $end, string $basis = 'order_date', string $mode = 'any'): array
    {
        $q = Order::query();

        // 対象の注文を絞る
        $this->applyBasis($q, $start, $end, $basis, $mode);

        // 合計値
        $totals = $q->selectRaw('
                COUNT(*) as orders_count,
                COALESCE(SUM(subtotal),0)     as subtotal_sum,
                COALESCE(SUM(shipping_fee),0) as shipping_sum,
                COALESCE(SUM(cool_fee),0)     as cool_sum,
                COALESCE(SUM(tax10),0)        as tax10_sum,
                COALESCE(SUM(tax8),0)         as tax8_sum,
                COALESCE(SUM(total),0)        as total_sum
            ')->first();

        // 支払方法の内訳
        $byMethod = $this->byPaymentMethod($start, $end, $basis, $mode);

        return [
            'orders'        => (int)$totals->orders_count,
            'subtotal_sum'  => (int)$totals->subtotal_sum,
            'shipping_sum'  => (int)$totals->shipping_sum,
            'cool_sum'      => (int)$totals->cool_sum,
            'tax10_sum'     => (int)$totals->tax10_sum,
            'tax8_sum'      => (int)$totals->tax8_sum,
            'total_sum'     => (int)$totals->total_sum,
            'by_method'     => $byMethod,
        ];
    }

    /** 支払方法別の内訳 */
    public function byPaymentMethod(CarbonImmutable $start, CarbonImmutable $end, string $basis = 'order_date', string $mode = 'any'): array
    {
        $q = Order::query()->select([
            DB::raw('COALESCE(payment_method, "（未設定）") as method'),
            DB::raw('COUNT(*) as cnt'),
            DB::raw('COALESCE(SUM(total),0) as sum_total'),
        ]);

        $this->applyBasis($q, $start, $end, $basis, $mode);

        return $q->groupBy('method')
            ->orderByDesc('sum_total')
            ->get()
            ->map(fn($r) => [
                'method' => $r->method,
                'count'  => (int)$r->cnt,
                'total'  => (int)$r->sum_total,
            ])->all();
    }

    /**
     * ある月の「収支シート行データ」（Excel出力に使える形）
     * 返す配列は config/income.php の columns に合わせる
     */
    public function rowsForMonth(string $ym, string $basis = null, string $mode = 'any'): array
    {
        $basis = $basis ?? config('income.basis', 'order_date');
        [$start, $end] = $this->rangeFromYm($ym);

        $includeShipping = (bool)config('income.include_shipping_in_total', true);
        $includeCool     = (bool)config('income.include_cool_in_total', true);

        $orders = Order::query()
            ->with(['items:id,order_id'])
            ->when(true, fn($q) => $this->applyBasis($q, $start, $end, $basis, $mode))
            ->orderBy('purchased_at', 'asc')
            ->get();

        $rows = [];
        foreach ($orders as $o) {
            $date = optional($o->purchased_at)->format('Y-m-d') ?: ($o->purchased_at_text ?? '');
            $total = (int)$o->subtotal;
            if ($includeShipping) $total += (int)$o->shipping_fee;
            if ($includeCool)     $total += (int)$o->cool_fee;

            // 出力列（デフォルト定義に合わせる）
            $rows[] = [
                '日付'       => $date,
                '注文番号'   => (string)$o->order_no,
                '購入者'     => (string)$o->buyer_name,
                '支払方法'   => (string)$o->payment_method,
                '小計'       => (int)$o->subtotal,
                '送料'       => (int)$o->shipping_fee,
                'クール'     => (int)$o->cool_fee,
                '税10'       => (int)$o->tax10,
                '税8'        => (int)$o->tax8,
                '合計'       => $total, // ← “収入”として計上する金額
                // 任意の追記欄
                '備考'       => $o->note ?? '',
            ];
        }

        return $rows;
    }

    /** 期間条件をクエリに適用 */
    private function applyBasis($query, CarbonImmutable $start, CarbonImmutable $end, string $basis, string $mode): void
    {
        if ($basis === 'order_date') {
            $query->where(function ($w) use ($start, $end) {
                $w->whereBetween('purchased_at', [$start, $end])
                  ->orWhere(function ($q2) use ($start) {
                      // purchased_at が null の場合、原文テキストから同月に絞る運用も可能だが
                      // ここでは除外（必要なら like 条件を足す）
                  });
            });
            return;
        }

        // item の status による抽出
        // 0=未対応,1=送り状発行済み,2=発送済み
        $targetStatus = $basis === 'labeled' ? 1 : 2;

        if ($mode === 'all') {
            // すべての明細が targetStatus 以上
            $query->whereDoesntHave('items', function ($q) use ($targetStatus) {
                $q->where('status', '<', $targetStatus);
            });
        } else {
            // any: 一部でも targetStatus 以上があればOK
            $query->whereHas('items', function ($q) use ($targetStatus) {
                $q->where('status', '>=', $targetStatus);
            });
        }

        // 計上月の考え方は「注文日ベース」を踏襲（必要ならここを変更）
        $query->whereBetween('purchased_at', [$start, $end]);
    }

    /** ym → [start, end] */
    public function rangeFromYm(string $ym): array
    {
        $tz = config('app.timezone','Asia/Tokyo');
        [$y,$m] = array_map('intval', explode('-', $ym));
        $start = CarbonImmutable::create($y, $m, 1, 0, 0, 0, $tz);
        return [$start, $start->addMonth()];
    }

    /**
     * 売上ランキング（SKU×商品名単位）
     * @param string $startYm  開始年月 (YYYY-MM)
     * @param string $endYm    終了年月 (YYYY-MM)
     * @param string $basis    計上基準（将来拡張用: 'order_date' 固定運用）
     * @param string $mode     将来拡張用（'any' 固定運用）
     * @param int    $limit    取得件数
     * @return array<array{sku:string,name:string,qty:int,total:int,orders:int,rows:int}>
     */
    public function salesRanking(string $startYm, string $endYm, string $basis = 'order_date', string $mode = 'any', int $limit = 50): array
    {
        // 期間
        [$start, ] = $this->rangeFromYm($startYm);    // 開始月1日 00:00
        [, $end]   = $this->rangeFromYm($endYm);      // 終了月の翌月1日 00:00

        // 終了が開始より前なら入れ替え（入力ミス救済）
        if ($end->lessThanOrEqualTo($start)) {
            $tmp = $start; $start = $end->subMonth(); $end = $tmp->addMonth();
        }

        // ランキング本体
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.purchased_at', [$start, $end])
            ->groupBy('order_items.sku', 'order_items.name')
            ->select([
                DB::raw('COALESCE(order_items.sku, "") as sku'),
                DB::raw('order_items.name as name'),
                DB::raw('SUM(order_items.quantity) as qty_sum'),
                DB::raw('SUM(order_items.line_total) as total_sum'),
                DB::raw('COUNT(*) as rows_cnt'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders_cnt'),
            ])
            ->orderByDesc('total_sum')
            ->limit($limit)
            ->get();

        // 整形（表示用のプレースホルダもここで）
        return $rows->map(function($r){
            return [
                'sku'    => $r->sku !== '' ? $r->sku : '（SKU未設定）',
                'name'   => (string)$r->name,
                'qty'    => (int)$r->qty_sum,
                'total'  => (int)$r->total_sum,
                'orders' => (int)$r->orders_cnt,
                'rows'   => (int)$r->rows_cnt,
            ];
        })->all();
    }
}
