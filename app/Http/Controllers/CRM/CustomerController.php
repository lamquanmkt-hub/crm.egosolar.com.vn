<?php

namespace App\Http\Controllers\CRM;

use App\Contracts\Services\CustomerServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Core\Region;
use App\Models\CRM\Customers\Customer;
use App\Models\CRM\Customers\CustomerType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerServiceInterface $customerService
    ) {
        $this->middleware('auth');
        $this->authorizeResource(Customer::class, 'customer');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $filters = request()->only([
            'search',
            'region_id',
            'customer_type_id',
            'customer_status',
            'owner_id',
            'is_potential',
            'has_order',
        ]);

        return view('customers.index', [
            'customers' => $this->customerService->search($filters),
            'regions' => Region::all(),
            'customerTypes' => CustomerType::all(),
            'users' => User::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * Note: Using popup form via ajaxForm method
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('customers.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        $this->customerService->create($request->validated());

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Thêm khách hàng thành công',
            ]);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', 'Thêm khách hàng thành công');
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): View
    {
        $customerDetail = $this->customerService->findWithDetails($customer->id);

        return view('customers.show', [
            'customer' => $customerDetail,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * Note: Using popup form via ajaxForm method
     */
    public function edit(Customer $customer): RedirectResponse
    {
        return redirect()->route('customers.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $this->customerService->update($customer->id, $request->validated());

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Cập nhật khách hàng thành công',
            ]);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', 'Cập nhật khách hàng thành công');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->customerService->delete($customer->id);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Đã xóa khách hàng');
    }

    public function ajaxForm(?string $id = null)
    {
        $customer = $id ? $this->customerService->find($id) : null;
        $regions = Region::all();
        $customerTypes = CustomerType::all();
        $users = User::all();

        return view('customers._form', compact('customer', 'regions', 'customerTypes', 'users'));
    }

    /**
     * Search customers
     */
    public function search()
    {
        $filters = request()->only([
            'search',
            'region_id',
            'customer_type_id',
            'customer_status',
            'owner_id',
            'is_potential',
        ]);

        $query = Customer::query();
        $user = auth()->user();

        if ($user->hasRole('sales')) {
            $query->where('owner_id', $user->id);
        } elseif (! empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query->paginate(20);
    }

    /**
     * Lấy nhanh thông tin hoá đơn của khách hàng
     * Dùng cho trang tạo đơn hàng khi chọn khách
     */
    public function invoiceInfo(Customer $customer): JsonResponse
    {
        return response()->json([
            'id' => $customer->id,
            'billing_company_name' => $customer->billing_company_name,
            'billing_tax_code' => $customer->billing_tax_code,
            'billing_address' => $customer->billing_address,
            'billing_email' => $customer->billing_email,
        ]);
    }

    /**
     * Cập nhật nhanh thông tin hoá đơn của khách hàng
     * Dùng cho nút "Lưu thông tin hoá đơn" ở trang tạo đơn
     */
    public function updateBillingInfo(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'billing_company_name' => ['nullable', 'string', 'max:255'],
            'billing_tax_code' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'billing_email' => ['nullable', 'email', 'max:255'],
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Lưu thông tin hoá đơn khách hàng thành công',
            'data' => [
                'id' => $customer->id,
                'billing_company_name' => $customer->billing_company_name,
                'billing_tax_code' => $customer->billing_tax_code,
                'billing_address' => $customer->billing_address,
                'billing_email' => $customer->billing_email,
            ],
        ]);
    }
}
