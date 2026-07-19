<?php
namespace App\Repositories\Interfaces;
use App\Models\CRM\Orders\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
	public function sumTotalAmount();
	public function revenueByMonth(int $months = 12);
	public function ordersByMonth(int $months = 12);
	public function topSalespersons(int $limit = 5);
	/**
	 * Lấy tất cả đơn hàng với phân trang
	 */
	public function all(): LengthAwarePaginator;

	/**
	 * Tìm đơn hàng theo ID
	 */
	public function find($id): ?Order;

	/**
	 * Tìm đơn hàng với đầy đủ quan hệ
	 */
	public function findWithDetails($id): ?Order;

	/**
	 * Tạo đơn hàng mới
	 */
	public function create(array $data): Order;

	/**
	 * Cập nhật đơn hàng
	 */
	public function update($id, array $data): Order;

	/**
	 * Xóa đơn hàng
	 */
	public function delete($id): int;

	/**
	 * Tìm kiếm đơn hàng với bộ lọc
	 */
	public function search(array $filters = []): LengthAwarePaginator;

	/**
	 * Lấy đơn hàng theo Sales
	 */
	public function getOrdersBySales($salesId, array $filters = []): LengthAwarePaginator;

	/**
	 * Lấy đơn hàng theo bộ phận hiện tại
	 */
	public function getOrdersByDepartment(string $department, array $filters = []): LengthAwarePaginator;
    /**
     * Lấy đơn hàng theo nhiều bộ phận
     */
    public function getOrdersByMultipleDepartments(array $departments, array $filters = []): LengthAwarePaginator;

    /**
	 * Đếm tổng số đơn hàng
	 */
	public function count(): int;

	/**
	 * Đếm đơn hàng theo trạng thái
	 */
	public function countByStatus(string $status): int;

	/**
	 * Đếm đơn hàng theo bộ phận
	 */
	public function countByDepartment(string $department): int;

	/**
	 * Tổng doanh thu
	 */
	public function getTotalRevenue(array $filters = []): float;

	/**
	 * Lấy đơn hàng gần đây
	 */
	public function getRecent($limit = 10): Collection;

	/**
	 * Lấy đơn hàng chưa hoàn tất
	 */
	public function getPendingOrders(): Collection;

	/**
	 * Lấy đơn hàng quá hạn xử lý
	 */
	public function getOverdueOrders(): Collection;
}
