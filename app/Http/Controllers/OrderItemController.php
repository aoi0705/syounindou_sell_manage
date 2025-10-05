<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function update(Request $request, Order $order, OrderItem $item)
    {
        if ($item->order_id !== $order->id) {
            abort(404);
        }

        $data = $request->validate([
            'sku'        => ['nullable','string','max:50'],
            'name'       => ['required','string','max:255'],
            'unit_price' => ['required','integer','min:0'],
            'quantity'   => ['required','integer','min:0'],
        ]);
        $data['line_total'] = (int)$data['unit_price'] * (int)$data['quantity'];

        $item->update($data);

        return redirect()->route('orders.show', $order)->with('status', '明細を更新しました');
    }

    public function destroy(Order $order, OrderItem $item)
    {
        if ($item->order_id !== $order->id) {
            abort(404);
        }

        $item->delete();

        return redirect()->route('orders.show', $order)->with('status', '明細を削除しました');
    }
}
