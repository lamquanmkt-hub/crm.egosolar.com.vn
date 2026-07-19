<?php
namespace App\Repositories\Eloquent;
use App\Models\CRM\Orders\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
	public function count(): int {
		return DB::table('crm_orders')->count();
	}
	public function sumTotalAmount()
	{
		return DB::table('crm_orders')->sum('total_amount');
	}
	public function getRecent($limit = 10): Collection {
		return Order::orderBy('order_date', 'desc')
            ->limit($limit)
            ->get();
	}
	public function revenueByMonth(int $months = 12)
	{
		return DB::table('crm_orders')
		         ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as month")
		         ->selectRaw("SUM(total_amount) as revenue")
		         ->whereNotNull('order_date')
		         ->groupBy('month')
		         ->orderBy('month', 'asc')
		         ->get();
	}
	public function ordersByMonth(int $months = 12)
	{
		return DB::table('crm_orders')
		         ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as month")
		         ->selectRaw("COUNT(*) as total_orders")
		         ->whereNotNull('order_date')
		         ->groupBy('month')
		         ->orderBy('month', 'asc')
		         ->get();
	}
	public function orderStatusCounts(): \Illuminate\Support\Collection
    {
		return DB::table('crm_orders')
		         ->select('current_status_type_id', DB::raw('COUNT(*) as total'))
		         ->groupBy('current_status_type_id')
		         ->get();
	}
	public function topSalespersons(int $limit = 5): \Illuminate\Support\Collection
    {
		return DB::table('crm_orders')
		         ->join('users', 'users.id', '=', 'crm_orders.created_by')
		         ->select('users.id', 'users.name', DB::raw('SUM(crm_orders.total_amount) as revenue'), DB::raw('COUNT(crm_orders.id) as orders_count'))
		         ->groupBy('users.id', 'users.name')
		         ->orderByDesc('revenue')
		         ->limit($limit)
		         ->get();
	}
	public function all(): LengthAwarePaginator
	{
		return Order::with([
			'lead.customer',
			'warehouse',
			'creator',
			'currentStatusType'
		])
		            ->latest('id')
		            ->paginate(20);
	}

	public function find($id): ?Order
	{
		return Order::with([
			'lead.customer.customerType',
			'items.product',
			'warehouse',
			'creator',
			'approver',
			'currentStatusType'
		])->findOrFail($id);
	}

	public function findWithDetails($id): ?Order
	{
		return Order::with([
			'lead.customer' => function ($query) {
				$query->with([
					'customerType',
					'region',
					'unpaidDebts'
				]);
			},
			'items.product',
			'warehouse',
			'creator',
			'approver',
			'approvals' => function ($query) {
				$query->with('approver')->orderBy('approved_at', 'asc');
			},
			'payments' => function ($query) {
				$query->with('method', 'recordedBy')->latest();
			},
			'currentStatusType',
			'debt',
			'tasks' => function ($query) {
				$query->whereIn('status', ['pending', 'in_progress']);
			}
		])->findOrFail($id);
	}

	public function create(array $data): Order
	{
		return Order::create($data);
	}

	public function update($id, array $data): Order
	{
		$order = $this->find($id);
		$order->update($data);
		return $order->fresh();
	}

	public function delete($id): int
	{
		return Order::destroy($id);
	}

	public function search(array $filters = []): LengthAwarePaginator
	{
		$query = Order::query()->with([
			'lead.customer',
			'warehouse',
			'creator',
			'currentStatusType'
		]);

		// Tìm kiếm theo mã đơn hoặc tên khách
		if (!empty($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('order_code', 'like', "%{$search}%")
				  ->orWhereHas('lead.customer', function ($q2) use ($search) {
					  $q2->where('name', 'like', "%{$search}%")
					     ->orWhere('phone', 'like', "%{$search}%");
				  });
			});
		}

		// Lọc theo trạng thái/bộ phận
		if (!empty($filters['status'])) {
			$query->where('current_department', $filters['status']);
		}

		// Lọc theo kho
