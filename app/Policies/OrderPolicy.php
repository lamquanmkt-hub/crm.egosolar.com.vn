<?php
namespace App\Policies;
use App\Enums\OrderDepartment;
use App\Models\CRM\Orders\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
	use HandlesAuthorization;
	public function viewAny(User $user): bool
	{
		return true;
	}
	public function view(User $user, Order $order): bool
	{
		if ($user->hasRole(['admin', 'management', 'accounting', 'sales_manager', 'warehouse'])) {
			return true;
		}
		if ($user->hasRole('sales')) {
			return (int) $order->created_by === (int) $user->id;
		}
		return false;
	}
	public function markShipped(User $user, Order $order): bool
{
    if (
        ($order->status ?? null) === 'cancelled'
        || ($order->current_status ?? null) === 'cancelled'
        || ($order->current_department ?? null) === 'cancelled'
        || ($order->approval_status ?? null) === 'cancelled'
    ) {
        return false;
    }

    // Phải xuất kho xong mới được đánh dấu đã vận chuyển
    if ((int)($order->inventory_issued ?? 0) !== 1) {
        return false;
    }

    // Đã ship rồi thì không bấm lại
    if ((int)($order->is_shipped ?? 0) === 1 || ($order->shipping_status ?? null) === 'shipped') {
        return false;
    }

    return $user->hasRole([
        'admin',
        'sales',
        'sales_manager',
        'accounting', 'ke_toan', 'kế toán',
        'warehouse', 'kho',
    ]);
}
	public function create(User $user): bool
	{
		return $user->hasRole(['sales', 'admin', 'sales_manager', 'warehouse']);
	}
	public function update(User $user, Order $order): bool
{
    // Không cho sửa khi đã xuất kho
    if ((int)($order->inventory_issued ?? 0) === 1) {
        return false;
    }

    // Không cho sửa khi đã hoàn tất
    if ($order->current_department === 'completed') {
        return false;
    }

    // Admin luôn được sửa
    if ($user->hasRole(['admin', 'super_admin'])) {
        return true;
    }

    // Kế toán được sửa kể cả sau khi Sales Manager đã duyệt
    if ($user->hasRole(['accounting', 'ke_toan', 'kế toán'])) {
        return true;
    }

    // Sales / Sales Manager chỉ được sửa đơn của mình khi còn ở bước sales
    if ($user->hasRole(['sales', 'sales_manager'])) {
        return (int) $order->created_by === (int) $user->id
            && $order->current_department === 'sales';
    }

    // Warehouse giữ nguyên logic cũ
    if ($user->hasRole(['warehouse'])) {
        return (int) $order->created_by === (int) $user->id
            && $order->current_department === 'sales';
    }

    return false;
}
	public function delete(User $user, Order $order): bool
	{
        if ((int) ($order->inventory_issued ?? 0) === 1) return false;
        if ($order->current_department === 'completed') return false;
        if ($order->payments()->exists() || $order->debt()->exists()) return false;
        return $user->hasRole(['admin', 'super_admin']);
	}
	public function submit(User $user, Order $order): bool
	{
		return $user->hasRole(['sales', 'sales_manager', 'warehouse'])
		       && (int) $order->created_by === (int) $user->id
		       && $order->current_department === 'sales';
	}
	/**
	 * ✅ APPROVE: mỗi role duyệt được tất cả đơn ở bước của họ
	 */
	public function approve(User $user, Order $order): bool
	{
		if ($order->current_department == OrderDepartment::WAREHOUSE && (int)($order->inventory_issued ?? 0) === 0) {
			return false;
		}
		if ($order->current_department === 'completed') {
			return false;
		}
		if ($user->hasRole('admin')) {
			return true;
		}
		$map = [
			'sales_manager' => 'sales_manager',
			'accounting'    => 'accounting',
			'management'    => 'management',
			'warehouse'     => 'warehouse',
		];
		$dept = (string) $order->current_department;
		return isset($map[$dept]) && $user->hasRole($map[$dept]);
	}
	/**
	 * ✅ REJECT: mỗi role được reject tất cả đơn ở bước của họ
	 */
	public function reject(User $user, Order $order): bool
	{
		if ((int)($order->inventory_issued ?? 0) === 1) {
			return false;
		}
		if ($order->current_department === 'completed') {
			return false;
		}
//		if ($user->hasRole('admin')) {
//			return true;
//		}
		$map = [
			'sales_manager' => 'sales_manager',
			'accounting'    => 'accounting',
			'management'    => 'management',
			'warehouse'     => 'warehouse',
		];
		$dept = (string) $order->current_department;

		return isset($map[$dept]) && $user->hasRole($map[$dept]);
	}
	public function ship(User $user, Order $order): bool
{
    return $user->hasRole(['warehouse', 'admin'])
        && in_array($order->current_department, ['warehouse', 'kho'], true);
}

	public function recordPayment(User $user, Order $order): bool
	{
		// admin luôn được
		if ($user->hasRole(['admin', 'super_admin'])) return true;
		// ✅ Kế toán + Kho được ghi nhận thanh toán
    if ($user->hasRole(['accounting', 'ke_toan', 'kế toán', 'warehouse', 'kho'])) return true;
		// sales_manager được
		if ($user->hasRole('sales_manager')) return true;
		// sales chỉ được ghi nhận cho đơn do mình tạo (giữ logic cũ)
		if ($user->hasRole('sales')) {
			return (int) $order->created_by === (int) $user->id;
		}
		return false;
	}
	public function viewReports(User $user): bool
	{
		return $user->hasRole(['admin', 'management', 'sales_manager', 'accounting']);
	}
	public function export(User $user): bool
	{
		return $user->hasRole(['admin', 'management', 'accounting', 'warehouse', 'sales_manager']);
	}
	/**
	 * ✅ Warehouse duyệt xuất kho
	 */
	public function warehouseIssue(User $user, Order $order): bool
{
    if ($this->isCancelled($order)) return false;
    if (!$user->hasRole(['warehouse', 'admin'])) return false;
    if ((int)($order->inventory_issued ?? 0) === 1) return false;

    return in_array($order->current_department, ['warehouse', 'kho'], true);
}
	private function isCancelled(Order $order): bool
	{
		return (
			($order->status ?? null) === 'cancelled'
			|| ($order->current_status ?? null) === 'cancelled'
			|| ($order->current_department ?? null) === 'cancelled'
			|| ($order->approval_status ?? null) === 'cancelled'
		);
	}
	public function updateShippingInfo(User $user, Order $order): bool
{
    // Admin luôn được
    if ($user->hasRole(['admin', 'super_admin'])) return true;

    // Các role nội bộ được nhập
    if ($user->hasRole(['management', 'accounting', 'warehouse', 'sales_manager'])) return true;

    // Sales chỉ được nhập cho đơn của mình
    if ($user->hasRole('sales')) {
        return (int) $order->created_by === (int) $user->id;
    }

    return false;
}
}
