<?php

namespace App\Http\Controllers;

use App\Models\CRM\Orders\Order;
use App\Models\OrderReturn;
use Illuminate\Http\Request;

// ✅ đúng theo dự án bạn

class OrderReturnController extends Controller
{
    public function create($orderId)
    {
        $order = Order::findOrFail($orderId);

        // ✅ chỉ cho đổi/trả khi đơn hoàn tất
        $isCompleted =
            ($order->currentKey ?? null) === 'completed'
            || (isset($order->status_name) && mb_strtolower($order->status_name) === 'hoàn tất')
            || (isset($order->statusName) && mb_strtolower($order->statusName) === 'hoàn tất');

        if (!$isCompleted) {
            abort(403, 'ĐƠN CHƯA HOÀN TẤT NÊN CHƯA THỂ ĐỔI/TRẢ.');
        }

        return view('orders.returns.create', compact('order'));
    }

    public function store(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        $isCompleted =
            ($order->currentKey ?? null) === 'completed'
            || (isset($order->status_name) && mb_strtolower($order->status_name) === 'hoàn tất')
            || (isset($order->statusName) && mb_strtolower($order->statusName) === 'hoàn tất');

        if (!$isCompleted) {
            abort(403, 'ĐƠN CHƯA HOÀN TẤT NÊN CHƯA THỂ ĐỔI/TRẢ.');
        }

        $data = $request->validate([
            'type'   => 'required|in:return,exchange',
            'reason' => 'required|string|min:5|max:2000',
        ]);

        OrderReturn::create([
            'order_id' => $order->id,
            'type'     => $data['type'],
            'reason'   => $data['reason'],
            'status'   => 'pending',
        ]);

        return redirect()
            ->route('orders.show', $order->id)
            ->with('success', 'Đã tạo yêu cầu đổi/trả. Vui lòng chờ duyệt.');
    }
}
