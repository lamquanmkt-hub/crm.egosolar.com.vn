<?php

declare(strict_types=1);

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\SolarWarrantyClaim;
use App\Support\SolarMaintenanceAccess;
use App\Support\Synced\EgoCompanyScope;
use App\Support\Warranty\WarrantyAudit;
use App\Support\Warranty\WarrantyFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Trang RIÊNG cho Kho: "Xuất hàng bảo hành & sửa chữa". KHÔNG chứa nghiệp vụ mới — chỉ đọc/ghi
 * đúng 1 nguồn dữ liệu thật (crm_serial_warranty_claims, warranty_repair_parts,
 * warranty_serial_reservations…) mà module Kỹ thuật cũng dùng, qua đúng WarrantyExchangeService /
 * RepairService (gọi lại các route action hiện có ky-thuat.warranty-exchange.* /
 * ky-thuat.repair.*.parts.*) — không có bảng dữ liệu song song, không copy/lệch trạng thái.
 */
class WarrantyFulfillmentController extends Controller
{
    private const TABS = ['waiting', 'reserved', 'issued', 'return'];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(SolarMaintenanceAccess::isWarehouse($user), 403);

        $tab = in_array($request->string('tab')->toString(), self::TABS, true) ? $request->string('tab')->toString() : 'waiting';
        $company = EgoCompanyScope::currentId();

        $rows = $this->loadRows($company);

        $filters = [
            'type' => (string) $request->input('type'),
            'warehouse_id' => (int) $request->input('warehouse_id'),
            'product' => trim((string) $request->input('product')),
            'assigned_to' => (int) $request->input('assigned_to'),
            'customer' => trim((string) $request->input('customer')),
            'from' => (string) $request->input('from'),
            'to' => (string) $request->input('to'),
            'q' => trim((string) $request->input('q')),
        ];

        $filtered = $rows->filter(function (array $row) use ($filters): bool {
            if ($filters['type'] !== '' && $row['type'] !== $filters['type']) {
                return false;
            }
            if ($filters['warehouse_id'] > 0 && $row['warehouse_id'] !== $filters['warehouse_id']) {
                return false;
            }
            if ($filters['product'] !== '' && ! Str::contains(Str::lower($row['product']), Str::lower($filters['product']))) {
                return false;
            }
            if ($filters['assigned_to'] > 0 && $row['assigned_to'] !== $filters['assigned_to']) {
                return false;
            }
            if ($filters['customer'] !== '' && ! Str::contains(Str::lower($row['customer'].' '.$row['customer_phone']), Str::lower($filters['customer']))) {
                return false;
            }
            if ($filters['from'] !== '' && $row['requested_at'] < $filters['from']) {
                return false;
            }
            if ($filters['to'] !== '' && $row['requested_at'] > $filters['to'].' 23:59:59') {
                return false;
            }
            if ($filters['q'] !== '') {
                $hay = Str::lower($row['claim_code'].' '.$row['serial_old'].' '.$row['serial_new']);
                if (! Str::contains($hay, Str::lower($filters['q']))) {
                    return false;
                }
            }

            return true;
        });

        $counts = ['waiting' => 0, 'reserved' => 0, 'issued' => 0, 'return' => 0];
        foreach ($filtered as $row) {
            $counts[$row['stage']]++;
        }

        $list = $filtered->filter(fn (array $row) => $row['stage'] === $tab)
            ->sortBy('requested_at')->values();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 30;
        $paged = $list->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $paged, $list->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $warehouses = DB::table('crm_warehouses')->when($company > 0, fn ($q) => $q->where(fn ($w) => $w->where('company_id', $company)->orWhereNull('company_id')))
            ->orderBy('name')->get(['id', 'name']);
        $technicians = DB::table('users')->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->whereIn('roles.name', ['ky_thuat', 'technical', 'technical_manager'])
            ->distinct()->orderBy('users.name')->get(['users.id', 'users.name']);

