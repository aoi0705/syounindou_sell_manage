<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\BankTransfer;
use App\Services\BankTransferParser;
use Illuminate\Http\Request;

class BankTransferController extends Controller
{
    // 銀行振込の注文一覧
    public function index(Request $request)
    {
        $q     = trim((string)$request->input('q', ''));
        $year  = $request->integer('y');
        $month = $request->integer('m');

        $ym = trim((string)$request->input('ym', ''));
        if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
            [$year, $month] = array_map('intval', explode('-', $ym));
        }

        $query = Order::query()
            ->withCount('items')
            ->where('payment_method', 'like', '%銀行振込%');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('order_no', 'like', "%{$q}%")
                  ->orWhere('buyer_name', 'like', "%{$q}%")
                  ->orWhere('shop_name', 'like', "%{$q}%");
            });
        }

        if ($year) {
            $tz    = config('app.timezone','Asia/Tokyo');
            $start = \Carbon\CarbonImmutable::create($year, $month ?: 1, 1, 0, 0, 0, $tz);
            $end   = $month ? $start->addMonths(1) : $start->addYear();

            $query->where(function ($w) use ($start, $end, $year, $month) {
                $w->whereBetween('purchased_at', [$start, $end])
                  ->orWhere(function ($q2) use ($year, $month) {
                      if ($month) {
                          $q2->whereNull('purchased_at')
                             ->where('purchased_at_text','like',sprintf('%04d-%02d%%', $year, $month));
                      } else {
                          $q2->whereNull('purchased_at')
                             ->where('purchased_at_text','like',sprintf('%04d-%%', $year));
                      }
                  });
            });
        }

        $orders = $query->orderBy('purchased_at','desc')->orderBy('id','desc')
                        ->paginate(20)->appends($request->query());

        return view('bank.index', compact('orders','q','year','month'));
    }

    // 注文ごとの振込記録一覧＋取り込みフォーム
    public function show(Order $order)
    {
        $order->load('items');
        $transfers = BankTransfer::where('order_id', $order->id)
            ->orderBy('transfer_at', 'desc')->orderBy('id', 'desc')->get();

        return view('bank.show', compact('order','transfers'));
    }

    // テキスト貼り付けで登録
    public function store(Request $request, Order $order, BankTransferParser $parser)
    {
        $raw = (string) $request->input('raw_body');
        $p   = $parser->parse($raw);

        BankTransfer::create([
            'order_id'         => $order->id,
            'transfer_at_text' => $p['transfer_at_text'],
            'transfer_at'      => $p['transfer_at'],
            'amount'           => $p['amount'],
            'bank_name'        => $p['bank_name'],
            'branch_name'      => $p['branch_name'],
            'payer_name'       => $p['payer_name'],
            'raw_body'         => $raw,
        ]);

        return redirect()->route('bank.show', $order)->with('status','振込記録を登録しました');
    }

    public function destroy(Order $order, BankTransfer $transfer)
    {
        abort_unless($transfer->order_id === $order->id, 404);
        $transfer->delete();
        return redirect()->route('bank.show', $order)->with('status','振込記録を削除しました');
    }
}
