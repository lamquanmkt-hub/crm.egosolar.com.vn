<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Models\Site;
use App\Models\SolarWarrantyClaim;
use App\Services\Warranty\RepairService;
use App\Services\Warranty\WarrantyAudit;
use App\Support\SchemaCache;
use App\Support\SolarMaintenanceAccess;
use App\Support\Synced\EgoCompanyScope;
use App\Support\Warranty\WarrantyException;
use App\Support\Warranty\WarrantyFlow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Quy trình B — SỬA CHỮA SẢN PHẨM TÍNH PHÍ (claim_type = paid_repair).
 * Dùng lại các hàm tra cứu serial/công ty của controller đổi hàng.
 */
class WarrantyRepairController extends TechnicalWarrantyExchangeController
{
    public function __construct(private readonly RepairService $repair)
    {
    }

    public function index(Request $request): View
    {
        $this->authorizeView($request);
        $this->ensureReady();
        $user = $request->user();

        $query = SolarWarrantyClaim::query()->where('claim_type', WarrantyFlow::TYPE_REPAIR)
            ->with(['site:id,name,project_code', 'assignee:id,name']);
        $company = EgoCompanyScope::currentId();
        if ($company > 0 && ! SolarMaintenanceAccess::isAdmin($user)) {
            $query->where(fn (Builder $w) => $w->where('company_id', $company)->orWhereNull('company_id'));
        }
        if (SolarMaintenanceAccess::isTechnicianOnly($user)) {
            $query->where('assigned_to', $user->id);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn (Builder $w) => $w->where('claim_code', 'like', "%$q%")->orWhere('serial_code', 'like', "%$q%")->orWhere('issue_description', 'like', "%$q%"));
        }
        if (($st = (string) $request->query('status', '')) !== '' && array_key_exists($st, WarrantyFlow::REPAIR_STATUSES)) {
            $query->where('status', $st);
        }
        if ($a = (int) $request->query('assigned_to', 0)) {
            $query->where('assigned_to', $a);
        }
        if ($f = trim((string) $request->query('from', ''))) {
            $query->whereDate('created_at', '>=', $f);
        }
        if ($t = trim((string) $request->query('to', ''))) {
            $query->whereDate('created_at', '<=', $t);
        }
        if ($s = (int) $request->query('site_id', 0)) {
            $query->where('site_id', $s);
        }
        if ($c = trim((string) $request->query('customer', ''))) {
            $query->whereIn('customer_id', DB::table('crm_customers')->where('name', 'like', "%$c%")->select('id'));
        }
        if ($pid = (int) $request->query('product_id', 0)) {
            $query->whereIn('serial_unit_id', DB::table('crm_serial_units')->where('product_id', $pid)->select('id'));
        }

        $claims = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $serialMap = $this->serialDetailsForClaims(collect($claims->items()));
        foreach ($claims as $claim) {
            $claim->device = $serialMap->get((int) $claim->serial_unit_id);
        }

