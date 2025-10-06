<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;

class OrderController extends Controller
{
    /**
     * 注文一覧（検索・並び替え・年月フィルタ[<input type="month">]）
     */
    public function index(Request $request)
    {
        $q     = trim((string)$request->input('q', ''));
        $ym    = trim((string)$request->input('ym', ''));
        $sort  = $request->input('sort', 'date_desc');
        $status= $request->input('status'); // ← 追加: 絞り込みキー

        $query = Order::query()
            ->withCount([
                'items', // items_count
                // 状態別カウント
                'items as items_status_pending_count' => fn($q) => $q->where('status', 0),
                'items as items_status_labeled_count' => fn($q) => $q->where('status', 1),
                'items as items_status_shipped_count' => fn($q) => $q->where('status', 2),
            ]);

        // キーワード
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('order_no', 'like', "%{$q}%")
                  ->orWhere('buyer_name', 'like', "%{$q}%")
                  ->orWhere('shop_name', 'like', "%{$q}%");
            });
        }

        // 年月フィルタ（あれば）
        if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
            [$year, $month] = array_map('intval', explode('-', $ym));
            $start = \Carbon\CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('app.timezone','Asia/Tokyo'));
            $end   = $start->addMonth();
            $query->where(function ($w) use ($start,$end,$year,$month) {
                $w->whereBetween('purchased_at', [$start, $end])
                  ->orWhere(function ($q2) use ($year,$month) {
                      $q2->whereNull('purchased_at')
                         ->where('purchased_at_text','like',sprintf('%04d-%02d%%',$year,$month));
                  });
            });
        }

        // 並び順
        switch ($sort) {
            case 'date_asc':
                $query->orderBy('purchased_at','asc')->orderBy('id','asc'); break;
            case 'total_desc':
                $query->orderBy('total','desc'); break;
            default:
                $query->orderBy('purchased_at','desc')->orderBy('id','desc'); break;
        }

        // ▼ 状態で絞り込み（HAVING を使うのがポイント）
        //   * 全て未対応      : items_status_pending_count  = items_count (>0)
        //   * 全て送り状発行  : items_status_labeled_count  = items_count (>0)
        //   * 全て発送済み    : items_status_shipped_count  = items_count (>0)
        //   * 一部送り状発行  : 0 < items_status_labeled_count  < items_count
        //   * 一部発送済み    : 0 < items_status_shipped_count  < items_count
        if (in_array($status, ['pending','labeled','shipped','partial_labeled','partial_shipped'], true)) {
            $query->having('items_count', '>', 0); // 明細ゼロは除外（必要に応じて外してOK）
            switch ($status) {
                case 'pending':
                    $query->havingRaw('items_status_pending_count = items_count');
                    break;
                case 'labeled':
                    $query->havingRaw('items_status_labeled_count = items_count');
                    break;
                case 'shipped':
                    $query->havingRaw('items_status_shipped_count = items_count');
                    break;
                case 'partial_labeled':
                    $query->having('items_status_labeled_count', '>', 0)
                          ->havingRaw('items_status_labeled_count < items_count');
                    break;
                case 'partial_shipped':
                    $query->having('items_status_shipped_count', '>', 0)
                          ->havingRaw('items_status_shipped_count < items_count');
                    break;
            }
        }

        $orders = $query->paginate(20)->appends($request->query());

        return view('orders.index', [
            'orders' => $orders,
            'q'      => $q,
            'ym'     => $ym,
            'sort'   => $sort,
            'status' => $status, // ← ビューで active 表示に使う
        ]);
    }

    /**
     * 注文更新（詳細ページの編集フォームから）
     */
    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            // 注文ヘッダ
            'payment_method'      => ['nullable','string','max:255'],
            'ship_carrier'        => ['nullable','string','max:255'],
            'ship_date_request'   => ['nullable','string','max:255'],
            'ship_time_window'    => ['nullable','string','max:255'],
            'mail_preference'     => ['nullable','string','max:255'],
            // 追加フラグ
            'is_shipped'          => ['nullable','boolean'],
            'is_gift'             => ['nullable','boolean'],

            // 購入者
            'buyer_name'          => ['nullable','string','max:255'],
            'buyer_kana'          => ['nullable','string','max:255'],
            'buyer_address_full'  => ['nullable','string'],
            'buyer_tel'           => ['nullable','string','max:50'],
            'buyer_mobile'        => ['nullable','string','max:50'],
            'buyer_email'         => ['nullable','string','max:255'],

            // お届け先
            'shipto_name'         => ['nullable','string','max:255'],
            'shipto_kana'         => ['nullable','string','max:255'],
            'shipto_address_full' => ['nullable','string'],
            'shipto_tel'          => ['nullable','string','max:50'],
        ]);

        // チェックボックス未チェック時に null になる場合の補完
        if (!array_key_exists('is_shipped', $data)) { $data['is_shipped'] = (bool)$request->input('is_shipped', 0); }
        if (!array_key_exists('is_gift', $data))    { $data['is_gift']    = (bool)$request->input('is_gift', 0); }

        $order->update($data);

        return redirect()->route('orders.show', $order)->with('status', '注文情報を更新しました');
    }

    /**
     * 注文削除（明細は FK の cascadeOnDelete を想定）
     */
    public function destroy(Order $order)
    {
        $order->delete();
        return redirect()->route('orders.index')->with('status', '注文を削除しました');
    }
}
