<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Core\Warehouse;
use App\Models\CRM\Consignments\CustomerConsignment;
use App\Models\CRM\Customers\Customer;
use App\Models\Inventory\Catalog\Product;
use App\Services\Consignment\CustomerConsignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class CustomerConsignmentController extends Controller
{
    public function __construct(private readonly CustomerConsignmentService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->requirePermission('consignments.view');

        $query = CustomerConsignment::query()
            ->with(['customer', 'salesUser', 'creator', 'consignmentWarehouse', 'items'])
            ->withSum('items as total_quantity', 'quantity')
            ->withSum('items as issued_quantity', 'issued_quantity');
        $this->applyUserScope($query);

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('salesUser', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $consignments = $query->latest('id')->paginate(20)->withQueryString();

        $statsQuery = CustomerConsignment::query();
        $this->applyUserScope($statsQuery);
        $stats = [
            'pending_approval' => (clone $statsQuery)->where('status', CustomerConsignment::STATUS_PENDING_APPROVAL)->count(),
            'waiting_warehouse' => (clone $statsQuery)->where('status', CustomerConsignment::STATUS_APPROVED)->count(),
            'active' => (clone $statsQuery)->where('status', CustomerConsignment::STATUS_ACTIVE)->count(),
            'overdue' => (clone $statsQuery)
                ->where('status', CustomerConsignment::STATUS_ACTIVE)
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '<', now()->toDateString())
                ->count(),
        ];

        return view('customer_consignments.index', compact('consignments', 'stats'));
    }
    public function process(): View
    {
        $this->requirePermission('consignments.view');

        return view('customer_consignments.process');
    }


    public function create(): View
    {
        $this->requirePermission('consignments.create');
        return view('customer_consignments.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('consignments.create');
        $data = $this->validateForm($request);
        $data['company_id'] = $this->companyId();
        $submit = (string) $request->input('action') === 'submit';

        try {
            $consignment = $this->service->createDirect($data, $submit);
            return redirect()->route('customer-consignments.show', $consignment)
                ->with('success', $submit
                    ? 'Đã tạo đơn '.$consignment->code.' và gửi Admin/Giám đốc duyệt.'
                    : 'Đã lưu nháp đơn ký gửi '.$consignment->code.'.');
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(CustomerConsignment $customerConsignment): View
    {
        $this->requirePermission('consignments.create');
        $this->assertConsignmentAccess($customerConsignment);
        abort_unless(in_array($customerConsignment->status, [
            CustomerConsignment::STATUS_DRAFT,
            CustomerConsignment::STATUS_REVISION_REQUESTED,
        ], true), 403, 'Đơn không còn được phép chỉnh sửa.');

        $customerConsignment->load(['customer', 'items.product', 'items.sourceWarehouse']);
        return view('customer_consignments.create', [
            ...$this->formOptions(),
            'editing' => $customerConsignment,
        ]);
    }

    public function update(Request $request, CustomerConsignment $customerConsignment): RedirectResponse
    {
        $this->requirePermission('consignments.create');
        $this->assertConsignmentAccess($customerConsignment);
        $data = $this->validateForm($request);
        $data['company_id'] = $this->companyId();
        $submit = (string) $request->input('action') === 'submit';

        try {
            $this->service->updateDraft($customerConsignment, $data, $submit);
            return redirect()->route('customer-consignments.show', $customerConsignment)
                ->with('success', $submit ? 'Đã cập nhật và gửi lại đơn chờ duyệt.' : 'Đã cập nhật bản nháp.');
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(CustomerConsignment $customerConsignment): View
    {
        $this->requirePermission('consignments.view');
        $this->assertConsignmentAccess($customerConsignment, allowWarehouse: true);
        $customerConsignment->load([
            'customer', 'salesUser', 'items.product', 'items.sourceWarehouse',
            'items.serials.serialUnit.primaryIdentifier', 'creator', 'submitter',
            'approver', 'rejecter', 'revisionRequester', 'warehouseConfirmer',
            'consignmentWarehouse', 'activities.user',
        ]);

        $availableSerials = [];
        if ($customerConsignment->status === CustomerConsignment::STATUS_APPROVED && $this->can('consignments.warehouse_issue')) {
            $availableSerials = $this->service->availableSerials($customerConsignment);
        }

        return view('customer_consignments.show', compact('customerConsignment', 'availableSerials'));
    }

    public function submit(CustomerConsignment $customerConsignment): RedirectResponse
    {
        $this->requirePermission('consignments.submit');
        $this->assertConsignmentAccess($customerConsignment);
        try {
            $this->service->submit($customerConsignment);
            return back()->with('success', 'Đã gửi đơn ký gửi chờ Admin/Giám đốc duyệt.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, CustomerConsignment $customerConsignment): RedirectResponse
    {
        $this->requirePermission('consignments.approve');
        $data = $request->validate(['note' => ['nullable', 'string', 'max:3000']]);
        try {
            $this->service->approve($customerConsignment, $data['note'] ?? null);
            return back()->with('success', 'Đã phê duyệt. Kho đã được phép chuẩn bị và xuất hàng ký gửi.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }

    public function requestRevision(Request $request, CustomerConsignment $customerConsignment): RedirectResponse
    {
        $this->requirePermission('consignments.review');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:3000']]);
        try {
            $this->service->requestRevision($customerConsignment, $data['reason']);
            return back()->with('success', 'Đã trả đơn cho Sales chỉnh sửa.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, CustomerConsignment $customerConsignment): RedirectResponse
    {
        $this->requirePermission('consignments.review');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:3000']]);
        try {
            $this->service->reject($customerConsignment, $data['reason']);
            return back()->with('success', 'Đã từ chối đơn ký gửi.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }

    public function warehouseIssue(Request $request, CustomerConsignment $customerConsignment): RedirectResponse
    {
        $this->requirePermission('consignments.warehouse_issue');
        $data = $request->validate([
            'issue_date' => ['required', 'date'],
            'issue_note' => ['nullable', 'string', 'max:3000'],
            'serials' => ['nullable', 'array'],
            'serials.*' => ['nullable', 'array'],
            'serials.*.*' => ['integer'],
        ]);
        try {
            $this->service->issueFromWarehouse(
                $customerConsignment,
                (array) ($data['serials'] ?? []),
                $data['issue_date'],
                $data['issue_note'] ?? null
            );
            return back()->with('success', 'Kho đã xuất hàng ký gửi cho khách. Không ghi nhận doanh thu bán hàng.');
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, CustomerConsignment $customerConsignment): RedirectResponse
    {
        $this->requirePermission('consignments.cancel');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:3000']]);
        try {
            $this->service->cancel($customerConsignment, $data['reason']);
            return back()->with('success', 'Đã hủy đơn ký gửi.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }

    private function validateForm(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:crm_customers,id'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'receiver_name' => ['nullable', 'string', 'max:150'],
            'receiver_phone' => ['nullable', 'string', 'max:50'],
            'shipping_address' => ['required', 'string', 'max:3000'],
            'terms_note' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:crm_product_catalog,id'],
            'items.*.source_warehouse_id' => ['required', 'integer', 'exists:crm_warehouses,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_note' => ['nullable', 'string', 'max:1000'],
            'action' => ['required', 'in:draft,submit'],
        ]);
    }

    private function formOptions(): array
    {
        $companyId = $this->companyId();
        $user = auth()->user();

        $customersQuery = Customer::query()->orderBy('name');
        if ($user && method_exists($customersQuery->getModel(), 'scopeVisibleToUser')) {
            $customersQuery->visibleToUser($user);
        }
        $customers = $customersQuery->limit(800)->get(['id', 'name', 'phone', 'email', 'address', 'owner_id']);

        $warehouses = Warehouse::query()
            ->where('company_id', $companyId)
            ->when(Schema::hasColumn('crm_warehouses', 'warehouse_type'), fn ($q) => $q->where('warehouse_type', '!=', 'customer_consignment'))
            ->when(Schema::hasColumn('crm_warehouses', 'is_sales_selectable'), fn ($q) => $q->where('is_sales_selectable', 1))
            ->orderBy('name')
            ->get(['id', 'name', 'location']);

        $products = Product::query()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'price', 'price_agent', 'price_retail', 'is_serialized']);

        $stockQuery = DB::table('crm_product_stock')->whereIn('warehouse_id', $warehouses->pluck('id'));
        if (Schema::hasColumn('crm_product_stock', 'company_id')) {
            $stockQuery->where('company_id', $companyId);
        }
        $stockMap = [];
        foreach ($stockQuery->get(['product_id', 'warehouse_id', 'qty']) as $row) {
            $stockMap[(int) $row->product_id][(int) $row->warehouse_id] = (int) $row->qty;
        }

        return compact('customers', 'warehouses', 'products', 'stockMap');
    }

    private function companyId(): int
    {
        if (class_exists(\App\Support\EgoCompanyLock::class)) {
            return (int) \App\Support\EgoCompanyLock::id();
        }
        return (int) (auth()->user()->company_id ?? 2);
    }

    private function requirePermission(string $permission): void
    {
        abort_unless($this->can($permission), 403, 'Bạn không có quyền thực hiện chức năng này.');
    }

    private function can(string $permission): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) return true;
        return (bool) $user->can($permission);
    }

    private function applyUserScope($query): void
    {
        $user = auth()->user();
        if (! $user) return;
        if ($this->isSalesOnly()) {
            $query->where('sales_user_id', $user->id);
        }
    }

    private function assertConsignmentAccess(CustomerConsignment $consignment, bool $allowWarehouse = false): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        if ($allowWarehouse && $this->can('consignments.warehouse_issue')) return;
        if ($this->can('consignments.approve') || $this->can('consignments.review')) return;
        if ($this->isSalesOnly()) {
            abort_unless((int) $consignment->sales_user_id === (int) $user->id, 403);
        }
    }

    private function isSalesOnly(): bool
    {
        $user = auth()->user();
        if (! $user || ! method_exists($user, 'hasRole')) return false;
        return $user->hasRole('sales') && ! $user->hasAnyRole(['admin', 'management', 'manager', 'director', 'giam_doc', 'ceo', 'sales_manager', 'warehouse', 'kho']);
    }
}