//		if (!empty($filters['warehouse_id'])) {
//			$query->where('warehouse_id', $filters['warehouse_id']);
//		}
        if (!empty($filters['warehouse_id'])) {
            $warehouseId = (int)$filters['warehouse_id'];

            $query->join('crm_order_items as oi', 'oi.order_id', '=', 'crm_orders.id')
                ->where('oi.warehouse_id', $warehouseId)
                ->select('crm_orders.*')
                ->distinct();
        }

		// Lọc theo ngày
		if (!empty($filters['from_date'])) {
			$query->whereDate('order_date', '>=', $filters['from_date']);
		}

		if (!empty($filters['to_date'])) {
			$query->whereDate('order_date', '<=', $filters['to_date']);
		}

		// Lọc theo người tạo
		if (!empty($filters['created_by'])) {
			$query->where('created_by', $filters['created_by']);
		}

		return $query->latest('id')->paginate($filters['per_page'] ?? 20);
	}

	public function getOrdersBySales($salesId, array $filters = []): LengthAwarePaginator
	{
		$filters['created_by'] = $salesId;
		return $this->search($filters);
	}

	public function getOrdersByDepartment(string $department, array $filters = []): LengthAwarePaginator
	{
		$query = Order::query()->with([
			'lead.customer',
			'warehouse',
			'creator',
			'currentStatusType'
		])
		              ->where('current_department', $department);

		// Áp dụng các filter khác
		if (!empty($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('order_code', 'like', "%{$search}%")
				  ->orWhereHas('lead.customer', function ($q2) use ($search) {
					  $q2->where('name', 'like', "%{$search}%");
				  });
			});
		}

		if (!empty($filters['from_date'])) {
			$query->whereDate('order_date', '>=', $filters['from_date']);
		}

		if (!empty($filters['to_date'])) {
			$query->whereDate('order_date', '<=', $filters['to_date']);
		}

		return $query->latest('id')->paginate($filters['per_page'] ?? 20);
	}
    public function getOrdersByMultipleDepartments(array $departments, array $filters = []): LengthAwarePaginator
    {
        $query = Order::query()->with([
            'lead.customer',
            'warehouse',
            'creator',
            'currentStatusType'
        ])
            ->whereIn('current_department', $departments);

        // Áp dụng các filter khác
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', "%{$search}%")
                    ->orWhereHas('lead.customer', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        return $query->latest('id')->paginate($filters['per_page'] ?? 20);
    }

    public function countByStatus(string $status): int
	{
		return Order::whereHas('currentStatusType', function ($query) use ($status) {
			$query->where('code', $status);
		})->count();
	}

	public function countByDepartment(string $department): int
	{
		return Order::where('current_department', $department)->count();
	}

	public function getTotalRevenue(array $filters = []): float
	{
		$query = Order::query()->where('payment_recorded', true);

		if (!empty($filters['from_date'])) {
			$query->whereDate('order_date', '>=', $filters['from_date']);
		}

		if (!empty($filters['to_date'])) {
			$query->whereDate('order_date', '<=', $filters['to_date']);
		}

		return $query->sum('total_amount');
	}

//	public function getRecent($limit = 10): Collection
//	{
//		return Order::with([
//			'lead.customer',
//			'creator',
//			'currentStatusType'
//		])
//		            ->latest('created_at')
//		            ->take($limit)
//		            ->get();
//	}

	public function getPendingOrders(): Collection
	{
		return Order::where('current_department', '!=', 'completed')
		            ->with([
			            'lead.customer',
			            'creator',
			            'currentStatusType'
		            ])
		            ->orderBy('created_at', 'asc')
		            ->get();
	}

	public function getOverdueOrders(): Collection
	{
		// Lấy đơn hàng quá hạn dự kiến xử lý
		return Order::where('current_department', '!=', 'completed')
		            ->whereNotNull('estimated_delivery')
		            ->whereDate('estimated_delivery', '<', now())
		            ->with([
			            'lead.customer',
			            'creator',
			            'currentStatusType'
		            ])
		            ->get();
	}
}
