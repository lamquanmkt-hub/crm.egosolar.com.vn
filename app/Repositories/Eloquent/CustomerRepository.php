<?php

namespace App\Repositories\Eloquent;

use App\Models\CRM\Customers\Customer;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository Eloquent thao tác dữ liệu khách hàng.
 */
class CustomerRepository implements CustomerRepositoryInterface
{
    /**
     * Lấy danh sách khách hàng kèm khu vực, phân trang.
     */
    public function all(): LengthAwarePaginator|array
    {
        return Customer::with('region')->latest()->paginate(20);
    }

    /**
     * Lấy danh sách khách hàng kèm đầy đủ quan hệ, phân trang.
     */
    public function getAllWithRelations(): LengthAwarePaginator
    {
        return Customer::query()
            ->with([
                'region',
                'customerType',
                'assignedUser', // Owner
                'latestLead.source',
                'latestOrder',
            ])
            ->latest('id')
            ->paginate(20);
    }

    /**
     * Tìm khách hàng theo ID.
     */
    public function find($id)
    {
        return Customer::findOrFail($id);
    }

    /**
     * Tìm khách hàng theo ID kèm đầy đủ quan hệ chi tiết.
     */
    public function findWithDetails($id)
    {
        return Customer::with([
            'region',
            'customerType',
            'assignedUser',
            'leads' => function ($query) {
                $query->with('source', 'status', 'assignedUser')->latest();
            },
            'orders' => function ($query) {
                $query->with('items')->latest();
            },
            'tags',
            // Loại bỏ 'creator' vì Customer ko có quan hệ này trực tiếp
        ])->findOrFail($id);
    }

    /**
     * Tìm kiếm khách hàng theo bộ lọc, có phân quyền theo user, phân trang.
     */
    public function search(array $filters = []): LengthAwarePaginator
    {
        $user = auth()->user();
        // 1. Query cơ bản & Phân quyền
        $query = Customer::query()
            ->visibleToUser($user) // Scope lọc theo owner_id nếu là sales
            ->with([
                'region',
                'customerType',
                'assignedUser',
                'latestLead.source',
            ]);
        // 2. Lọc theo từ khóa (Tên, SĐT, Email, Nickname)
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('facebook_name', 'like', "%{$search}%");
            });
        }
        // 3. Các bộ lọc chính xác
        if (! empty($filters['region_id'])) {
            $query->where('region_id', $filters['region_id']);
        }
        if (! empty($filters['customer_type_id'])) {
            $query->where('customer_type_id', $filters['customer_type_id']);
        }
        if (! empty($filters['customer_status'])) {
            $query->where('customer_status', $filters['customer_status']);
        }
        // Lọc khách đã mua hàng (có đơn)
        if (isset($filters['has_order']) && $filters['has_order'] !== '') {
            if ($filters['has_order'] == '1') {
                $query->whereHas('orders');
            } elseif ($filters['has_order'] == '0') {
                $query->whereDoesntHave('orders');
            }
        }
        // Chỉ Admin/Manager mới được lọc theo Owner (Sales chỉ thấy của mình)
        if (! empty($filters['owner_id']) && $user->can('customer.view_all')) {
            $query->where('owner_id', $filters['owner_id']);
        }
        if (isset($filters['is_potential'])) {
            $query->where('is_potential', $filters['is_potential']);
        }

        return $query->latest('id')->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Tạo mới khách hàng.
     */
    public function create(array $data)
    {
        return Customer::create($data);
    }

    /**
     * Cập nhật khách hàng.
     */
    public function update($id, array $data): Customer|array
    {
        $customer = Customer::findOrFail($id);
        $customer->update($data);

        return $customer;
    }

    /**
     * Xoá khách hàng.
     */
    public function delete($id): int
    {
        return Customer::destroy($id);
    }

    /**
     * Đếm tổng số khách hàng.
     */
    public function count(): int
    {
        return Customer::count();
    }

    /**
     * Đếm số khách hàng đã từng mua hàng.
     */
    public function countPurchased(): int
    {
        return Customer::whereHas('orders')->count();
    }
}