        return view('technical.warranty-repair.index', [
            'claims' => $claims,
            'statuses' => WarrantyFlow::REPAIR_STATUSES,
            'priorities' => SolarWarrantyClaim::PRIORITIES,
            'technicians' => app(\App\Services\Synced\Technical\SolarMaintenanceQueryService::class)->technicalUsers(),
            'sites' => $this->sitesForCompany(),
            'products' => DB::table('crm_product_catalog')->orderBy('name')->limit(400)->get(['id', 'name']),
            'canCreate' => SolarMaintenanceAccess::canCreateWarrantyClaim($user),
            'isTechnicianOnly' => SolarMaintenanceAccess::isTechnicianOnly($user),
            'orders' => $this->ordersWithSerialsForCompany(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(SolarMaintenanceAccess::canCreateWarrantyClaim($request->user()), 403);
        $this->ensureReady();

        $data = $request->validate([
            'source_type' => ['nullable', Rule::in(['site', 'order'])],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'order_id' => ['nullable', 'integer', 'exists:crm_orders,id'],
            'serial_code' => ['required', 'string', 'max:190'],
            'priority' => ['required', Rule::in(array_keys(SolarWarrantyClaim::PRIORITIES))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'issue_description' => ['required', 'string', 'max:10000'],
            'out_of_scope_reason' => ['nullable', 'string', 'max:5000'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array', 'max:'.(int) config('warranty.evidence_max_files', 8)],
            'evidence.*' => ['file', 'max:'.(int) config('warranty.evidence_max_kb', 20480)],
        ], [
            'serial_code.required' => 'Vui lòng nhập serial thiết bị cần sửa.',
            'issue_description.required' => 'Vui lòng mô tả lỗi/hiện tượng thực tế.',
        ]);

        $companyId = EgoCompanyScope::currentId();
        $site = null;
        $order = null;
        if (! empty($data['site_id'])) {
            $site = Site::withoutGlobalScopes()->findOrFail((int) $data['site_id']);
            $companyId = (int) ($site->company_id ?: $companyId);
            $this->assertCompany($companyId, $request);
        }
        if (! empty($data['order_id'])) {
            $order = $this->findOrderForExchange((int) $data['order_id']);
            $companyId = (int) ($order->company_id ?: $companyId);
            $this->assertCompany($companyId, $request);
        }

        $serial = $this->findSerial(trim((string) $data['serial_code']));
        $this->assertSerialCompany($serial, $companyId, $request);
        if ($order) {
            $this->assertSerialBelongsToOrder($serial, (int) $order->id);
        }
        if ($site && (int) ($serial->warranty_site_id ?? 0) > 0 && (int) $serial->warranty_site_id !== (int) $site->id) {
            throw ValidationException::withMessages(['site_id' => 'Serial đang gắn với công trình khác trong hồ sơ bảo hành.']);
        }

        $assignee = $this->resolveAssignee($request, $data['assigned_to'] ?? null, $companyId);
        $customerId = (int) ($serial->customer_id ?? 0) ?: (int) ($order->customer_id ?? 0);

        try {
            $claim = $this->repair->create($request->user(), $data, [
                'serial' => $serial,
                'site_id' => $site?->id ?: ((int) ($serial->warranty_site_id ?? 0) ?: null),
                'order_id' => $order?->id ?: ((int) ($serial->order_id ?? 0) ?: null),
                'customer_id' => $customerId,
                'company_id' => $companyId,
                'warranty_active' => $this->isWarrantyActive($serial),
                'warranty_status' => (string) ($serial->warranty_status ?? ''),
                'assignee_id' => $assignee?->id,
                'assignee_name' => $assignee?->name,
            ]);
        } catch (WarrantyException $e) {
            throw ValidationException::withMessages(['serial_code' => $e->getMessage()]);
        }
        $this->storeEvidence($request, $claim);

        return redirect()->route('ky-thuat.repair.show', ['claim' => $claim->id])
            ->with('success', 'Đã tiếp nhận '.$claim->claim_code.'. Bước tiếp theo: chẩn đoán kỹ thuật.');
    }

    public function show(Request $request, SolarWarrantyClaim $claim): View
    {
        $this->authorizeView($request);
        abort_unless($claim->claim_type === WarrantyFlow::TYPE_REPAIR, 404);
        $this->assertCompany((int) ($claim->company_id ?: $claim->site?->company_id), $request);
        $user = $request->user();
        if (SolarMaintenanceAccess::isTechnicianOnly($user)) {
            abort_unless((int) $claim->assigned_to === (int) $user->id, 403);
        }

        $claim->load(['site:id,name,project_code,contact_name,contact_phone,address,company_id', 'order:id,order_code', 'assignee:id,name', 'creator:id,name']);
        $quotations = DB::table('warranty_repair_quotations')->where('claim_id', $claim->id)->orderByDesc('version')->get();
        $items = DB::table('warranty_repair_quotation_items')->whereIn('quotation_id', $quotations->pluck('id'))->get()->groupBy('quotation_id');
        $current = $quotations->firstWhere('id', $claim->current_quotation_id);
        $parts = DB::table('warranty_repair_parts as p')->leftJoin('crm_warehouses as w', 'w.id', '=', 'p.warehouse_id')->where('p.claim_id', $claim->id)->get(['p.*', 'w.name as warehouse_name']);
        $qa = DB::table('warranty_repair_qa as q')->leftJoin('users as u', 'u.id', '=', 'q.tested_by')->where('q.claim_id', $claim->id)->orderByDesc('q.id')->get(['q.*', 'u.name as tester']);
        $status = (string) $claim->status;
        $isLead = SolarMaintenanceAccess::isTechnicalLead($user);
        $isWarehouse = SolarMaintenanceAccess::canHandleWarrantyStock($user);
        $isAssigned = $isLead || ((int) $claim->assigned_to === (int) $user->id && SolarMaintenanceAccess::isTechnician($user));
        $device = $this->findSerialByUnitId((int) $claim->serial_unit_id);
        $flags = [
            'quotation_sent' => $quotations->contains(fn ($q) => in_array($q->status, ['sent', 'approved', 'rejected', 'superseded'], true)),
            'quotation_approved' => (bool) ($current && $current->status === 'approved'),
            'parts_ready' => $parts->isEmpty() ? in_array($status, ['repairing', 'qa_testing', 'qa_failed', 'ready_handover', 'handed_over', 'completed'], true)
                : $parts->every(fn ($p) => in_array($p->status, ['issued', 'closed'], true)),
            'qa_passed' => $qa->first()?->result === 'pass',
        ];
        $can = [
            'diagnose' => $isAssigned && $status === 'diagnosing',
            'quote' => $isAssigned && in_array($status, ['quotation_draft', 'waiting_customer_confirmation', 'quotation_rejected', 'approved_for_repair', 'waiting_parts'], true),
            'send' => $isAssigned && $status === 'quotation_draft' && $current && $current->status === 'draft',
            'decide' => $isAssigned && $status === 'waiting_customer_confirmation',
            'reserve_parts' => $isWarehouse && in_array($status, ['approved_for_repair', 'waiting_parts'], true) && $parts->contains('status', 'planned'),
            'issue_parts' => $isWarehouse && $status === 'waiting_parts' && $parts->contains('status', 'reserved'),
            'return_parts' => $isWarehouse && $parts->contains('status', 'issued'),
            'start' => $isAssigned && in_array($status, ['approved_for_repair', 'waiting_parts', 'qa_failed'], true),
            'update' => $isAssigned && $status === 'repairing',
            'qa_submit' => $isAssigned && $status === 'repairing',
            'qa' => $isAssigned && $status === 'qa_testing',
            'handover' => $isAssigned && $status === 'ready_handover',
            'complete' => $isLead && $status === 'handed_over',
            'cancel' => (bool) (($isLead || (int) $claim->created_by === (int) $user->id) && in_array($status, ['diagnosing', 'quotation_draft', 'waiting_customer_confirmation', 'quotation_rejected', 'approved_for_repair', 'waiting_parts'], true)),
            'evidence' => (SolarMaintenanceAccess::isManager($user) || $isAssigned) && WarrantyFlow::isOpen($status),
        ];

        return view('technical.warranty-repair.show', [
            'claim' => $claim, 'device' => $device, 'statuses' => WarrantyFlow::REPAIR_STATUSES,
            'timeline' => WarrantyFlow::repairTimeline($claim, $flags),
            'checklist' => $this->repair->checklist($claim),
            'quotations' => $quotations, 'quotationItems' => $items, 'current' => $current,
            'parts' => $parts, 'qa' => $qa, 'can' => $can,
            'history' => WarrantyAudit::history((int) $claim->id),
            'attachments' => $this->canViewEvidence($user) ? $this->attachments($claim) : collect(),
            'warehouses' => app(\App\Services\Technical\SolarWarrantyQueryService::class)->warehouses($user),
            'methods' => WarrantyFlow::REPAIR_DECISION_METHODS,
            'products' => DB::table('crm_product_catalog')->orderBy('name')->limit(400)->get(['id', 'name', 'sku']),
            'canViewInternal' => SolarMaintenanceAccess::canViewTechnicalInternal($user),
            'priorClaims' => DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $claim->serial_unit_id)->where('id', '<>', $claim->id)->whereNull('deleted_at')->orderByDesc('id')->limit(10)->get(['id', 'claim_code', 'claim_type', 'status', 'created_at']),
        ]);
    }

    // ----------------------------------------------------------------- actions
    public function diagnosis(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        $d = $r->validate([
            'diagnosis' => ['required', 'string', 'max:10000'], 'diagnosis_cause' => ['required', 'string', 'max:10000'],
            'proposed_solution' => ['required', 'string', 'max:10000'], 'parts_needed' => ['nullable', 'string', 'max:5000'],
            'est_repair_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'], 'tech_note' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->act($claim, 'Đã lưu chẩn đoán. Tiếp theo: lập báo giá.', fn () => $this->repair->saveDiagnosis($claim->id, $r->user(), $d));
    }

    public function quotation(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        $d = $r->validate([
            'items' => ['nullable', 'array', 'max:60'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable'],
            'items.*.unit_price' => ['nullable'],
            'labor_amount' => ['nullable'], 'onsite_amount' => ['nullable'], 'shipping_amount' => ['nullable'],
            'extra_amount' => ['nullable'], 'discount_amount' => ['nullable'], 'note' => ['nullable', 'string', 'max:5000'],
        ]);
        // bỏ dòng trống; TỔNG TIỀN do server tính — mọi trường total gửi lên đều bị bỏ qua
        $d['items'] = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => ! empty($i['name']) || ! empty($i['product_id'])));

        return $this->act($claim, 'Đã lưu báo giá (tổng tiền do hệ thống tính).', fn () => $this->repair->saveQuotation($claim->id, $r->user(), $d));
    }

    public function sendQuotation(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        return $this->act($claim, 'Đã gửi báo giá — chờ khách xác nhận.', fn () => $this->repair->sendQuotation($claim->id, $r->user()));
    }

    public function decision(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        $d = $r->validate([
            'decision' => ['required', 'in:approved,rejected'], 'method' => ['required', 'string', 'max:20'],
            'decided_at' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->act($claim, 'Đã ghi nhận quyết định của khách.', fn () => $this->repair->customerDecision($claim->id, $r->user(), $d['decision'], $d['method'], $d['decided_at'] ?? null, $d['note'] ?? null));
    }

    public function reserveParts(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        $d = $r->validate(['warehouse_id' => ['required', 'integer', 'exists:crm_warehouses,id']]);

        return $this->act($claim, 'Đã giữ linh kiện.', fn () => $this->repair->reserveParts($claim->id, $r->user(), (int) $d['warehouse_id']));
    }

    public function issueParts(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        return $this->act($claim, 'Đã xuất linh kiện cho Kỹ thuật.', fn () => $this->repair->issueParts($claim->id, $r->user()));
    }

    public function returnParts(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        return $this->act($claim, 'Đã hoàn kho linh kiện dư.', fn () => $this->repair->returnLeftoverParts($claim->id, $r->user()));
    }

    public function start(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        return $this->act($claim, 'Đã bắt đầu sửa chữa.', fn () => $this->repair->startRepair($claim->id, $r->user()));
    }

    public function progress(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        $d = $r->validate([
            'repair_work_done' => ['required', 'string', 'max:10000'], 'repair_hours_actual' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'repair_issues' => ['nullable', 'string', 'max:5000'], 'parts_used' => ['nullable', 'array'], 'parts_used.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $used = array_filter((array) ($d['parts_used'] ?? []), fn ($v) => $v !== null && $v !== '');

        return $this->act($claim, 'Đã cập nhật tiến độ sửa chữa.', fn () => $this->repair->updateRepair($claim->id, $r->user(), $d, $used));
    }

    public function qaSubmit(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        return $this->act($claim, 'Đã chuyển sang kiểm tra sau sửa.', fn () => $this->repair->submitToQa($claim->id, $r->user()));
    }

    public function qa(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        $d = $r->validate(['result' => ['required', 'in:pass,fail'], 'measurements' => ['nullable', 'string', 'max:5000'], 'note' => ['nullable', 'string', 'max:5000']]);

        return $this->act($claim, $d['result'] === 'pass' ? 'Kiểm tra ĐẠT — sẵn sàng bàn giao.' : 'Kiểm tra KHÔNG đạt — quay lại sửa chữa.', fn () => $this->repair->recordQa($claim->id, $r->user(), $d['result'], $d['measurements'] ?? null, $d['note'] ?? null));
    }

    public function handover(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        $d = $r->validate([
            'handed_over_at' => ['nullable', 'date'], 'handover_receiver_name' => ['required', 'string', 'max:190'],
            'handover_condition' => ['required', 'string', 'max:120'], 'handover_result' => ['required', 'string', 'max:5000'],
            'handover_guidance' => ['nullable', 'string', 'max:5000'], 'handover_note' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->act($claim, 'Đã bàn giao cho khách hàng.', fn () => $this->repair->handover($claim->id, $r->user(), $d));
    }

    public function complete(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        return $this->act($claim, 'Đã hoàn tất phiếu sửa chữa (báo giá cuối đã khóa).', fn () => $this->repair->complete($claim->id, $r->user()));
    }

    public function cancel(Request $r, SolarWarrantyClaim $claim): RedirectResponse
    {
        $d = $r->validate(['reason' => ['required', 'string', 'max:5000']], ['reason.required' => 'Bắt buộc nhập lý do hủy phiếu.']);

        return $this->act($claim, 'Đã hủy phiếu.', fn () => $this->repair->cancel($claim->id, $r->user(), $d['reason']));
    }

    private function act(SolarWarrantyClaim $claim, string $ok, callable $fn): RedirectResponse
    {
        abort_unless($claim->claim_type === WarrantyFlow::TYPE_REPAIR, 404);
        $user = request()->user();
        abort_unless(SolarMaintenanceAccess::canViewAny($user), 403);
        $current = EgoCompanyScope::currentId();
        $cc = (int) $claim->company_id;
        if ($current > 0 && $cc > 0 && $current !== $cc && ! SolarMaintenanceAccess::isAdmin($user)) {
            abort(403, 'Dữ liệu không thuộc công ty đang làm việc.');
        }
        try {
            $fn();
        } catch (WarrantyException $e) {
            return back()->withInput()->withErrors(['workflow' => $e->getMessage()]);
        }

        return back()->with('success', $ok);
    }
}
