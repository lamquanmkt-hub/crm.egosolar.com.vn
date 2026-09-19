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
        if ($status !== '' && array_key_exists($status, SolarWarrantyClaim::STATUSES)) {
            $query->where('status', $status);
        }

        $priority = trim((string) $request->query('priority', ''));
        if ($priority !== '' && array_key_exists($priority, SolarWarrantyClaim::PRIORITIES)) {
            $query->where('priority', $priority);
        }

        $bucket = trim((string) $request->query('bucket', ''));
        if ($bucket === 'pending_approval') {
            $query->where('status', 'pending_approval');
        } elseif ($bucket === 'warehouse') {
            $query->whereIn('status', ['approved', 'waiting_stock']);
        } elseif ($bucket === 'processing') {
            $query->whereIn('status', ['replacing', 'waiting_customer']);
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
            'statuses' => SolarWarrantyClaim::STATUSES,
            'priorities' => SolarWarrantyClaim::PRIORITIES,
            'canCreate' => SolarMaintenanceAccess::canCreateWarrantyClaim($user),
            'isManager' => SolarMaintenanceAccess::isManager($user),
            'isTechnicianOnly' => SolarMaintenanceAccess::isTechnicianOnly($user),
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
            'warranty_override' => ['nullable', 'boolean'],
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

        $warrantyActive = $this->isWarrantyActive($serial);
        if (! $warrantyActive) {
            $canOverride = SolarMaintenanceAccess::isManager($request->user()) && (bool) ($data['warranty_override'] ?? false);
            if (! $canOverride) {
                throw ValidationException::withMessages([
                    'serial_code' => 'Serial không có bảo hành còn hiệu lực. Nếu cần xử lý ngoại lệ, Trưởng phòng/Admin phải tạo phiếu và xác nhận ngoại lệ.',
                ]);
            }
        }

        $assignee = $this->resolveAssignee($request, $data['assigned_to'] ?? null, $companyId);

        $duplicate = SolarWarrantyClaim::query()
            ->where('claim_type', 'replacement')
            ->where('serial_unit_id', (int) $serial->serial_unit_id)
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial này đã có một đề xuất đổi hàng đang mở. Hãy xử lý phiếu hiện tại trước khi tạo phiếu mới.',
            ]);
        }

        $claim = DB::transaction(function () use ($request, $data, $companyId, $resolvedSiteId, $resolvedOrderId, $resolvedCustomerId, $serial, $assignee, $warrantyActive, $sourceType): SolarWarrantyClaim {
            $internalNote = trim((string) ($data['internal_note'] ?? ''));
            $sourceNote = $sourceType === 'order'
                ? 'Nguồn tạo phiếu: Đơn hàng #'.$resolvedOrderId.'.'
                : 'Nguồn tạo phiếu: Công trình #'.$resolvedSiteId.'.';
            $internalNote = trim($sourceNote."\n".$internalNote);

            if (! $warrantyActive) {
                $internalNote = trim($internalNote."\nNgoại lệ: Trưởng phòng/Admin xác nhận tạo đề xuất khi bảo hành không còn hiệu lực/không đủ dữ liệu.");
            }

            $claim = SolarWarrantyClaim::create([
                'company_id' => $companyId ?: null,
                'site_id' => $resolvedSiteId,
                'maintenance_schedule_id' => null,
                'serial_unit_id' => (int) $serial->serial_unit_id,
                'serial_code' => (string) $serial->serial_code,
                'customer_id' => $resolvedCustomerId > 0 ? $resolvedCustomerId : null,
                'order_id' => $resolvedOrderId,
                'claim_type' => 'replacement',
                'priority' => $data['priority'],
                'status' => 'pending_approval',
                'approval_status' => 'pending',
                'assigned_to' => $assignee?->id,
                'assigned_name' => $assignee?->name,
                'received_at' => now()->toDateString(),
                'issue_description' => trim((string) $data['issue_description']),
                'diagnosis' => trim((string) $data['diagnosis']),
                'proposed_solution' => trim((string) $data['proposed_solution']),
                'submitted_at' => now(),
                'submitted_by' => $request->user()->id,
                'estimated_cost' => (float) ($data['estimated_cost'] ?? 0),
                'is_chargeable' => false,
                'internal_note' => $internalNote !== '' ? $internalNote : null,
                'created_by' => $request->user()->id,
            ]);

            $claim->update([
                'claim_code' => sprintf('DXBH-%s-%06d', now()->format('Y'), $claim->id),
            ]);

            $this->logSerialEvent(
                $claim,
                'maintenance_replacement_proposed',
                'Tạo đề xuất đổi hàng bảo hành '.$claim->claim_code.'. Chờ Trưởng phòng/Admin phê duyệt.'
            );

            return $claim;
        });

        $this->storeEvidence($request, $claim);

        return redirect()->route('ky-thuat.warranty-exchange.show', ['claim' => $claim->id])
            ->with('success', 'Đã tạo '.$claim->claim_code.' và gửi duyệt đề xuất đổi hàng bảo hành.');
    }

    public function show(Request $request, SolarWarrantyClaim $claim): View
    {
        $this->authorizeClaim($request, $claim);
        $this->ensureReady();

        $claim->load([
            'site:id,name,project_code,contact_name,contact_phone,address,company_id',
            'order:id,order_code,order_date,company_id,lead_id',
            'assignee:id,name',
            'creator:id,name',
            'approver:id,name',
            'stockMovements' => function ($query): void {
                $query->with(['warehouse:id,name', 'requester:id,name'])
                    ->orderByDesc('id');
            },
        ]);

        $device = $this->findSerialByUnitId((int) $claim->serial_unit_id);
        $warehouses = app(SolarWarrantyQueryService::class)->warehouses($request->user());
        $replacementCandidates = $this->replacementCandidates($device, $warehouses);
        $attachments = $this->attachments($claim);

        $canUpdate = SolarMaintenanceAccess::isManager($request->user())
            || (SolarMaintenanceAccess::isTechnician($request->user()) && (int) $claim->assigned_to === (int) $request->user()->id);
        $canStock = SolarMaintenanceAccess::canHandleWarrantyStock($request->user());
        $canViewCosts = SolarMaintenanceAccess::canViewMaintenanceCosts($request->user());
        $allowedStatuses = $this->allowedStatusesForUser($request, $claim);

        return view('technical.warranty-exchange.show', [
            'claim' => $claim,
            'device' => $device,
            'attachments' => $attachments,
            'warehouses' => $warehouses,
            'replacementCandidates' => $replacementCandidates,
            'statuses' => SolarWarrantyClaim::STATUSES,
            'priorities' => SolarWarrantyClaim::PRIORITIES,
            'transitions' => SolarWarrantyClaim::TRANSITIONS,
            'stockTypes' => SolarWarrantyStockMovement::TYPES,
            'stockStatuses' => SolarWarrantyStockMovement::STATUSES,
            'canUpdate' => $canUpdate,
            'canStock' => $canStock,
            'canViewCosts' => $canViewCosts,
            'isManager' => SolarMaintenanceAccess::isManager($request->user()),
            'allowedStatuses' => $allowedStatuses,
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
        $this->authorizeClaimUpdate($request, $claim);

        $request->validate([
            'evidence' => ['required', 'array', 'min:1', 'max:8'],
            'evidence.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'evidence.required' => 'Vui lòng chọn ít nhất một tệp minh chứng.',
            'evidence.*.mimes' => 'Minh chứng chỉ nhận JPG, PNG, WEBP hoặc PDF.',
            'evidence.*.max' => 'Mỗi tệp minh chứng tối đa 20MB.',
        ]);

        $this->storeEvidence($request, $claim);

        return back()->with('success', 'Đã bổ sung minh chứng cho '.$claim->claim_code.'.');
    }

    public function downloadEvidence(Request $request, SolarWarrantyClaim $claim, int $attachment): BinaryFileResponse
    {
        $this->authorizeClaim($request, $claim);
        abort_unless(SchemaCache::hasTable('solar_warranty_claim_attachments'), 404);

        $row = DB::table('solar_warranty_claim_attachments')
            ->where('id', $attachment)
            ->where('warranty_claim_id', $claim->id)
            ->whereNull('deleted_at')
            ->first();
        abort_unless($row, 404);
        abort_unless(Storage::disk('public')->exists((string) $row->file_path), 404);

        return response()->file(
            Storage::disk('public')->path((string) $row->file_path),
            ['Content-Type' => (string) ($row->mime_type ?: 'application/octet-stream')]
        );
    }

    public function destroyEvidence(Request $request, SolarWarrantyClaim $claim, int $attachment): RedirectResponse
    {
        $this->authorizeClaimUpdate($request, $claim);
        abort_unless(SchemaCache::hasTable('solar_warranty_claim_attachments'), 404);

        $row = DB::table('solar_warranty_claim_attachments')
            ->where('id', $attachment)
            ->where('warranty_claim_id', $claim->id)
            ->whereNull('deleted_at')
            ->first();
        abort_unless($row, 404);

        DB::table('solar_warranty_claim_attachments')->where('id', $row->id)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã gỡ minh chứng khỏi phiếu.');
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user() && SolarMaintenanceAccess::canViewAny($request->user()), 403);
    }

    private function authorizeClaim(Request $request, SolarWarrantyClaim $claim): void
    {
        $this->authorizeView($request);
        abort_unless((string) $claim->claim_type === 'replacement', 404);
        $this->assertCompany((int) ($claim->company_id ?: $claim->site?->company_id), $request);

        if (SolarMaintenanceAccess::isTechnicianOnly($request->user())) {
            abort_unless((int) $claim->assigned_to === (int) $request->user()->id, 403);
        }
    }

    private function authorizeClaimUpdate(Request $request, SolarWarrantyClaim $claim): void
    {
        $this->authorizeClaim($request, $claim);
        $allowed = SolarMaintenanceAccess::isManager($request->user())
            || (SolarMaintenanceAccess::isTechnician($request->user()) && (int) $claim->assigned_to === (int) $request->user()->id);
        abort_unless($allowed, 403);
    }

    private function ensureReady(): void
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

    private function ensureSerialReady(): void
    {
        foreach (['crm_serial_units', 'crm_serial_unit_identifiers', 'crm_serial_identifiers'] as $table) {
            abort_unless(SchemaCache::hasTable($table), 503, 'Kho serial chưa sẵn sàng để tra cứu.');
        }
    }

    private function baseClaimQuery($user): Builder
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

    private function summary($user): array
    {
        $row = $this->baseClaimQuery($user)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status='pending_approval' THEN 1 ELSE 0 END) AS pending_approval")
            ->selectRaw("SUM(CASE WHEN status IN ('approved','waiting_stock') THEN 1 ELSE 0 END) AS warehouse")
            ->selectRaw("SUM(CASE WHEN status IN ('replacing','waiting_customer') THEN 1 ELSE 0 END) AS processing")
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

    private function sitesForCompany(): Collection
    {
        $query = DB::table('sites')
            ->select('id', 'name', 'project_code', 'contact_name', 'contact_phone', 'address', 'company_id');
        $companyId = EgoCompanyScope::currentId();
        if ($companyId > 0 && SchemaCache::hasColumn('sites', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query->orderByDesc('id')->limit(800)->get();
    }

    private function ordersWithSerialsForCompany(): Collection
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

    private function findOrderForExchange(int $orderId): object
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

    private function serialsForOrder(int $orderId): Collection
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

    private function assertSerialBelongsToOrder(object $serial, int $orderId): void
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

    private function resolveAssignee(Request $request, mixed $assigneeId, int $companyId): ?object
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

    private function findSerial(string $code): object
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

    private function findSerialByUnitId(int $serialUnitId): ?object
    {
        if ($serialUnitId <= 0) {
            return null;
        }

        return $this->serialBaseQuery()->where('su.id', $serialUnitId)->first();
    }

    private function serialBaseQuery()
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

    private function assertSerialCompany(object $serial, int $companyId, Request $request): void
    {
        if ($companyId <= 0 || SolarMaintenanceAccess::isAdmin($request->user())) {
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

    private function assertCompany(int $companyId, Request $request): void
    {
        $current = EgoCompanyScope::currentId();
        if ($current > 0 && $companyId > 0 && $current !== $companyId && ! SolarMaintenanceAccess::isAdmin($request->user())) {
            abort(403, 'Dữ liệu không thuộc công ty đang làm việc.');
        }
    }

    private function isWarrantyActive(object $serial): bool
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

    private function serialDetailsForClaims(Collection $claims): Collection
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

    private function replacementCandidates(?object $device, Collection $warehouses): Collection
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

    private function attachments(SolarWarrantyClaim $claim): Collection
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

    private function storeEvidence(Request $request, SolarWarrantyClaim $claim): void
    {
        if (! SchemaCache::hasTable('solar_warranty_claim_attachments')) {
            return;
        }

        foreach ((array) $request->file('evidence', []) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('warranty-exchange/'.$claim->id, 'public');
            DB::table('solar_warranty_claim_attachments')->insert([
                'warranty_claim_id' => $claim->id,
                'category' => 'evidence',
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'uploaded_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function allowedStatusesForUser(Request $request, SolarWarrantyClaim $claim): array
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

    private function logSerialEvent(SolarWarrantyClaim $claim, string $type, string $note): void
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
