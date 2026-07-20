<?php

namespace App\Repositories;

use App\Models\CRM\Orders\Order;

/**
 * Repository thao tác dữ liệu đơn hàng.
 */
class OrderRepository extends BaseRepository
{
    /**
     * Khởi tạo repository với model Order.
     */
    public function __construct(Order $order)
    {
        $this->model = $order;
    }

    /**
     * Lấy các đơn hàng đang chờ duyệt (PENDING_APPROVAL).
     */
    public function getPendingApprovals()
    {
        return $this->model->where('status', 'PENDING_APPROVAL')->get();
    }

    /**
     * Tìm đơn hàng theo ID kèm khách hàng, sản phẩm và lịch sử duyệt.
     */
    public function withRelations($id)
    {
        return $this->model->with(['lead.customer', 'items.product', 'approvals'])->find($id);
    }

    /**
     * Lấy tất cả đơn hàng kèm khách hàng và người tạo, mới nhất trước.
     */
    public function all()
    {
        return Order::with(['lead.customer', 'createdBy'])->latest()->get();
    }

    /**
     * Đếm tổng số đơn hàng.
     */
    public function count()
    {
        return Order::count();
    }

    /**
     * Lấy danh sách đơn hàng mới nhất.
     */
    public function getRecent($limit = 5)
    {
        return Order::orderByDesc('created_at')->take($limit)->get();
    }
}
