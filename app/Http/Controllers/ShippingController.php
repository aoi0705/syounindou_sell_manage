<?php

namespace App\Http\Controllers;

use App\Mail\ShippingCompletedMail;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ShippingController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->input('q', ''));

        $items = OrderItem::query()
            ->with(['order:id,order_no,buyer_name,buyer_email,shipto_name,shipto_address_full,shipto_tel,ship_date_request,ship_time_window,ship_carrier,payment_method'])
            ->where('status', OrderItem::STATUS_LABEL_ISSUED)
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('sku', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                      ->orWhereHas('order', function ($o) use ($q) {
                          $o->where('order_no', 'like', "%{$q}%")
                            ->orWhere('buyer_name', 'like', "%{$q}%");
                      });
                });
            })
            ->orderBy('label_issued_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(50)
            ->appends($request->query());

        return view('shipping.index', [
            'items' => $items,
            'q'     => $q,
        ]);
    }

    public function dispatch(Request $request)
    {
        $data = $request->validate([
            'ids'           => ['required','array','min:1'],
            'ids.*'         => ['integer','distinct'],
            'tracking_no'   => ['required','array'],
            'tracking_no.*' => ['nullable','string','max:100'],
        ],[],[
            'ids'         => '対象明細',
            'tracking_no' => '追跡番号',
        ]);

        $items = OrderItem::with(['order'])
            ->whereIn('id', $data['ids'])
            ->where('status', OrderItem::STATUS_LABEL_ISSUED)
            ->get();

        if ($items->isEmpty()) {
            return back()->withErrors(['ids' => '有効な対象が選択されていません。']);
        }

        // 追跡番号必須（未入力があればエラー）
        foreach ($items as $it) {
            $tn = trim((string)($data['tracking_no'][$it->id] ?? ''));
            if ($tn === '') {
                return back()->withErrors(['tracking_no' => "明細ID {$it->id} の追跡番号が未入力です。"]);
            }
        }

        DB::beginTransaction();
        try {
            $now = now();

            foreach ($items as $it) {
                $tn = trim((string)$data['tracking_no'][$it->id]);
                $it->tracking_no = $tn;
                $it->status      = OrderItem::STATUS_SHIPPED;
                $it->shipped_at  = $now;
                $it->save();
            }

            $byOrder = $items->groupBy('order_id');
            foreach ($byOrder as $orderId => $rows) {
                $order = $rows->first()->order;
                $to = trim((string)($order->buyer_email ?? ''));
                if ($to === '') continue;

                //Mail::to($to)->send(new ShippingCompletedMail($order, $rows->all()));
                Mail::to("kuwaaoi0@gmail.com")->send(new ShippingCompletedMail($order, $rows->all()));
                // ->queue(...) に切替可
            }

            DB::commit();

            return redirect()
                ->route('shipping.index')
                ->with('status', "発送処理を完了しました（".count($items)."件）");

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors(['dispatch' => '発送処理でエラー：'.$e->getMessage()]);
        }
    }

    /**
     * ★ 追跡番号の「保存だけ」を行う（ステータスは変更しない）
     * - 入力されている tracking_no[*] を見て、該当IDの明細に保存
     * - status が「送り状発行済み」のものだけ対象
     * - 空文字は null 保存（クリア）
     */
    public function saveTracking(Request $request)
    {
        $data = $request->validate([
            'tracking_no'   => ['required','array'],
            'tracking_no.*' => ['nullable','string','max:100'],
            // 任意で一部だけ保存したい時のために ids を受けるが、空でもOK
            'ids'           => ['array'],
            'ids.*'         => ['integer','distinct'],
        ]);

        // 保存対象：ids が送られていればそれを優先、無ければ tracking_no のキー全部
        $targetIds = !empty($data['ids'])
            ? array_map('intval', $data['ids'])
            : array_map('intval', array_keys($data['tracking_no']));

        if (empty($targetIds)) {
            return back()->withErrors(['tracking_no' => '保存対象がありません。']);
        }

        $items = OrderItem::whereIn('id', $targetIds)
            ->where('status', OrderItem::STATUS_LABEL_ISSUED)
            ->get();

        if ($items->isEmpty()) {
            return back()->withErrors(['tracking_no' => '保存対象の明細が見つかりません。']);
        }

        foreach ($items as $it) {
            $raw = $data['tracking_no'][$it->id] ?? null;
            $tn  = is_string($raw) ? trim($raw) : null;
            // 空ならクリア、入力があれば保存
            $it->tracking_no = ($tn === '' ? null : $tn);
            $it->save();
        }

        return back()->with('status', '追跡番号を保存しました（'.count($items).'件）');
    }
}