        return view('warehouse.warranty-fulfillment.index', [
            'tab' => $tab,
            'counts' => $counts,
            'rows' => $paginator,
            'filters' => $filters,
            'warehouses' => $warehouses,
            'technicians' => $technicians,
            'exchangeStatuses' => WarrantyFlow::EXCHANGE_STATUSES,
            'repairStatuses' => WarrantyFlow::REPAIR_STATUSES,
        ]);
    }

    /** Chi tiết 1 phiếu để mở popup "Xem chi tiết yêu cầu" (AJAX). */
    public function show(Request $request, SolarWarrantyClaim $claim): JsonResponse
    {
        abort_unless(SolarMaintenanceAccess::isWarehouse($request->user()), 403);

        $data = [
            'id' => $claim->id,
            'claim_code' => $claim->claim_code,
            'claim_type' => $claim->claim_type,
            'status' => $claim->status,
            'status_label' => $claim->claim_type === 'replacement'
                ? (WarrantyFlow::EXCHANGE_STATUSES[$claim->status] ?? $claim->status)
                : (WarrantyFlow::REPAIR_STATUSES[$claim->status] ?? $claim->status),
            'customer_name' => $claim->customer_name,
            'customer_phone' => $claim->customer_phone,
            'device' => trim(($claim->device_type ?: '').' '.($claim->device_brand ?: '').' '.($claim->device_model ?: '')),
            'serial_code' => $claim->serial_code,
            'reserved_serial_code' => $claim->reserved_serial_code,
            'issue_description' => $claim->issue_description,
            'diagnosis' => $claim->diagnosis,
            'assignee' => $claim->assignee?->name,
            'creator' => $claim->creator?->name,
            'received_at' => optional($claim->received_at)->format('d/m/Y H:i'),
        ];

        if ($claim->claim_type === 'paid_repair') {
            $data['parts'] = DB::table('warranty_repair_parts as p')->leftJoin('crm_warehouses as w', 'w.id', '=', 'p.warehouse_id')
                ->where('p.claim_id', $claim->id)
                ->get(['p.name', 'p.qty_planned', 'p.qty_reserved', 'p.qty_issued', 'p.qty_used', 'p.qty_returned', 'p.status', 'w.name as warehouse_name']);
        }

        return response()->json($data);
    }

    /** Ghi chú nội bộ của Kho — không đổi trạng thái phiếu, chỉ log vào lịch sử (audit) dùng chung. */
    public function note(Request $request, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        abort_unless(SolarMaintenanceAccess::isWarehouse($request->user()), 403);
        $d = $request->validate(['note' => ['required', 'string', 'max:5000']], ['note.required' => 'Bắt buộc nhập nội dung ghi chú.']);

        WarrantyAudit::log($claim->id, 'warehouse_note', $claim->status, $claim->status, null, null, $d['note'], $request->user()->id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Đã lưu ghi chú Kho.']);
        }

        return back()->with('success', 'Đã lưu ghi chú Kho.');
    }

    /**
     * Nguồn dữ liệu DUY NHẤT: đọc thẳng crm_serial_warranty_claims (+ bảng liên quan) — không copy
     * sang bảng khác. Trả về mảng thuần để lọc/nhóm ở PHP (khối lượng Kho xử lý luôn nhỏ).
     */
    private function loadRows(int $company): \Illuminate\Support\Collection
    {
        $scope = function ($q) use ($company): void {
            if ($company > 0) {
                $q->where(fn ($w) => $w->where('company_id', $company)->orWhereNull('company_id'));
            }
        };

        $exchange = SolarWarrantyClaim::query()->where('claim_type', 'replacement')->tap($scope)
            ->whereIn('status', ['waiting_stock', 'reserved', 'issued', 'technician_received', 'replacing', 'waiting_faulty_return'])
            ->orWhere(function ($q) use ($scope): void {
                $q->where('claim_type', 'replacement')->tap($scope)->where('status', 'completed')->where('faulty_return_status', 'deferred');
            })
            ->with(['assignee:id,name', 'creator:id,name'])
            ->limit(500)->get();

        $unitIds = $exchange->pluck('serial_unit_id')->filter()->unique()->values();
        $devices = $unitIds->isEmpty() ? collect() : DB::table('crm_serial_units as su')
            ->join('crm_product_catalog as p', 'p.id', '=', 'su.product_id')
            ->whereIn('su.id', $unitIds)->pluck('p.name', 'su.id');

        $reservations = DB::table('warranty_serial_reservations')->whereIn('claim_id', $exchange->pluck('id'))
            ->where('status', 'active')->get()->keyBy('claim_id');

        $rows = collect();

        foreach ($exchange as $c) {
            $stage = match (true) {
                $c->status === 'waiting_stock' => 'waiting',
                $c->status === 'reserved' => 'reserved',
                in_array($c->status, ['issued', 'technician_received', 'replacing'], true) => 'issued',
                default => 'return', // waiting_faulty_return, completed+deferred
            };
            $res = $reservations->get($c->id);
            $rows->push([
                'claim_id' => $c->id,
                'type' => 'exchange',
                'type_label' => 'Đổi hàng bảo hành',
                'claim_code' => $c->claim_code,
                'customer' => (string) $c->customer_name,
                'customer_phone' => (string) $c->customer_phone,
                'product' => (string) ($devices[$c->serial_unit_id] ?? ''),
                'serial_old' => (string) $c->serial_code,
                'serial_new' => (string) ($c->reserved_serial_code ?: ''),
                'warehouse_id' => (int) ($res->warehouse_id ?? 0),
                'status' => $c->status,
                'status_label' => WarrantyFlow::EXCHANGE_STATUSES[$c->status] ?? $c->status,
                'requested_at' => (string) ($c->status_changed_at ?: $c->created_at),
                'creator' => $c->creator?->name ?: '—',
                'assigned_to' => (int) $c->assigned_to,
                'assignee' => $c->assignee?->name ?: '—',
                'stage' => $stage,
                'parts_summary' => null,
            ]);
        }

        $repair = SolarWarrantyClaim::query()->where('claim_type', 'paid_repair')->tap($scope)
            ->whereIn('status', ['approved_for_repair', 'waiting_parts', 'repairing', 'qa_testing', 'qa_failed', 'ready_handover', 'handed_over', 'completed'])
            ->whereIn('id', DB::table('warranty_repair_parts')->select('claim_id'))
            ->with(['assignee:id,name', 'creator:id,name'])
            ->limit(500)->get();

        $partsByClaim = DB::table('warranty_repair_parts as p')->leftJoin('crm_warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->whereIn('p.claim_id', $repair->pluck('id'))
            ->get(['p.claim_id', 'p.name', 'p.warehouse_id', 'w.name as warehouse_name', 'p.qty_planned', 'p.qty_reserved', 'p.qty_issued', 'p.qty_used', 'p.qty_returned', 'p.status'])
            ->groupBy('claim_id');

        foreach ($repair as $c) {
            $parts = $partsByClaim->get($c->id, collect());
            if ($parts->isEmpty()) {
                continue;
            }
            $allClosed = $parts->every(fn ($p) => in_array($p->status, ['closed'], true));
            $anyIssued = $parts->contains(fn ($p) => in_array($p->status, ['issued'], true));
            $anyReserved = $parts->contains(fn ($p) => $p->status === 'reserved');
            $anyPlanned = $parts->contains(fn ($p) => $p->status === 'planned');
            // Kỹ thuật đã qua giai đoạn sửa (QA/bàn giao/hoàn tất) mà vẫn còn linh kiện "issued" (có dư)
            // => việc còn lại là Kho nhận hoàn dư, không phải "chờ xuất" nữa.
            $pastRepairing = in_array($c->status, ['qa_testing', 'qa_failed', 'ready_handover', 'handed_over', 'completed'], true);
            $stage = match (true) {
                $anyIssued && $pastRepairing => 'return',
                $anyIssued => 'issued',
                $anyPlanned && ! $anyReserved => 'waiting',
                $anyReserved => 'reserved',
                $allClosed => 'return',
                default => 'waiting',
            };
            $rows->push([
                'claim_id' => $c->id,
                'type' => 'repair',
                'type_label' => 'Sửa chữa tính phí',
                'claim_code' => $c->claim_code,
                'customer' => (string) $c->customer_name,
                'customer_phone' => (string) $c->customer_phone,
                'product' => $parts->pluck('name')->filter()->implode(', '),
                'serial_old' => (string) $c->serial_code,
                'serial_new' => '',
                'warehouse_id' => (int) ($parts->first()->warehouse_id ?? 0),
                'status' => $c->status,
                'status_label' => WarrantyFlow::REPAIR_STATUSES[$c->status] ?? $c->status,
                'requested_at' => (string) ($c->status_changed_at ?: $c->created_at),
                'creator' => $c->creator?->name ?: '—',
                'assigned_to' => (int) $c->assigned_to,
                'assignee' => $c->assignee?->name ?: '—',
                'stage' => $stage,
                'parts_summary' => $parts->map(fn ($p) => sprintf('%s: YC %s / Giữ %s / Xuất %s / Dùng %s / Hoàn %s',
                    $p->name, self::n($p->qty_planned), self::n($p->qty_reserved), self::n($p->qty_issued), self::n($p->qty_used), self::n($p->qty_returned)))->implode(' | '),
            ]);
        }

        return $rows;
    }

    private static function n(mixed $v): string
    {
        $f = (float) $v;

        return rtrim(rtrim(number_format($f, 3, '.', ''), '0'), '.') ?: '0';
    }
}
