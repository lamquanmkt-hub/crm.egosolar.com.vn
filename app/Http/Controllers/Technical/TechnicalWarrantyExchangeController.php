<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SolarWarrantyClaim;
use App\Models\SolarWarrantyStockMovement;
use App\Services\Synced\Technical\SolarMaintenanceQueryService;
use App\Services\Technical\SolarWarrantyQueryService;
use App\Support\SchemaCache;
use App\Support\SolarMaintenanceAccess;
use App\Support\Synced\EgoCompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TechnicalWarrantyExchangeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request);
        $this->ensureReady();

        $user = $request->user();
        $query = $this->baseClaimQuery($user)
            ->with([
                'site:id,name,project_code,contact_name,contact_phone,address,company_id',
                'order:id,order_code,order_date,company_id,lead_id',
                'assignee:id,name',
                'creator:id,name',
                'approver:id,name',
            ]);

        $keyword = trim((string) $request->query('q', ''));
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $query->where(function (Builder $filter) use ($like): void {
                $filter->where('claim_code', 'like', $like)
                    ->orWhere('serial_code', 'like', $like)
                    ->orWhere('replacement_serial_code', 'like', $like)
                    ->orWhere('issue_description', 'like', $like)
                    ->orWhere('diagnosis', 'like', $like)
                    ->orWhere('proposed_solution', 'like', $like)
                    ->orWhereHas('site', function (Builder $site) use ($like): void {
                        $site->where('name', 'like', $like)
                            ->orWhere('project_code', 'like', $like)
                            ->orWhere('contact_name', 'like', $like)
                            ->orWhere('contact_phone', 'like', $like);
                    })
                    ->orWhereHas('order', fn (Builder $order) => $order->where('order_code', 'like', $like));
            });
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && array_key_exists($status, \App\Support\Warranty\WarrantyFlow::EXCHANGE_STATUSES)) {
            $query->where('status', $status);
        }

        $priority = trim((string) $request->query('priority', ''));
        if ($priority !== '' && array_key_exists($priority, SolarWarrantyClaim::PRIORITIES)) {
            $query->where('priority', $priority);
        }

        if ($assigned = (int) $request->query('assigned_to', 0)) {
            $query->where('assigned_to', $assigned);
        }
        if ($from = trim((string) $request->query('from', ''))) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = trim((string) $request->query('to', ''))) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($siteId = (int) $request->query('site_id', 0)) {
            $query->where('site_id', $siteId);
        }
        if ($customer = trim((string) $request->query('customer', ''))) {
            $query->whereIn('customer_id', DB::table('crm_customers')->where('name', 'like', '%'.$customer.'%')->select('id'));
        }
        if ($productId = (int) $request->query('product_id', 0)) {
            $query->whereIn('serial_unit_id', DB::table('crm_serial_units')->where('product_id', $productId)->select('id'));
        }
        if ($warehouseId = (int) $request->query('warehouse_id', 0)) {
            $query->whereIn('id', DB::table('solar_warranty_stock_movements')->where('warehouse_id', $warehouseId)->select('warranty_claim_id'));
        }

        $bucket = trim((string) $request->query('bucket', ''));
        if ($bucket === 'pending_approval') {
            $query->where('status', 'pending_approval');
        } elseif ($bucket === 'warehouse') {
            $query->whereIn('status', ['approved', 'waiting_stock', 'reserved', 'waiting_faulty_return']);
        } elseif ($bucket === 'processing') {
            $query->whereIn('status', ['issued', 'technician_received', 'replacing', 'waiting_customer']);
        } elseif ($bucket === 'completed') {
            $query->where('status', 'completed');
        } elseif ($bucket === 'open') {
            $query->whereNotIn('status', ['completed', 'rejected', 'cancelled']);
        }

        $claims = $query
            ->orderByRaw("CASE WHEN priority='urgent' THEN 0 WHEN priority='high' THEN 1 WHEN priority='normal' THEN 2 ELSE 3 END")
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $serialMap = $this->serialDetailsForClaims(collect($claims->items()));
        foreach ($claims as $claim) {
            $claim->device = $serialMap->get((int) $claim->serial_unit_id);
        }

        $summary = $this->summary($user);
        $sites = $this->sitesForCompany();
        $orders = $this->ordersWithSerialsForCompany();
        $technicians = app(SolarMaintenanceQueryService::class)->technicalUsers();

        return view('technical.warranty-exchange.index', [
            'claims' => $claims,
            'summary' => $summary,
            'sites' => $sites,
            'orders' => $orders,
            'technicians' => $technicians,
            'statuses' => \App\Support\Warranty\WarrantyFlow::EXCHANGE_STATUSES,
            'priorities' => SolarWarrantyClaim::PRIORITIES,
            'canCreate' => SolarMaintenanceAccess::canCreateWarrantyClaim($user),
            'isManager' => SolarMaintenanceAccess::isManager($user),
            'canRequestException' => SolarMaintenanceAccess::canCreateWarrantyClaim($user),
            'isTechnicianOnly' => SolarMaintenanceAccess::isTechnicianOnly($user),
            'products' => DB::table('crm_product_catalog')->orderBy('name')->limit(400)->get(['id', 'name']),
            'warehousesList' => DB::table('crm_warehouses')->orderBy('name')->get(['id', 'name']),
            'flowStatuses' => \App\Support\Warranty\WarrantyFlow::EXCHANGE_STATUSES,
        ]);
    }

    /** Danh sách việc dành riêng cho Kho (đổi hàng + linh kiện sửa chữa) + thông báo chưa đọc. */
    public function warehouseQueue(Request $request): View
    {
        $user = $request->user();
        abort_unless(SolarMaintenanceAccess::isWarehouse($user) || SolarMaintenanceAccess::isTechnicalLead($user), 403);
        $this->ensureReady();

        $company = EgoCompanyScope::currentId();
        $scope = function ($q) use ($company): void {
            if ($company > 0) {
                $q->where(fn ($w) => $w->where('company_id', $company)->orWhereNull('company_id'));
            }
        };

        $exchange = SolarWarrantyClaim::query()->where('claim_type', 'replacement')->tap($scope)
            ->where(function ($q): void {
                $q->whereIn('status', ['waiting_stock', 'reserved', 'waiting_faulty_return'])
                    ->orWhere(fn ($x) => $x->where('status', 'completed')->where('faulty_return_status', 'deferred'));
            })->orderByRaw("CASE status WHEN 'waiting_stock' THEN 0 WHEN 'reserved' THEN 1 ELSE 2 END")->orderBy('status_changed_at')->limit(200)->get();

        $repair = SolarWarrantyClaim::query()->where('claim_type', 'paid_repair')->tap($scope)
            ->whereIn('status', ['approved_for_repair', 'waiting_parts', 'repairing', 'qa_testing', 'qa_failed', 'ready_handover', 'handed_over'])
            ->whereIn('id', DB::table('warranty_repair_parts')->whereIn('status', ['planned', 'reserved', 'issued'])->select('claim_id'))
            ->orderBy('status_changed_at')->limit(200)->get();

        $notes = DB::table('warranty_claim_notifications')->where('audience', 'warehouse')->where('is_read', false)->orderByDesc('id')->limit(30)->get();
        if ($request->boolean('read')) {
            DB::table('warranty_claim_notifications')->where('audience', 'warehouse')->where('is_read', false)->update(['is_read' => true, 'updated_at' => now()]);
        }

        return view('technical.warranty-exchange.warehouse', [
            'exchange' => $exchange, 'repair' => $repair, 'notes' => $notes,
            'exchangeStatuses' => \App\Support\Warranty\WarrantyFlow::EXCHANGE_STATUSES,
            'repairStatuses' => \App\Support\Warranty\WarrantyFlow::REPAIR_STATUSES,
        ]);
    }
    public function store(Request $request): RedirectResponse
    {
        abort_unless(SolarMaintenanceAccess::canCreateWarrantyClaim($request->user()), 403);
        $this->ensureReady();

        $data = $request->validate([
            'source_type' => ['required', Rule::in(['site', 'order'])],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'order_id' => ['nullable', 'integer', 'exists:crm_orders,id'],
            'serial_code' => ['required', 'string', 'max:190'],
            'priority' => ['required', Rule::in(array_keys(SolarWarrantyClaim::PRIORITIES))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'issue_description' => ['required', 'string', 'max:10000'],
            'diagnosis' => ['required', 'string', 'max:10000'],
            'proposed_solution' => ['required', 'string', 'max:10000'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'warranty_exception' => ['nullable', 'boolean'],
            'exception_reason' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array', 'max:8'],
            'evidence.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'source_type.required' => 'Vui lòng chọn nguồn thiết bị: Công trình hoặc Đơn hàng.',
            'serial_code.required' => 'Vui lòng chọn/nhập serial thiết bị lỗi.',
            'issue_description.required' => 'Vui lòng mô tả lỗi/hiện tượng thực tế.',
            'diagnosis.required' => 'Vui lòng nhập kết quả chẩn đoán của kỹ thuật.',
            'proposed_solution.required' => 'Vui lòng nhập lý do và phương án đề xuất đổi.',
            'evidence.*.mimes' => 'Minh chứng chỉ nhận JPG, PNG, WEBP hoặc PDF.',
            'evidence.*.max' => 'Mỗi tệp minh chứng tối đa 20MB.',
        ]);

        $sourceType = (string) $data['source_type'];
        $site = null;
        $order = null;
        $companyId = EgoCompanyScope::currentId();

        if ($sourceType === 'site') {
            if (empty($data['site_id'])) {
                throw ValidationException::withMessages([
                    'site_id' => 'Vui lòng chọn công trình cần đổi hàng bảo hành.',
                ]);
            }

            $site = Site::withoutGlobalScopes()->findOrFail((int) $data['site_id']);
            $companyId = (int) ($site->company_id ?: $companyId);
            $this->assertCompany($companyId, $request);
        } else {
            if (empty($data['order_id'])) {
                throw ValidationException::withMessages([
                    'order_id' => 'Vui lòng chọn đơn hàng có serial cần bảo hành.',
                ]);
            }

            $order = $this->findOrderForExchange((int) $data['order_id']);
            $companyId = (int) ($order->company_id ?: $companyId);
            $this->assertCompany($companyId, $request);
        }

        $serial = $this->findSerial(trim((string) $data['serial_code']));
        $this->assertSerialCompany($serial, $companyId, $request);

        if ($sourceType === 'order') {
            $this->assertSerialBelongsToOrder($serial, (int) $order->id);
        }

        if ($sourceType === 'site'
            && (int) ($serial->warranty_site_id ?? 0) > 0
            && (int) $serial->warranty_site_id !== (int) $site->id) {
            throw ValidationException::withMessages([
                'site_id' => 'Serial này đang được gắn với công trình khác trong hồ sơ bảo hành. Vui lòng kiểm tra lại công trình/serial.',
            ]);
        }

        $resolvedSiteId = $site?->id ?: ((int) ($serial->warranty_site_id ?? 0) ?: null);
        $resolvedOrderId = $sourceType === 'order'
            ? (int) $order->id
            : ((int) ($serial->order_id ?? 0) ?: null);
        $resolvedCustomerId = (int) ($serial->customer_id ?? 0);
        if ($resolvedCustomerId <= 0 && $order) {
            $resolvedCustomerId = (int) ($order->customer_id ?? 0);
        }

        // Serial chưa gắn hồ sơ bảo hành với công trình được chọn = thiếu dữ liệu liên kết → phải qua ngoại lệ.
        $linkMissing = $sourceType === 'site' && (int) ($serial->warranty_site_id ?? 0) === 0;
        $warrantyActive = $this->isWarrantyActive($serial) && ! $linkMissing;
        if (! $warrantyActive && ! (bool) ($data['warranty_exception'] ?? false)) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial không có bảo hành còn hiệu lực. Nếu cần xử lý ngoại lệ, hãy tích “Đề nghị ngoại lệ bảo hành” và nhập lý do — phiếu sẽ cần người có thẩm quyền KHÁC duyệt.',
            ]);
        }

        $assignee = $this->resolveAssignee($request, $data['assigned_to'] ?? null, $companyId);

        try {
            $claim = app(\App\Services\Warranty\WarrantyExchangeService::class)->create($request->user(), $data, [
                'serial' => $serial,
                'source_type' => $sourceType,
                'site_id' => $resolvedSiteId,
                'order_id' => $resolvedOrderId,
                'customer_id' => $resolvedCustomerId,
                'company_id' => $companyId,
                'warranty_active' => $warrantyActive,
                'assignee_id' => $assignee?->id,
                'assignee_name' => $assignee?->name,
            ]);
        } catch (\App\Support\Warranty\WarrantyException $e) {
            throw ValidationException::withMessages(['serial_code' => $e->getMessage()]);
        }
        $this->storeEvidence($request, $claim);

        return redirect()->route('ky-thuat.warranty-exchange.show', ['claim' => $claim->id])
            ->with('success', 'Đã tạo '.$claim->claim_code.' và gửi duyệt đề xuất đổi hàng bảo hành.');
    }

    public function show(Request $request, SolarWarrantyClaim $claim): View
    {
        $this->authorizeClaim($request, $claim);
        $this->ensureReady();

        $user = $request->user();
        $claim->load([
            'site:id,name,project_code,contact_name,contact_phone,address,company_id',
            'order:id,order_code,order_date,company_id,lead_id',
            'assignee:id,name',
            'creator:id,name',
            'approver:id,name',
        ]);

        $device = $this->findSerialByUnitId((int) $claim->serial_unit_id);
        $warehouses = app(SolarWarrantyQueryService::class)->warehouses($user);
        $link = SchemaCache::hasTable('warranty_serial_replacements')
            ? DB::table('warranty_serial_replacements')->where('claim_id', $claim->id)->first() : null;
        $reservation = SchemaCache::hasTable('warranty_serial_reservations')
            ? DB::table('warranty_serial_reservations')->where('claim_id', $claim->id)->where('status', 'active')->first() : null;
        $service = app(\App\Services\Warranty\WarrantyExchangeService::class);
        $status = (string) $claim->status;
        $isLead = SolarMaintenanceAccess::isTechnicalLead($user);
        $isWarehouse = SolarMaintenanceAccess::canHandleWarrantyStock($user);
        $isAssigned = $isLead || ((int) $claim->assigned_to === (int) $user->id && SolarMaintenanceAccess::isTechnician($user));
        $self = in_array((int) $user->id, array_filter([(int) $claim->created_by, (int) $claim->assigned_to, (int) $claim->exception_requested_by]), true);

        $can = [
            'approve' => $isLead && $status === 'pending_approval',
            'approve_blocked_self' => $isLead && $status === 'pending_approval' && $self && ! SolarMaintenanceAccess::canOverrideWarranty($user),
            'override' => SolarMaintenanceAccess::canOverrideWarranty($user),
            'resubmit' => $status === 'needs_more_information' && ($isLead || $self),
            'reopen' => $isLead && $status === 'rejected',
            'cancel' => in_array($status, ['pending_approval', 'needs_more_information', 'waiting_stock', 'reserved'], true)
                && ($isLead || ((int) $claim->created_by === (int) $user->id && in_array($status, ['pending_approval', 'needs_more_information'], true))),
            'reserve' => $isWarehouse && in_array($status, ['waiting_stock', 'reserved'], true),
            'release' => $isWarehouse && $status === 'reserved',
            'issue' => $isWarehouse && $status === 'reserved',
            'faulty_return' => $isWarehouse && ($status === 'waiting_faulty_return' || ($status === 'completed' && $claim->faulty_return_status === 'deferred')),
            'tech_receive' => $isAssigned && $status === 'issued',
            'replace' => $isAssigned && in_array($status, ['technician_received', 'replacing'], true),
            'defer_return' => $isLead && $status === 'waiting_faulty_return' && $claim->faulty_return_status !== 'deferred' && $claim->faulty_return_status !== 'returned',
            'complete' => $isLead && in_array($status, ['faulty_returned', 'waiting_faulty_return'], true),
            'evidence' => (SolarMaintenanceAccess::isManager($user) || $isAssigned) && \App\Support\Warranty\WarrantyFlow::isOpen($status),
        ];

        return view('technical.warranty-exchange.show', [
            'claim' => $claim,
            'device' => $device,
            'attachments' => $this->canViewEvidence($user) ? $this->attachments($claim) : collect(),
            'warehouses' => $warehouses,
            'replacementCandidates' => $can['reserve'] ? $this->replacementCandidates($device, $warehouses) : collect(),
            'statuses' => \App\Support\Warranty\WarrantyFlow::EXCHANGE_STATUSES,
            'priorities' => SolarWarrantyClaim::PRIORITIES,
            'timeline' => \App\Support\Warranty\WarrantyFlow::exchangeTimeline($claim, (bool) $link),
            'checklist' => $service->checklist($claim),
            'history' => \App\Services\Warranty\WarrantyAudit::history((int) $claim->id),
            'link' => $link,
            'reservation' => $reservation,
            'movements' => DB::table('solar_warranty_stock_movements as m')->leftJoin('crm_warehouses as w', 'w.id', '=', 'm.warehouse_id')
                ->leftJoin('users as u', 'u.id', '=', 'm.completed_by')->where('m.warranty_claim_id', $claim->id)->whereNull('m.deleted_at')
                ->orderByDesc('m.id')->get(['m.*', 'w.name as warehouse_name', 'u.name as completed_by_name']),
            'priorClaims' => DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $claim->serial_unit_id)->where('id', '<>', $claim->id)->whereNull('deleted_at')
                ->orderByDesc('id')->limit(10)->get(['id', 'claim_code', 'claim_type', 'status', 'created_at']),
            'can' => $can,
            'canViewCosts' => SolarMaintenanceAccess::canViewMaintenanceCosts($user),
            'canViewInternal' => SolarMaintenanceAccess::canViewTechnicalInternal($user),
            'faultyConditions' => \App\Support\Warranty\WarrantyFlow::FAULTY_CONDITIONS,
            'userNames' => DB::table('users')->whereIn('id', array_filter([
                $claim->issued_by, $claim->tech_received_by, $claim->replaced_by, $claim->faulty_received_by,
                $claim->exception_requested_by, $claim->exception_approved_by, $claim->decision_by, $claim->closed_by,
            ]))->pluck('name', 'id'),
        ]);
    }
    public function serialInfo(Request $request): JsonResponse
    {
        $this->authorizeView($request);
        $this->ensureSerialReady();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:190'],
        ]);

        try {
            $serial = $this->findSerial(trim((string) $data['code']));
            $companyId = EgoCompanyScope::currentId();
            $this->assertSerialCompany($serial, $companyId, $request);

            return response()->json([
                'ok' => true,
                'serial' => [
                    'serial_unit_id' => (int) $serial->serial_unit_id,
                    'serial_code' => (string) $serial->serial_code,
                    'product_id' => (int) $serial->product_id,
                    'product_name' => (string) ($serial->product_name ?: 'Chưa xác định sản phẩm'),
                    'sku' => (string) ($serial->sku ?: ''),
                    'state' => (string) ($serial->current_state ?: 'unknown'),
                    'customer_id' => $serial->customer_id ? (int) $serial->customer_id : null,
                    'customer_name' => (string) ($serial->customer_name ?: ''),
                    'order_id' => $serial->order_id ? (int) $serial->order_id : null,
                    'order_code' => (string) ($serial->order_code ?: ''),
                    'warranty_status' => (string) ($serial->warranty_status ?: ''),
                    'warranty_start_at' => $serial->warranty_start_at,
                    'warranty_end_at' => $serial->warranty_end_at,
                    'warranty_active' => $this->isWarrantyActive($serial),
                    'warranty_site_id' => $serial->warranty_site_id ? (int) $serial->warranty_site_id : null,
                    'warranty_site_name' => (string) ($serial->warranty_site_name ?: ''),
                ],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'Không thể tra cứu serial.',
            ], 422);
        }
    }

    public function orderSerials(Request $request): JsonResponse
    {
        $this->authorizeView($request);
        $this->ensureSerialReady();

        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:crm_orders,id'],
        ]);

        try {
            $order = $this->findOrderForExchange((int) $data['order_id']);
            $companyId = (int) ($order->company_id ?: EgoCompanyScope::currentId());
            $this->assertCompany($companyId, $request);

            $serials = $this->serialsForOrder((int) $order->id)
                ->map(function ($serial): array {
                    return [
                        'serial_unit_id' => (int) $serial->serial_unit_id,
                        'serial_code' => (string) $serial->serial_code,
                        'product_id' => (int) $serial->product_id,
                        'product_name' => (string) ($serial->product_name ?: 'Chưa xác định sản phẩm'),
                        'sku' => (string) ($serial->sku ?: ''),
                        'state' => (string) ($serial->current_state ?: 'unknown'),
                        'customer_name' => (string) ($serial->customer_name ?: ''),
                        'warranty_status' => (string) ($serial->warranty_status ?: ''),
                        'warranty_start_at' => $serial->warranty_start_at,
                        'warranty_end_at' => $serial->warranty_end_at,
                        'warranty_active' => $this->isWarrantyActive($serial),
                        'warranty_site_id' => $serial->warranty_site_id ? (int) $serial->warranty_site_id : null,
                        'warranty_site_name' => (string) ($serial->warranty_site_name ?: ''),
                    ];
                })
                ->values();

            return response()->json([
                'ok' => true,
                'order' => [
                    'id' => (int) $order->id,
                    'order_code' => (string) $order->order_code,
                    'order_date' => (string) ($order->order_date ?: ''),
                    'customer_id' => (int) ($order->customer_id ?? 0) ?: null,
                    'customer_name' => (string) ($order->customer_name ?: ''),
                    'customer_phone' => (string) ($order->customer_phone ?: ''),
                ],
                'serials' => $serials,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'Không thể lấy serial từ đơn hàng.',
            ], 422);
        }
    }

    public function uploadEvidence(Request $request, SolarWarrantyClaim $claim): RedirectResponse
    {
        $this->authorizeClaimUpdate($request, $claim, true);

        $request->validate([
            'evidence' => ['required', 'array', 'min:1', 'max:'.(int) config('warranty.evidence_max_files', 8)],
            'evidence.*' => ['file', 'max:'.(int) config('warranty.evidence_max_kb', 20480)],
        ], [
            'evidence.required' => 'Vui lòng chọn ít nhất một tệp minh chứng.',
            'evidence.max' => 'Tối đa '.(int) config('warranty.evidence_max_files', 8).' tệp mỗi lần tải lên.',
            'evidence.*.max' => 'Mỗi tệp minh chứng tối đa 20MB.',
        ]);

        try {
            app(\App\Services\Warranty\EvidenceStore::class)->store((array) $request->file('evidence', []), $claim, $request->user(), $request->input('step'));
        } catch (\App\Support\Warranty\WarrantyException $e) {
            return back()->withErrors(['evidence' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã bổ sung minh chứng cho '.$claim->claim_code.'.');
    }

    public function downloadEvidence(Request $request, SolarWarrantyClaim $claim, int $attachment): BinaryFileResponse
    {
        $this->authorizeClaim($request, $claim, true);
        abort_unless($this->canViewEvidence($request->user()), 403);
        abort_unless(SchemaCache::hasTable('solar_warranty_claim_attachments'), 404);

        $store = app(\App\Services\Warranty\EvidenceStore::class);
        $row = $store->find($claim, $attachment);

        return response()->file($store->absolutePath($row), [
            'Content-Type' => (string) ($row->mime_type ?: 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="evidence-'.$row->id.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroyEvidence(Request $request, SolarWarrantyClaim $claim, int $attachment): RedirectResponse
    {
        $this->authorizeClaimUpdate($request, $claim, true);
        abort_unless(SchemaCache::hasTable('solar_warranty_claim_attachments'), 404);

        try {
            app(\App\Services\Warranty\EvidenceStore::class)->delete($claim, $attachment, $request->user(), $request->input('reason'));
        } catch (\App\Support\Warranty\WarrantyException $e) {
            return back()->withErrors(['evidence' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã gỡ minh chứng khỏi phiếu.');
    }

    protected function canViewEvidence($user): bool
    {
        return SolarMaintenanceAccess::canViewTechnicalInternal($user) || SolarMaintenanceAccess::isWarehouse($user);
    }
    protected function authorizeView(Request $request): void
    {
        abort_unless($request->user() && SolarMaintenanceAccess::canViewAny($request->user()), 403);
    }

    protected function authorizeClaim(Request $request, SolarWarrantyClaim $claim, bool $anyFlow = false): void
    {
        $this->authorizeView($request);
        abort_unless($anyFlow
            ? \App\Support\Warranty\WarrantyFlow::isFlowType((string) $claim->claim_type)
            : (string) $claim->claim_type === 'replacement', 404);
        $this->assertCompany((int) ($claim->company_id ?: $claim->site?->company_id), $request);

        if (SolarMaintenanceAccess::isTechnicianOnly($request->user())) {
            abort_unless((int) $claim->assigned_to === (int) $request->user()->id, 403);
        }
    }

    protected function authorizeClaimUpdate(Request $request, SolarWarrantyClaim $claim, bool $anyFlow = false): void
    {
        $this->authorizeClaim($request, $claim, $anyFlow);
        $allowed = SolarMaintenanceAccess::isManager($request->user())
            || (SolarMaintenanceAccess::isTechnician($request->user()) && (int) $claim->assigned_to === (int) $request->user()->id);
        abort_unless($allowed, 403);
    }

    protected function ensureReady(): void
    {
        abort_unless(
            SchemaCache::hasTable('crm_serial_warranty_claims')
            && SchemaCache::hasColumn('crm_serial_warranty_claims', 'claim_type')
            && SchemaCache::hasColumn('crm_serial_warranty_claims', 'replacement_serial_code'),
            503,
            'Module bảo hành chưa đủ cấu trúc dữ liệu. Vui lòng chạy migration.'
        );
        $this->ensureSerialReady();
    }

    protected function ensureSerialReady(): void
    {
        foreach (['crm_serial_units', 'crm_serial_unit_identifiers', 'crm_serial_identifiers'] as $table) {
            abort_unless(SchemaCache::hasTable($table), 503, 'Kho serial chưa sẵn sàng để tra cứu.');
        }
    }

    protected function baseClaimQuery($user): Builder
    {
        $query = SolarWarrantyClaim::query()->where('claim_type', 'replacement');
        $companyId = EgoCompanyScope::currentId();

        if ($companyId > 0 && ! SolarMaintenanceAccess::isAdmin($user)) {
            $query->where(function (Builder $company) use ($companyId): void {
                $company->where('company_id', $companyId)
                    ->orWhere(function (Builder $fallback) use ($companyId): void {
                        $fallback->whereNull('company_id')
                            ->whereHas('site', fn (Builder $site) => $site->where('company_id', $companyId));
                    });
            });
        }

        if (SolarMaintenanceAccess::isTechnicianOnly($user)) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

    protected function summary($user): array
    {
        $row = $this->baseClaimQuery($user)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status='pending_approval' THEN 1 ELSE 0 END) AS pending_approval")
            ->selectRaw("SUM(CASE WHEN status IN ('approved','waiting_stock','reserved','waiting_faulty_return') THEN 1 ELSE 0 END) AS warehouse")
            ->selectRaw("SUM(CASE WHEN status IN ('issued','technician_received','replacing','waiting_customer') THEN 1 ELSE 0 END) AS processing")
            ->selectRaw("SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw("SUM(CASE WHEN priority='urgent' AND status NOT IN ('completed','rejected','cancelled') THEN 1 ELSE 0 END) AS urgent")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'pending_approval' => (int) ($row->pending_approval ?? 0),
            'warehouse' => (int) ($row->warehouse ?? 0),
            'processing' => (int) ($row->processing ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'urgent' => (int) ($row->urgent ?? 0),
        ];
    }

    protected function sitesForCompany(): Collection
    {
        $query = DB::table('sites')
            ->select('id', 'name', 'project_code', 'contact_name', 'contact_phone', 'address', 'company_id');
        $companyId = EgoCompanyScope::currentId();
        if ($companyId > 0 && SchemaCache::hasColumn('sites', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query->orderByDesc('id')->limit(800)->get();
    }

    protected function ordersWithSerialsForCompany(): Collection
    {
        if (! SchemaCache::hasTable('crm_orders')
            || ! SchemaCache::hasTable('crm_order_items')
            || ! SchemaCache::hasTable('crm_order_item_serial_units')) {
            return collect();
        }

        $linked = DB::table('crm_order_items as oi')
            ->join('crm_order_item_serial_units as oisu', 'oisu.order_item_id', '=', 'oi.id')
            ->selectRaw('oi.order_id, COUNT(DISTINCT oisu.serial_unit_id) as linked_serial_count')
            ->groupBy('oi.order_id');

        $query = DB::table('crm_orders as o')
            ->leftJoinSub($linked, 'linked_serials', fn ($join) => $join->on('linked_serials.order_id', '=', 'o.id'))
            ->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'l.customer_id')
            ->whereNull('o.deleted_at');

        if (SchemaCache::hasTable('crm_serial_warranties')) {
            $warranty = DB::table('crm_serial_warranties')
                ->whereNotNull('order_id')
                ->selectRaw('order_id, COUNT(DISTINCT serial_unit_id) as warranty_serial_count')
                ->groupBy('order_id');

            $query->leftJoinSub($warranty, 'warranty_serials', fn ($join) => $join->on('warranty_serials.order_id', '=', 'o.id'))
                ->where(function ($hasSerial): void {
                    $hasSerial->whereNotNull('linked_serials.order_id')
                        ->orWhereNotNull('warranty_serials.order_id');
                })
                ->selectRaw('GREATEST(COALESCE(linked_serials.linked_serial_count,0), COALESCE(warranty_serials.warranty_serial_count,0)) as serial_count');
        } else {
            $query->whereNotNull('linked_serials.order_id')
                ->addSelect('linked_serials.linked_serial_count as serial_count');
        }

        $query->addSelect(
            'o.id',
            'o.order_code',
            'o.order_date',
            'o.company_id',
            'o.shipping_status',
            'o.inventory_issued',
            'c.id as customer_id',
            'c.name as customer_name',
            'c.phone as customer_phone'
        );

        $companyId = EgoCompanyScope::currentId();
        if ($companyId > 0 && SchemaCache::hasColumn('crm_orders', 'company_id')) {
            $query->where('o.company_id', $companyId);
        }

        return $query->orderByDesc('o.order_date')->orderByDesc('o.id')->limit(700)->get();
    }

    protected function findOrderForExchange(int $orderId): object
    {
        $order = DB::table('crm_orders as o')
            ->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'l.customer_id')
            ->where('o.id', $orderId)
            ->whereNull('o.deleted_at')
            ->first([
                'o.id',
                'o.order_code',
                'o.order_date',
                'o.company_id',
                'o.lead_id',
                'o.shipping_status',
                'o.inventory_issued',
                'c.id as customer_id',
                'c.name as customer_name',
                'c.phone as customer_phone',
            ]);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Không tìm thấy đơn hàng đã chọn.',
            ]);
        }

        return $order;
    }

    protected function serialsForOrder(int $orderId): Collection
    {
        $ids = collect();

        if (SchemaCache::hasTable('crm_order_items') && SchemaCache::hasTable('crm_order_item_serial_units')) {
            $ids = $ids->merge(
                DB::table('crm_order_items as oi')
                    ->join('crm_order_item_serial_units as oisu', 'oisu.order_item_id', '=', 'oi.id')
                    ->where('oi.order_id', $orderId)
                    ->pluck('oisu.serial_unit_id')
            );
        }

        if (SchemaCache::hasTable('crm_serial_warranties')) {
            $ids = $ids->merge(
                DB::table('crm_serial_warranties')
                    ->where('order_id', $orderId)
                    ->pluck('serial_unit_id')
            );
        }

        $ids = $ids->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return $this->serialBaseQuery()
            ->whereIn('su.id', $ids)
            ->orderBy('si.code')
            ->get();
    }

    protected function assertSerialBelongsToOrder(object $serial, int $orderId): void
    {
        if ($orderId <= 0) {
            throw ValidationException::withMessages([
                'order_id' => 'Vui lòng chọn đơn hàng.',
            ]);
        }

        if ((int) ($serial->order_id ?? 0) === $orderId) {
            return;
        }

        $linked = false;
        if (SchemaCache::hasTable('crm_order_items') && SchemaCache::hasTable('crm_order_item_serial_units')) {
            $linked = DB::table('crm_order_items as oi')
                ->join('crm_order_item_serial_units as oisu', 'oisu.order_item_id', '=', 'oi.id')
                ->where('oi.order_id', $orderId)
                ->where('oisu.serial_unit_id', (int) $serial->serial_unit_id)
                ->exists();
        }

        if (! $linked) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial đã chọn không thuộc đơn hàng này. Vui lòng chọn serial trong danh sách của đơn.',
            ]);
        }
    }

    protected function resolveAssignee(Request $request, mixed $assigneeId, int $companyId): ?object
    {
        $user = $request->user();
        if (SolarMaintenanceAccess::isTechnicianOnly($user)) {
            return $user;
        }

        if (! $assigneeId) {
            return null;
        }

        $assignee = \App\Models\User::find((int) $assigneeId);
        if (! $assignee || ! SolarMaintenanceAccess::isSelectableTechnician($assignee)) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Người phụ trách phải là nhân sự kỹ thuật đang hoạt động.',
            ]);
        }

        if (SchemaCache::hasColumn('users', 'company_id')
            && $companyId > 0
            && (int) ($assignee->company_id ?? 0) > 0
            && (int) $assignee->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Người phụ trách không thuộc công ty của nguồn dữ liệu đã chọn.',
            ]);
        }

        return $assignee;
    }

    protected function findSerial(string $code): object
    {
        if ($code === '') {
            throw ValidationException::withMessages(['serial_code' => 'Vui lòng nhập serial thiết bị.']);
        }

        $query = $this->serialBaseQuery()->where('si.code', $code)->first();
        if (! $query) {
            throw ValidationException::withMessages([
                'serial_code' => 'Không tìm thấy serial “'.$code.'” trong hệ thống.',
            ]);
        }

        return $query;
    }

    protected function findSerialByUnitId(int $serialUnitId): ?object
    {
        if ($serialUnitId <= 0) {
            return null;
        }

        return $this->serialBaseQuery()->where('su.id', $serialUnitId)->first();
    }

    protected function serialBaseQuery()
    {
        $query = DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', function ($join): void {
                $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->select([
                'su.id as serial_unit_id',
                'su.product_id',
                'si.code as serial_code',
            ]);

        if (SchemaCache::hasTable('crm_product_catalog')) {
            $query->leftJoin('crm_product_catalog as p', 'p.id', '=', 'su.product_id')
                ->addSelect('p.name as product_name', 'p.sku as sku');
        } else {
            $query->addSelect(DB::raw('NULL as product_name'), DB::raw('NULL as sku'));
        }

        if (SchemaCache::hasTable('crm_serial_unit_states')) {
            $query->leftJoin('crm_serial_unit_states as sus', 'sus.serial_unit_id', '=', 'su.id')
                ->addSelect('sus.state as current_state', 'sus.warehouse_id as current_warehouse_id');
            if (SchemaCache::hasColumn('crm_serial_unit_states', 'company_id')) {
                $query->addSelect('sus.company_id as state_company_id');
            } else {
                $query->addSelect(DB::raw('NULL as state_company_id'));
            }
        } else {
            $query->addSelect(
                DB::raw('NULL as current_state'),
                DB::raw('NULL as current_warehouse_id'),
                DB::raw('NULL as state_company_id')
            );
        }

        $hasWarrantyTable = SchemaCache::hasTable('crm_serial_warranties');
        if ($hasWarrantyTable) {
            $query->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id')
                ->addSelect(
                    'wa.customer_id',
                    'wa.order_id',
                    'wa.site_id as warranty_site_id',
                    'wa.status as warranty_status',
                    'wa.warranty_start_at',
                    'wa.warranty_end_at'
                );
        } else {
            $query->addSelect(
                DB::raw('NULL as customer_id'),
                DB::raw('NULL as order_id'),
                DB::raw('NULL as warranty_site_id'),
                DB::raw('NULL as warranty_status'),
                DB::raw('NULL as warranty_start_at'),
                DB::raw('NULL as warranty_end_at')
            );
        }

        if ($hasWarrantyTable && SchemaCache::hasTable('crm_customers')) {
            $query->leftJoin('crm_customers as c', 'c.id', '=', 'wa.customer_id')
                ->addSelect('c.name as customer_name', 'c.phone as customer_phone', 'c.company_id as customer_company_id');
        } else {
            $query->addSelect(
                DB::raw('NULL as customer_name'),
                DB::raw('NULL as customer_phone'),
                DB::raw('NULL as customer_company_id')
            );
        }

        if ($hasWarrantyTable && SchemaCache::hasTable('crm_orders')) {
            $query->leftJoin('crm_orders as o', 'o.id', '=', 'wa.order_id')
                ->addSelect('o.order_code', 'o.company_id as order_company_id');
        } else {
            $query->addSelect(DB::raw('NULL as order_code'), DB::raw('NULL as order_company_id'));
        }

        if ($hasWarrantyTable && SchemaCache::hasTable('sites')) {
            $query->leftJoin('sites as ws', 'ws.id', '=', 'wa.site_id')
                ->addSelect('ws.name as warranty_site_name');
        } else {
            $query->addSelect(DB::raw('NULL as warranty_site_name'));
        }

        return $query;
    }

    protected function assertSerialCompany(object $serial, int $companyId, Request $request): void
    {
        if ($companyId <= 0) {
            return;
        }

        $knownCompanyIds = collect([
            (int) ($serial->state_company_id ?? 0),
            (int) ($serial->order_company_id ?? 0),
            (int) ($serial->customer_company_id ?? 0),
        ])->filter(fn (int $id): bool => $id > 0)->unique();

        if ($knownCompanyIds->contains(fn (int $id): bool => $id !== $companyId)) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial này đang thuộc công ty khác và không thể dùng cho công trình đã chọn.',
            ]);
        }
    }

    protected function assertCompany(int $companyId, Request $request): void
    {
        $current = EgoCompanyScope::currentId();
        if ($current > 0 && $companyId > 0 && $current !== $companyId && ! SolarMaintenanceAccess::isAdmin($request->user())) {
            abort(403, 'Dữ liệu không thuộc công ty đang làm việc.');
        }
    }

    protected function isWarrantyActive(object $serial): bool
    {
        if (strtolower((string) ($serial->warranty_status ?? '')) !== 'active') {
            return false;
        }

        $end = trim((string) ($serial->warranty_end_at ?? ''));
        if ($end === '') {
            return true;
        }

        return $end >= now()->toDateString();
    }

    protected function serialDetailsForClaims(Collection $claims): Collection
    {
        $ids = $claims->pluck('serial_unit_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return $this->serialBaseQuery()
            ->whereIn('su.id', $ids)
            ->get()
            ->keyBy(fn ($row) => (int) $row->serial_unit_id);
    }

    protected function replacementCandidates(?object $device, Collection $warehouses): Collection
    {
        if (! $device || ! SchemaCache::hasTable('crm_serial_unit_states')) {
            return collect();
        }

        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();
        if ($warehouseIds->isEmpty()) {
            return collect();
        }

        return DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', function ($join): void {
                $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->join('crm_serial_unit_states as sus', 'sus.serial_unit_id', '=', 'su.id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'sus.warehouse_id')
            ->where('su.product_id', (int) $device->product_id)
            ->where('sus.state', 'in_stock')
            ->whereIn('sus.warehouse_id', $warehouseIds)
            ->where('su.id', '<>', (int) $device->serial_unit_id)
            ->orderBy('w.name')
            ->orderBy('si.code')
            ->limit(300)
            ->get([
                'su.id as serial_unit_id',
                'si.code as serial_code',
                'sus.warehouse_id',
                'w.name as warehouse_name',
            ]);
    }

    protected function attachments(SolarWarrantyClaim $claim): Collection
    {
        if (! SchemaCache::hasTable('solar_warranty_claim_attachments')) {
            return collect();
        }

        return DB::table('solar_warranty_claim_attachments as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.uploaded_by')
            ->where('a.warranty_claim_id', $claim->id)
            ->whereNull('a.deleted_at')
            ->orderByDesc('a.id')
            ->get([
                'a.id', 'a.original_name', 'a.mime_type', 'a.file_size', 'a.created_at',
                'u.name as uploader_name',
            ]);
    }

    protected function storeEvidence(Request $request, SolarWarrantyClaim $claim): void
    {
        try {
            app(\App\Services\Warranty\EvidenceStore::class)->store((array) $request->file('evidence', []), $claim, $request->user(), 'create');
        } catch (\App\Support\Warranty\WarrantyException $e) {
            // Phiếu đã tạo nhưng file không lưu được: báo rõ, KHÔNG im lặng.
            session()->flash('error', 'Phiếu đã được tạo nhưng minh chứng chưa lưu: '.$e->getMessage());
        }
    }
    protected function allowedStatusesForUser(Request $request, SolarWarrantyClaim $claim): array
    {
        $current = (string) $claim->status;
        $statuses = array_values(array_unique(array_merge([$current], SolarWarrantyClaim::TRANSITIONS[$current] ?? [])));

        if (! SolarMaintenanceAccess::canApprove($request->user())) {
            $statuses = array_values(array_diff($statuses, ['approved', 'rejected']));
        }
        if (! SolarMaintenanceAccess::isManager($request->user())) {
            $statuses = array_values(array_diff($statuses, ['completed', 'cancelled']));
        }

        if (! $claim->replacement_serial_unit_id) {
            $statuses = array_values(array_diff($statuses, ['replacing', 'waiting_customer', 'completed']));
        }
        if (! $claim->returned_serial_unit_id) {
            $statuses = array_values(array_diff($statuses, ['completed']));
        }

        if (! in_array($current, $statuses, true)) {
            array_unshift($statuses, $current);
        }

        return array_values(array_unique($statuses));
    }

    protected function logSerialEvent(SolarWarrantyClaim $claim, string $type, string $note): void
    {
        if (! $claim->serial_unit_id || ! SchemaCache::hasTable('crm_serial_warranty_events')) {
            return;
        }

        DB::table('crm_serial_warranty_events')->insert([
            'serial_unit_id' => $claim->serial_unit_id,
            'serial_code' => $claim->serial_code,
            'event_type' => Str::limit($type, 40, ''),
            'customer_id' => $claim->customer_id,
            'order_id' => $claim->order_id,
            'created_by' => auth()->id(),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
