<?php

namespace App\Services;

use App\Models\CRM\Customers\Customer;
use App\Models\CRM\Leads\Lead;
use App\Repositories\Interfaces\CustomerRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    protected CustomerRepositoryInterface $customerRepo;

    public function __construct(CustomerRepositoryInterface $customerRepo)
    {
        $this->customerRepo = $customerRepo;
    }

    public function getAll(): mixed {
        return $this->customerRepo->getAllWithRelations();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $user = auth()->user();
            $userId = $user ? $user->id : null;

            // 1. Logic tự động gán Owner (người phụ trách)
            // Nếu không chọn owner, mặc định là người đang tạo (nếu là Sales)
            if (empty($data['owner_id']) || ($user && !$user->can('customer.view_all'))) {
                $data['owner_id'] = $userId;
            }

            // 2. Thiết lập trạng thái mặc định
            if (!isset($data['customer_status'])) {
                $data['customer_status'] = 'lead';
            }

            // 3. Tạo Customer
            // Lưu ý: Không truyền created_by vào đây vì bảng crm_customers không có cột này
            $customer = $this->customerRepo->create($data);

            // 4. Tạo Lead đầu tiên (Bảng crm_leads CÓ cột created_by [cite: 69])
            $customer->leads()->create([
                'source_id'    => $data['source_id'] ?? null,
                'assigned_to'  => $customer->owner_id, // Đồng bộ người phụ trách
                'contact_date' => now(),
                'status_id'    => 1, // ID trạng thái mặc định (Mới)
                'note'         => $data['initial_note'] ?? 'Khách hàng mới',
                'created_by'   => $userId, // Lưu người tạo tại đây
            ]);

            // 5. Xử lý Tags
            if (!empty($data['tags']) && is_array($data['tags'])) {
                $customer->tags()->sync($data['tags']);
            }

            return $customer;
        });
    }

    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $customer = $this->customerRepo->update($id, $data);

            if (isset($data['tags'])) {
                // Logic sync tags nếu cần
                // $customer->tags()->sync($data['tags']);
            }
            return $customer;
        });
    }

    public function delete($id)
    {
        return DB::transaction(fn() => $this->customerRepo->delete($id));
    }

    public function search(array $filters = [])
    {
        return $this->customerRepo->search($filters);
    }

    public function findWithDetails($id)
    {
        return $this->customerRepo->findWithDetails($id);
    }

    // Các hàm count giữ nguyên
    public function count() { return $this->customerRepo->count(); }
    public function countPurchased() { return $this->customerRepo->countPurchased(); }
    public function find($id) { return $this->customerRepo->find($id); }

    /* * Đã xóa hàm getBirthdaysThisMonth() vì DB không có cột birthday.
     * Nếu muốn dùng tính năng này, cần alter table thêm cột birthday.
     */

    // --- BUSINESS LOGIC ---

    public function convertToMember($customerId, array $membershipData = [])
    {
        return DB::transaction(function () use ($customerId, $membershipData) {
            $customer = $this->customerRepo->find($customerId);
            $customer->update([
                'customer_status' => 'member',
                'converted_to_member_at' => now(),
            ]);

            if (!empty($membershipData)) {
                $customer->memberships()->create($membershipData);
            }
            return $customer;
        });
    }

    public function getCustomersForSelect(): Collection
    {
        // Lấy danh sách khách hàng gọn nhẹ cho Dropdown
        // Lấy danh sách khách hàng gọn nhẹ cho Dropdown
        $query = Customer::query()
            ->select('crm_customers.*') // Lấy hết cột bảng customer
            // Subquery lấy ID lead mới nhất
            ->addSelect([
                'latest_lead_id' => Lead::select('id')
                    ->whereColumn('customer_id', 'crm_customers.id')
                    ->latest('created_at')
                    ->limit(1)
            ])
            ->with(['customerType:id,name', 'region:id,name']);

        // Nếu user là sales, chỉ lấy khách hàng của user đó
        $user = auth()->user();
        if ($user && !$this->hasFullCustomerAccess($user) && $user->hasRole('sales')) {
            $query->where('crm_customers.owner_id', $user->id);
        }

        return $query
            ->orderBy('name')
            ->limit(50) // Giới hạn số lượng để không lag FE
            ->get()
            ->map(function($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'display' => $customer->name . ' - ' . $customer->phone,
                    'lead_id' => $customer->latest_lead_id,
                    'type' => $customer->customerType->name ?? '',
                ];
            });
    }
    public function searchCustomersForOrderSelect(string $term, int $limit = 30): array
{
    $user = auth()->user();

    $term = trim($term);
    if ($term === '') return []; // ✅ không gõ thì không trả về gì

    $query = Customer::query()
        ->select('crm_customers.id', 'crm_customers.name', 'crm_customers.phone', 'crm_customers.owner_id')
        ->addSelect([
            'latest_lead_id' => Lead::select('id')
                ->whereColumn('customer_id', 'crm_customers.id')
                ->latest('created_at')
                ->limit(1)
        ]);

    // ✅ Không có quyền view_all => chỉ thấy khách của mình
    if ($user && !$this->hasFullCustomerAccess($user)) {
        $query->where('crm_customers.owner_id', $user->id);
    }

    $query->where(function ($q) use ($term) {
        $q->where('crm_customers.name', 'like', "%{$term}%")
          ->orWhere('crm_customers.phone', 'like', "%{$term}%");
    });

    $customers = $query
        ->orderBy('crm_customers.name')
        ->limit($limit)
        ->get();

    return $customers->map(function ($c) {
        return [
            'id'      => (int) $c->id,
            'text'    => trim($c->name . ($c->phone ? ' - ' . $c->phone : '')),
            'lead_id' => (int) ($c->latest_lead_id ?? 0),
        ];
    })->values()->all();
}

public function getCustomerOptionForOrderSelect(int $customerId): ?array
{
    $user = auth()->user();

    $c = Customer::query()
        ->select('crm_customers.id', 'crm_customers.name', 'crm_customers.phone', 'crm_customers.owner_id')
        ->addSelect([
            'latest_lead_id' => Lead::select('id')
                ->whereColumn('customer_id', 'crm_customers.id')
                ->latest('created_at')
                ->limit(1)
        ])
        ->where('crm_customers.id', $customerId)
        ->first();

    if (!$c) return null;

    if ($user && !$this->hasFullCustomerAccess($user) && (int)$c->owner_id !== (int)$user->id) {
        return null;
    }

    return [
        'id'      => (int) $c->id,
        'text'    => trim($c->name . ($c->phone ? ' - ' . $c->phone : '')),
        'lead_id' => (int) ($c->latest_lead_id ?? 0),
    ];
}

    private function hasFullCustomerAccess($user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->can('customer.view_all')) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['kho', 'warehouse', 'admin'])) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('kho') || $user->hasRole('warehouse') || $user->hasRole('admin');
        }

        $roleText = strtolower(implode(' ', array_filter([
            $user->role ?? null,
            $user->role_name ?? null,
            $user->department ?? null,
            $user->position ?? null,
            $user->type ?? null,
        ])));

        return str_contains($roleText, 'kho')
            || str_contains($roleText, 'warehouse')
            || str_contains($roleText, 'admin');
    }


}
