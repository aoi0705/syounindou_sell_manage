<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;

class OrderController extends Controller
{
    /**
     * 注文一覧（検索・並び替え・年月フィルタ[<input type="month">]）
     */
    public function index(Request $request)
    {
        $q    = trim((string)$request->input('q', ''));
        $sort = $request->input('sort', 'date_desc'); // date_desc/date_asc/total_desc

        // <input type="month" name="ym"> から来る YYYY-MM を優先
        $ym   = trim((string)$request->input('ym', ''));

        // 互換用（古い y / m パラメータが来た場合）
        $year  = $request->integer('y');
        $month = $request->integer('m');

        if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
            [$year, $month] = array_map('intval', explode('-', $ym));
        }

        // 範囲チェック
        if ($year && ($year < 2000 || $year > 2100)) { $year = null; $month = null; }
        if ($month && ($month < 1 || $month > 12))   { $month = null; }

        $query = Order::query()->withCount('items');

        // キーワード検索
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('order_no', 'like', "%{$q}%")
                  ->orWhere('buyer_name', 'like', "%{$q}%")
                  ->orWhere('shop_name', 'like', "%{$q}%");
            });
        }

        // 年月フィルタ（purchased_at 優先、無ければ purchased_at_text の接頭一致でフォールバック）
        if ($year) {
            $tz    = config('app.timezone', 'Asia/Tokyo');
            $start = CarbonImmutable::create($year, $month ?: 1, 1, 0, 0, 0, $tz);
            $end   = $month ? $start->addMonths(1) : $start->addYear();

            $query->where(function ($w) use ($start, $end, $year, $month) {
                $w->whereBetween('purchased_at', [$start, $end])
                  ->orWhere(function ($q2) use ($year, $month) {
                      if ($month) {
                          $q2->whereNull('purchased_at')
                             ->where('purchased_at_text', 'like', sprintf('%04d-%02d%%', $year, $month));
                      } else {
                          $q2->whereNull('purchased_at')
                             ->where('purchased_at_text', 'like', sprintf('%04d-%%', $year));
                      }
                  });
            });
        }

        // 並び替え
        switch ($sort) {
            case 'date_asc':
                $query->orderBy('purchased_at', 'asc')->orderBy('id', 'asc');
                break;
            case 'total_desc':
                $query->orderBy('total', 'desc');
                break;
            default:
                $query->orderBy('purchased_at', 'desc')->orderBy('id', 'desc');
                break;
        }

        $orders = $query->paginate(20)->appends($request->query());

        // ビューは ym（YYYY-MM）を使う。空なら '' を渡す。
        return view('orders.index', [
            'orders' => $orders,
            'q'      => $q,
            'sort'   => $sort,
            'ym'     => $ym ?: ( ($year && $month) ? sprintf('%04d-%02d', $year, $month) : '' ),
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
