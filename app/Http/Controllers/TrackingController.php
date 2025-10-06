<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function update(Request $request, OrderItem $item)
    {
        // 「送り状発行済み」のものだけ登録可（お好みで外してもOK）
        if ($item->status !== OrderItem::STATUS_LABEL_ISSUED) {
            return back()->withErrors(['tracking_no' => 'この明細は追跡番号を登録できる状態ではありません。']);
        }

        $data = $request->validate([
            'tracking_no' => ['required','string','max:100'],
        ],[],['tracking_no' => '追跡番号']);

        $item->tracking_no = trim($data['tracking_no']);
        $item->status      = OrderItem::STATUS_SHIPPED;
        $item->shipped_at  = now();
        $item->save();

        return back()->with('status', "追跡番号を登録しました（#{$item->id}）");
    }
}
