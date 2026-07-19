<?php
namespace App\Repositories;
use App\Models\CRM\Orders\Order;

class OrderRepository extends BaseRepository
{
	public function __construct(Order $order)
	{
		$this->model = $order;
	}
	public function getPendingApprovals()
	{
		return $this->model->where('status', 'PENDING_APPROVAL')->get();
	}
	public function withRelations($id)
	{
		return $this->model->with(['lead.customer', 'items.product', 'approvals'])->find($id);
	}
	public function all()
	{
		return Order::with(['lead.customer', 'createdBy'])->latest()->get();
	}

	public function count()
	{
		return Order::count();
	}
	public function getRecent($limit = 5)
	{
		return Order::orderByDesc('created_at')->take($limit)->get();
	}
}
