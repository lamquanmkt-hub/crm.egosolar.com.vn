<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SolarMaintenanceIssueController extends Controller
{
    private const CLOSED = ['resolved', 'completed', 'closed', 'rejected', 'cancelled'];

    private const STATUSES = [
        'received' => 'Mới tiếp nhận',
        'processing' => 'Đang xử lý',
        'pending_replacement' => 'Chờ đổi thiết bị',
        'replacement_pending' => 'Chờ thiết bị thay thế',
        'approved_replacement' => 'Đã duyệt đổi',
        'waiting_device' => 'Đang chờ thiết bị',
        'resolved' => 'Đã xử lý',
        'completed' => 'Hoàn tất',
        'closed' => 'Đã đóng',
        'rejected' => 'Từ chối',
        'cancelled' => 'Đã hủy',
    ];

    public function index(Request $request): View|RedirectResponse
    {
        if (! SolarMaintenanceAccess::canViewAny($request->user())) {
            return redirect()->route('dashboard')->with('error', 'Bạn chưa có quyền xem Phiếu sự cố & Bảo hành.');
        }

        $keyword = trim((string) $request->query('q', ''));
        $mode = trim((string) $request->query('mode', 'open'));
        if (! in_array($mode, ['open', 'replacement', 'done', 'all'], true)) {
            $mode = 'open';
        }

        if (! Schema::hasTable('crm_serial_warranty_claims')) {
            return view('technical.maintenance.issues', [
                'claims' => $this->emptyPaginator($request),
                'summary' => ['open' => 0, 'processing' => 0, 'replacement' => 0, 'resolved' => 0],
                'statusLabels' => self::STATUSES,
                'mode' => $mode,
                'canManage' => SolarMaintenanceAccess::canManage($request->user()),
            ]);
        }

        $base = DB::table('crm_serial_warranty_claims as cl')
            ->leftJoin('crm_serial_units as su', 'su.id', '=', 'cl.serial_unit_id')
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'su.product_id')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'cl.customer_id')
            ->leftJoin('crm_orders as o', 'o.id', '=', 'cl.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'cl.created_by')
            ->select([
                'cl.id', 'cl.serial_unit_id', 'cl.serial_code', 'cl.customer_id', 'cl.order_id',
                'cl.status', 'cl.received_at', 'cl.resolved_at', 'cl.issue_description',
                'cl.resolution', 'cl.cost', 'cl.created_at', 'cl.updated_at', 'p.name as product_name',
                'c.name as customer_name', 'o.order_code', 'u.name as creator_name',
            ]);

        if ($mode === 'open') {
            $base->whereNotIn('cl.status', self::CLOSED);
        } elseif ($mode === 'replacement') {
            $base->whereNotIn('cl.status', self::CLOSED)
                ->where(function (Builder $query): void {
                    $query->whereIn('cl.status', [
                        'pending_replacement', 'replacement_pending', 'approved_replacement', 'waiting_device',
                    ])->orWhere('cl.resolution', 'like', '%đổi%')
                        ->orWhere('cl.resolution', 'like', '%thay%')
                        ->orWhere('cl.issue_description', 'like', '%đổi%')
                        ->orWhere('cl.issue_description', 'like', '%thay%');
                });
        } elseif ($mode === 'done') {
            $base->whereIn('cl.status', self::CLOSED);
        }

        if ($keyword !== '') {
            $base->where(function (Builder $builder) use ($keyword): void {
                $builder->where('cl.serial_code', 'like', "%{$keyword}%")
                    ->orWhere('p.name', 'like', "%{$keyword}%")
                    ->orWhere('c.name', 'like', "%{$keyword}%")
                    ->orWhere('o.order_code', 'like', "%{$keyword}%")
                    ->orWhere('cl.issue_description', 'like', "%{$keyword}%");
            });
        }

        $claims = $base->orderByRaw("CASE WHEN cl.status IN ('received','processing','pending_replacement','replacement_pending','waiting_device') THEN 0 ELSE 1 END")
            ->orderByDesc('cl.id')
            ->paginate(20)
            ->withQueryString();

        $summaryBase = DB::table('crm_serial_warranty_claims');
        $summary = [
            'open' => (clone $summaryBase)->whereNotIn('status', self::CLOSED)->count(),
            'processing' => (clone $summaryBase)->where('status', 'processing')->count(),
            'replacement' => (clone $summaryBase)->whereIn('status', [
                'pending_replacement', 'replacement_pending', 'approved_replacement', 'waiting_device',
            ])->count(),
            'resolved' => (clone $summaryBase)->whereIn('status', ['resolved', 'completed', 'closed'])->count(),
        ];

        return view('technical.maintenance.issues', [
            'claims' => $claims,
            'summary' => $summary,
            'statusLabels' => self::STATUSES,
            'mode' => $mode,
            'canManage' => SolarMaintenanceAccess::canManage($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! SolarMaintenanceAccess::canManage($request->user())) {
            abort(403);
        }

        if (! Schema::hasTable('crm_serial_warranty_claims')) {
            return back()->with('error', 'Hệ thống chưa có bảng dữ liệu phiếu bảo hành.');
        }

        $data = $request->validate([
            'serial_code' => ['required', 'string', 'max:255'],
            'issue_description' => ['required', 'string', 'max:5000'],
        ]);

        $row = $this->findSerial($data['serial_code']);
        if (! $row) {
            return back()->withInput()->with('error', 'Không tìm thấy serial trong hệ thống. Hãy tra cứu serial trước khi tạo phiếu.');
        }

        $claimId = DB::table('crm_serial_warranty_claims')->insertGetId([
            'serial_unit_id' => $row->serial_unit_id,
            'serial_code' => $row->serial_code,
            'customer_id' => $row->customer_id ?? null,
            'order_id' => $row->order_id ?? null,
            'status' => 'received',
            'received_at' => now()->toDateString(),
            'issue_description' => trim($data['issue_description']),
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logEvent($row, 'warranty_claim', 'Tiếp nhận phiếu sự cố #'.$claimId.': '.$data['issue_description']);

        return redirect()->route('ky-thuat.maintenance.issues', ['mode' => 'open'])
            ->with('success', 'Đã tạo phiếu sự cố #'.$claimId.' cho serial '.$row->serial_code.'.');
    }

    public function update(Request $request, int $claim): RedirectResponse
    {
        if (! SolarMaintenanceAccess::canManage($request->user())) {
            abort(403);
        }

        if (! Schema::hasTable('crm_serial_warranty_claims')) {
            return back()->with('error', 'Không tìm thấy dữ liệu phiếu bảo hành.');
        }

        $row = DB::table('crm_serial_warranty_claims')->where('id', $claim)->first();
        if (! $row) {
            return back()->with('error', 'Phiếu sự cố không tồn tại.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'resolution' => ['nullable', 'string', 'max:5000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        $update = [
            'status' => $data['status'],
            'resolution' => trim((string) ($data['resolution'] ?? '')) ?: null,
            'cost' => (float) ($data['cost'] ?? 0),
            'updated_at' => now(),
        ];

        if (in_array($data['status'], ['resolved', 'completed', 'closed'], true)) {
            $update['resolved_at'] = $row->resolved_at ?: now()->toDateString();
        } elseif (! in_array($data['status'], self::CLOSED, true)) {
            $update['resolved_at'] = null;
        }

        DB::table('crm_serial_warranty_claims')->where('id', $claim)->update($update);

        $serial = (object) [
            'serial_unit_id' => $row->serial_unit_id,
            'serial_code' => $row->serial_code,
            'customer_id' => $row->customer_id,
            'order_id' => $row->order_id,
            'state' => null,
            'warehouse_id' => null,
        ];
        $this->logEvent($serial, 'warranty_claim_update', 'Phiếu #'.$claim.' → '.(self::STATUSES[$data['status']] ?? $data['status']).'. '.($update['resolution'] ?: ''));

        return back()->with('success', 'Đã cập nhật phiếu sự cố #'.$claim.'.');
    }

    private function findSerial(string $code): ?object
    {
        $required = [
            'crm_serial_units', 'crm_serial_unit_identifiers', 'crm_serial_identifiers',
            'crm_serial_unit_states', 'crm_serial_warranties',
        ];
        foreach ($required as $table) {
            if (! Schema::hasTable($table)) {
                return null;
            }
        }

        return DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', function ($join): void {
                $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
            ->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id')
            ->where('si.code', trim($code))
            ->select([
                'su.id as serial_unit_id', 'si.code as serial_code', 'st.state', 'st.warehouse_id',
                'wa.customer_id', 'wa.order_id',
            ])->first();
    }

    private function logEvent(object $row, string $type, ?string $note): void
    {
        if (! Schema::hasTable('crm_serial_warranty_events')) {
            return;
        }

        DB::table('crm_serial_warranty_events')->insert([
            'serial_unit_id' => $row->serial_unit_id ?? null,
            'serial_code' => $row->serial_code ?? null,
            'event_type' => $type,
            'from_state' => $row->state ?? null,
            'to_state' => $row->state ?? null,
            'from_warehouse_id' => $row->warehouse_id ?? null,
            'to_warehouse_id' => $row->warehouse_id ?? null,
            'customer_id' => $row->customer_id ?? null,
            'order_id' => $row->order_id ?? null,
            'created_by' => auth()->id(),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function emptyPaginator(Request $request)
    {
        return new \Illuminate\Pagination\LengthAwarePaginator(
            collect(), 0, 20, max(1, (int) $request->query('page', 1)),
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
