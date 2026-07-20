<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MaterialRequestStatus;
use App\Models\Projects\Site;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service quản lý công trình điện mặt trời (Site): CRUD, đợt thanh toán, thiết bị, vật tư, tài chính.
 */
class SiteService
{
    /**
     * Tìm công trình theo ID kèm vật tư dự kiến và đợt thanh toán.
     */
    public function find(int $id): Site
    {
        return Site::query()
            ->with(['plannedMaterials', 'paymentTerms'])
            ->findOrFail($id);
    }

    /**
     * Phân trang danh sách công trình, chỉ select các cột thực có trong bảng.
     */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $want = [
            'id',
            'name',
            'created_at',

            // hiển thị danh sách
            'status',
            'address',
            'contact_name',
            'contact_phone',
            'note',
            'system_kwp',
            'system_kw_ac',
            'system_type',
            'phase',
            'installed_at',
            'warranty_to',
            'technician_name',
            'monitoring_link',
            'monitoring_account',
            'stage',

            // tài chính
            'contract_amount',
            'contract_signed_at',
            'finance_note',
        ];

        $columns = [];

        foreach ($want as $col) {
            if (Schema::hasColumn('sites', $col)) {
                $columns[] = $col;
            }
        }

        if (empty($columns)) {
            $columns = ['id', 'name', 'created_at'];
        }

        return Site::query()
            ->select($columns)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Tạo công trình mới.
     */
    public function create(array $data): Site
    {
        return Site::create($data);
    }

    /**
     * Cập nhật công trình và trả về bản ghi mới nhất.
     */
    public function update(Site $site, array $data): Site
    {
        $site->update($data);

        return $site->refresh();
    }

    /**
     * Xoá công trình cùng các bản ghi con (thiết bị, vật tư dự kiến, đợt thanh toán).
     */
    public function delete(Site $site): void
    {
        DB::transaction(function () use ($site) {
            $childTables = [
                'site_devices',
                'site_planned_materials',
                'site_payment_terms',
            ];

            foreach ($childTables as $table) {
                if (
                    Schema::hasTable($table)
                    && Schema::hasColumn($table, 'site_id')
                ) {
                    DB::table($table)
                        ->where('site_id', $site->id)
                        ->delete();
                }
            }

            $site->delete();
        });
    }

    // =========================================================
    // PAYMENT TERMS - ĐỢT THANH TOÁN
    // =========================================================

    /**
     * Đồng bộ các đợt thanh toán: cập nhật/thêm mới, tính trạng thái theo tiền đã thu, archive đợt có phiếu thu.
     */
    public function syncPaymentTerms(
        Site $site,
        array $rows
    ): void {
        if (! Schema::hasTable('site_payment_terms')) {
            return;
        }

        DB::transaction(function () use ($site, $rows) {
            $existing = DB::table('site_payment_terms')
                ->where('site_id', $site->id)
                ->get()
                ->keyBy(
                    fn ($row) => (int) $row->id
                );

            $keptIds = [];
            $contractAmount = (float) (
                $site->contract_amount ?? 0
            );

            $now = now();

            foreach ($rows as $row) {
                $rowId = (int) ($row['id'] ?? 0);

                $name = trim(
                    (string) ($row['name'] ?? '')
                );

                $percent = $row['percent'] ?? null;
                $amount = $row['amount'] ?? null;
                $dueDate = $row['due_date'] ?? null;

                $note = trim(
                    (string) ($row['note'] ?? '')
                );

                $percent = (
                    $percent === null
                    || $percent === ''
                )
                    ? null
                    : (float) $percent;

                $amount = (
                    $amount === null
                    || $amount === ''
                )
                    ? 0
                    : (float) $amount;

                if (
                    $amount <= 0
                    && $percent !== null
                    && $percent > 0
                    && $contractAmount > 0
                ) {
                    $amount = round(
                        $contractAmount
                        * $percent
                        / 100,
                        2
                    );
                }

                if (
                    $name === ''
                    && $amount <= 0
                    && (
                        $percent === null
                        || $percent <= 0
                    )
                ) {
                    continue;
                }

                if ($name === '') {
                    $name = 'Đợt thanh toán';
                }

                $data = [
                    'name' => $name,
                    'percent' => $percent,
                    'amount' => $amount,
                    'due_date' => ! empty($dueDate)
                        ? $dueDate
                        : null,
                    'note' => $note !== ''
                        ? $note
                        : null,
                    'updated_at' => $now,
                ];

                if (
                    $rowId > 0
                    && $existing->has($rowId)
                ) {
                    $paid = 0.0;

                    if (
                        Schema::hasTable('receipts')
                        && Schema::hasColumn(
                            'receipts',
                            'site_payment_term_id'
                        )
                        && Schema::hasColumn(
                            'receipts',
                            'amount'
                        )
                    ) {
                        $paid = (float) DB::table(
                            'receipts'
                        )
                            ->where(
                                'site_payment_term_id',
                                $rowId
                            )
                            ->sum('amount');
                    }

                    if ($paid <= 0) {
                        $data['status'] = 'pending';
                    } elseif (
                        $amount > 0
                        && $paid + 0.5 >= $amount
                    ) {
                        $data['status'] = 'paid';
                    } else {
                        $data['status'] = 'partial';
                    }

                    DB::table('site_payment_terms')
                        ->where('id', $rowId)
                        ->where('site_id', $site->id)
                        ->update($data);

                    $keptIds[] = $rowId;

                    continue;
                }

                $data['site_id'] = $site->id;
                $data['status'] = 'pending';
                $data['created_at'] = $now;

                $newId = DB::table(
                    'site_payment_terms'
                )->insertGetId($data);

                $keptIds[] = (int) $newId;
            }

            $removedIds = $existing
                ->keys()
                ->map(
                    fn ($id) => (int) $id
                )
                ->diff($keptIds)
                ->values();

            foreach ($removedIds as $removedId) {
                $hasReceipts = (
                    Schema::hasTable('receipts')
                    && Schema::hasColumn(
                        'receipts',
                        'site_payment_term_id'
                    )
                    && DB::table('receipts')
                        ->where(
                            'site_payment_term_id',
                            $removedId
                        )
                        ->exists()
                );

                if ($hasReceipts) {
                    DB::table('site_payment_terms')
                        ->where('id', $removedId)
                        ->update([
                            'status' => 'archived',
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('site_payment_terms')
                        ->where('id', $removedId)
                        ->delete();
                }
            }
        });
    }

    /**
     * Lấy danh sách đợt thanh toán (trừ archived) kèm số tiền đã thu, còn lại và trạng thái tính toán.
     */
    public function getPaymentTerms(Site $site): array
    {
        if (! Schema::hasTable('site_payment_terms')) {
            return [];
        }

        $canReadReceipts =
            Schema::hasTable('receipts')
            && Schema::hasColumn('receipts', 'amount')
            && Schema::hasColumn('receipts', 'site_payment_term_id');

        return DB::table('site_payment_terms')
            ->where('site_id', $site->id)
            ->where(function ($query) {
                $query
                    ->whereNull('status')
                    ->orWhere('status', '!=', 'archived');
            })
            ->orderBy('id')
            ->get()
            ->map(function ($r) use ($canReadReceipts) {
                $termId = (int) ($r->id ?? 0);
                $amount = (float) ($r->amount ?? 0);

                $paidAmount = 0.0;

                if ($canReadReceipts && $termId > 0) {
                    $paidAmount = (float) DB::table('receipts')
                        ->where('site_payment_term_id', $termId)
                        ->sum('amount');
                }

                $remainingAmount = max(0, $amount - $paidAmount);

                $computedStatus = 'pending';
                if ($amount > 0 && $paidAmount >= $amount) {
                    $computedStatus = 'paid';
                } elseif ($paidAmount > 0) {
                    $computedStatus = 'partial';
                }

                return [
                    'id' => $termId,
                    'name' => (string) ($r->name ?? ''),
                    'percent' => (string) ($r->percent ?? ''),
                    'amount' => (string) ($r->amount ?? ''),
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'computed_status' => $computedStatus,
                    'due_date' => ! empty($r->due_date) ? (string) $r->due_date : '',
                    'status' => (string) ($r->status ?? 'pending'),
                    'note' => (string) ($r->note ?? ''),
                ];
            })
            ->toArray();
    }

    /**
     * Lấy danh sách phiếu thu của công trình (tối đa 100, mới nhất trước).
     */
    public function getPaymentReceipts(Site $site): array
    {
        if (! Schema::hasTable('receipts')) {
            return [];
        }

        if (! Schema::hasColumn('receipts', 'site_id')) {
            return [];
        }

        $select = [];

        $select[] = Schema::hasColumn('receipts', 'id')
            ? 'r.id'
            : DB::raw('0 as id');

        $select[] = Schema::hasColumn('receipts', 'site_id')
            ? 'r.site_id'
            : DB::raw('0 as site_id');

        $select[] = Schema::hasColumn('receipts', 'site_payment_term_id')
            ? 'r.site_payment_term_id'
            : DB::raw('NULL as site_payment_term_id');

        $select[] = Schema::hasColumn('receipts', 'amount')
            ? 'r.amount'
            : DB::raw('0 as amount');

        $select[] = Schema::hasColumn('receipts', 'payment_method')
            ? 'r.payment_method'
            : DB::raw("'' as payment_method");

        $select[] = Schema::hasColumn('receipts', 'paid_at')
            ? 'r.paid_at'
            : DB::raw('NULL as paid_at');

        $select[] = Schema::hasColumn('receipts', 'note')
            ? 'r.note'
            : DB::raw("'' as note");

        $select[] = Schema::hasColumn('receipts', 'created_at')
            ? 'r.created_at'
            : DB::raw('NULL as created_at');

        $query = DB::table('receipts as r')
            ->where('r.site_id', $site->id);

        if (
            Schema::hasTable('site_payment_terms')
            && Schema::hasColumn('receipts', 'site_payment_term_id')
        ) {
            $query->leftJoin('site_payment_terms as spt', 'spt.id', '=', 'r.site_payment_term_id');
            $select[] = DB::raw('spt.name as term_name');
        } else {
            $select[] = DB::raw("'' as term_name");
        }

        $query->select($select);

        if (Schema::hasColumn('receipts', 'paid_at')) {
            $query->orderByDesc('r.paid_at');
        } elseif (Schema::hasColumn('receipts', 'created_at')) {
            $query->orderByDesc('r.created_at');
        } else {
            $query->orderByDesc('r.id');
        }

        return $query
            ->limit(100)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => (int) ($r->id ?? 0),
                    'site_id' => (int) ($r->site_id ?? 0),
                    'site_payment_term_id' => ! empty($r->site_payment_term_id) ? (int) $r->site_payment_term_id : null,
                    'term_name' => (string) ($r->term_name ?? ''),
                    'amount' => (float) ($r->amount ?? 0),
                    'payment_method' => (string) ($r->payment_method ?? ''),
                    'paid_at' => ! empty($r->paid_at) ? (string) $r->paid_at : '',
                    'note' => (string) ($r->note ?? ''),
                    'created_at' => ! empty($r->created_at) ? (string) $r->created_at : '',
                ];
            })
            ->toArray();
    }

    /**
     * Tổng hợp tài chính công trình: đã thu, còn phải thu, chi phí vật tư/khác, lợi nhuận gộp.
     */
    public function getFinanceSummary(Site $site): array
    {
        $siteId = (int) $site->id;
        $contractAmount = (float) ($site->contract_amount ?? 0);

        $receivedAmount = 0;
        if (
            Schema::hasTable('receipts')
            && Schema::hasColumn('receipts', 'site_id')
            && Schema::hasColumn('receipts', 'amount')
        ) {
            $receivedAmount = (float) DB::table('receipts')
                ->where('site_id', $siteId)
                ->sum('amount');
        }

        $materialCost = 0;
        if (
            Schema::hasTable('material_requests')
            && Schema::hasColumn('material_requests', 'site_id')
            && Schema::hasColumn('material_requests', 'total_cost')
        ) {
            $query = DB::table('material_requests')
                ->where('site_id', $siteId);

            if (Schema::hasColumn('material_requests', 'status')) {
                $query->where('status', MaterialRequestStatus::EXPORTED->value);
            }

            $materialCost = (float) $query->sum('total_cost');
        }

        $otherPaymentCost = 0;
        if (
            Schema::hasTable('payments')
            && Schema::hasColumn('payments', 'site_id')
            && Schema::hasColumn('payments', 'amount')
        ) {
            $otherPaymentCost = (float) DB::table('payments')
                ->where('site_id', $siteId)
                ->sum('amount');
        }

        $approvedRequestCost = 0;
        if (
            Schema::hasTable('payment_requests')
            && Schema::hasColumn('payment_requests', 'site_id')
            && Schema::hasColumn('payment_requests', 'amount')
        ) {
            $query = DB::table('payment_requests')
                ->where('site_id', $siteId);

            if (Schema::hasColumn('payment_requests', 'status')) {
                $query->where('status', 'accounting_approved');
            }

            $approvedRequestCost = (float) $query->sum('amount');
        }

        $otherCost = $otherPaymentCost + $approvedRequestCost;
        $totalCost = $materialCost + $otherCost;
        $remainingReceivable = max(0, $contractAmount - $receivedAmount);
        $grossProfit = $contractAmount - $totalCost;

        return [
            'contract_amount' => $contractAmount,
            'received_amount' => $receivedAmount,
            'remaining_receivable' => $remainingReceivable,
            'material_cost' => $materialCost,
            'other_cost' => $otherCost,
            'total_cost' => $totalCost,
            'gross_profit' => $grossProfit,
        ];
    }

    // =========================================================
    // SYSTEM SUMMARY
    // =========================================================

    /**
     * Tổng hợp thông số hệ thống (kWp, kW inverter, kWh pin lưu trữ) từ danh sách thiết bị.
     */
    public function getSystemSummary(Site $site, array $devices = []): array
    {
        $siteKwp = (float) ($site->system_kwp ?? 0);
        $siteKwAc = (float) ($site->system_kw_ac ?? 0);

        $inverterKw = 0.0;
        $batteryKwh = 0.0;
        $pvKwp = 0.0;
        $deviceCount = 0;

        foreach ($devices as $d) {
            $type = strtolower((string) ($d['type'] ?? ''));
            $qty = max((int) ($d['qty'] ?? 1), 1);
            $powerKw = (float) ($d['power_kw'] ?? 0);
            $capacityKwh = (float) ($d['capacity_kwh'] ?? 0);

            // Không đếm dòng chỉ có type mặc định, ví dụ Inverter trống.
            $hasAny =
                ! empty($d['brand'])
                || ! empty($d['model'])
                || ! empty($d['serial'])
                || $powerKw > 0
                || $capacityKwh > 0;

            if ($hasAny) {
                $deviceCount++;
            }

            if (str_contains($type, 'inverter')) {
                $inverterKw += $powerKw * $qty;
            }

            if (str_contains($type, 'battery') || str_contains($type, 'pin') || str_contains($type, 'storage')) {
                $batteryKwh += $capacityKwh * $qty;
            }

            if (
                str_contains($type, 'pv')
                || str_contains($type, 'solar')
                || str_contains($type, 'panel')
                || str_contains($type, 'tấm')
            ) {
                $pvKwp += $powerKw * $qty;
            }
        }

        if ($pvKwp <= 0 && $siteKwp > 0) {
            $pvKwp = $siteKwp;
        }

        if ($inverterKw <= 0 && $siteKwAc > 0) {
            $inverterKw = $siteKwAc;
        }

        return [
            'system_kwp' => $siteKwp,
            'system_kw_ac' => $siteKwAc,
            'inverter_kw' => $inverterKw,
            'battery_kwh' => $batteryKwh,
            'pv_kwp' => $pvKwp,
            'device_count' => $deviceCount,
            'system_type' => (string) ($site->system_type ?? ''),
            'phase' => (string) ($site->phase ?? ''),
            'installed_at' => ! empty($site->installed_at) ? (string) $site->installed_at : '',
            'warranty_to' => ! empty($site->warranty_to) ? (string) $site->warranty_to : '',
            'technician_name' => (string) ($site->technician_name ?? ''),
            'monitoring_link' => (string) ($site->monitoring_link ?? ''),
            'monitoring_account' => (string) ($site->monitoring_account ?? ''),
            'stage' => (string) ($site->stage ?? ''),
        ];
    }

    // =========================================================
    // PLANNED MATERIALS
    // =========================================================

    /**
     * Đồng bộ vật tư dự kiến: xoá toàn bộ và insert lại các dòng hợp lệ.
     */
    public function syncPlannedMaterials(Site $site, array $plannedRows): void
    {
        if (! Schema::hasTable('site_planned_materials')) {
            return;
        }

        DB::transaction(function () use ($site, $plannedRows) {
            DB::table('site_planned_materials')
                ->where('site_id', $site->id)
                ->delete();

            $now = now();
            $inserts = [];

            foreach ($plannedRows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $unit = trim((string) ($row['unit'] ?? ''));
                $qty = (float) ($row['qty'] ?? 0);

                if ($name === '' || $qty <= 0) {
                    continue;
                }

                $inserts[] = [
                    'site_id' => $site->id,
                    'name' => $name,
                    'unit' => $unit,
                    'qty' => $qty,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($inserts)) {
                DB::table('site_planned_materials')->insert($inserts);
            }
        });
    }

    /**
     * Lấy danh sách vật tư dự kiến của công trình.
     */
    public function getPlannedMaterials(Site $site): array
    {
        if (! Schema::hasTable('site_planned_materials')) {
            return [];
        }

        return DB::table('site_planned_materials')
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => [
                'name' => (string) ($r->name ?? ''),
                'unit' => (string) ($r->unit ?? ''),
                'qty' => (string) ($r->qty ?? ''),
            ])
            ->toArray();
    }

    /**
     * Lấy vật tư dự kiến cho trang sửa; fallback từ vật tư thực xuất (bỏ thiết bị chính) nếu chưa có.
     */
    public function getEditPlannedMaterials(Site $site): array
    {
        $savedPlanned = $this->getPlannedMaterials($site);

        if (! empty($savedPlanned)) {
            return $savedPlanned;
        }

        return $this->getActualMaterialsForEdit($site)
            ->reject(fn ($item) => $this->isMainActualMaterial($item))
            ->map(function ($item) {
                return [
                    'name' => $this->actualMaterialName($item),
                    'unit' => $this->actualMaterialUnit($item),
                    'qty' => (string) ($item->qty ?? 0),
                ];
            })
            ->values()
            ->toArray();
    }

    // =========================================================
    // DEVICES
    // =========================================================

    /**
     * Lấy danh sách thiết bị của công trình.
     */
    public function getDevices(Site $site): array
    {
        if (! Schema::hasTable('site_devices')) {
            return [];
        }

        return DB::table('site_devices')
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => [
                'type' => (string) ($r->type ?? ''),
                'brand' => (string) ($r->brand ?? ''),
                'model' => (string) ($r->model ?? ''),
                'serial' => (string) ($r->serial ?? ''),
                'power_kw' => (string) ($r->power_kw ?? ''),
                'capacity_kwh' => (string) ($r->capacity_kwh ?? ''),
                'qty' => (int) ($r->qty ?? 1),
                'warranty_to' => ! empty($r->warranty_to) ? (string) $r->warranty_to : '',
            ])
            ->toArray();
    }

    /**
     * Lấy thiết bị cho trang sửa; fallback từ thiết bị chính trong vật tư thực xuất nếu chưa lưu.
     */
    public function getEditDevices(Site $site): array
    {
        $savedDevices = collect($this->getDevices($site))
            ->filter(function ($d) {
                $brand = trim((string) ($d['brand'] ?? ''));
                $model = trim((string) ($d['model'] ?? ''));
                $serial = trim((string) ($d['serial'] ?? ''));
                $powerKw = (float) ($d['power_kw'] ?? 0);
                $capacityKwh = (float) ($d['capacity_kwh'] ?? 0);

                // Không tính dòng chỉ có type mặc định Inverter là dữ liệu thật.
                return $brand !== ''
                    || $model !== ''
                    || $serial !== ''
                    || $powerKw > 0
                    || $capacityKwh > 0;
            })
            ->values()
            ->toArray();

        if (! empty($savedDevices)) {
            return $savedDevices;
        }

        return $this->getActualMaterialsForEdit($site)
            ->filter(fn ($item) => $this->isMainActualMaterial($item))
            ->map(function ($item) {
                $name = $this->actualMaterialName($item);

                return [
                    'type' => $this->detectDeviceType($name, (string) ($item->note ?? '')),
                    'brand' => '',
                    'model' => $name,
                    'serial' => '',
                    'power_kw' => '',
                    'capacity_kwh' => '',
                    'qty' => (int) max(1, (float) ($item->qty ?? 1)),
                    'warranty_to' => '',
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Đồng bộ thiết bị công trình: xoá toàn bộ và insert lại, bỏ qua dòng rỗng.
     */
    public function syncDevices(Site $site, array $deviceRows): void
    {
        if (! Schema::hasTable('site_devices')) {
            return;
        }

        DB::transaction(function () use ($site, $deviceRows) {
            DB::table('site_devices')
                ->where('site_id', $site->id)
                ->delete();

            $now = now();
            $inserts = [];

            foreach ($deviceRows as $row) {
                $type = trim((string) ($row['type'] ?? ''));
                $brand = trim((string) ($row['brand'] ?? ''));
                $model = trim((string) ($row['model'] ?? ''));
                $serial = trim((string) ($row['serial'] ?? ''));
                $powerKw = (float) ($row['power_kw'] ?? 0);
                $capacityKwh = (float) ($row['capacity_kwh'] ?? 0);
                $qty = (int) ($row['qty'] ?? 1);
                $warrantyTo = $row['warranty_to'] ?? null;

                // Không lưu dòng rỗng chỉ có type mặc định, ví dụ Inverter.
                $hasAny =
                    $brand !== ''
                    || $model !== ''
                    || $serial !== ''
                    || $powerKw > 0
                    || $capacityKwh > 0;

                if (! $hasAny) {
                    continue;
                }

                if ($type === '') {
                    $type = 'other';
                }

                if ($qty < 1) {
                    $qty = 1;
                }

                $inserts[] = [
                    'site_id' => $site->id,
                    'type' => $type,
                    'brand' => $brand,
                    'model' => $model,
                    'serial' => $serial,
                    'power_kw' => $powerKw > 0 ? $powerKw : null,
                    'capacity_kwh' => $capacityKwh > 0 ? $capacityKwh : null,
                    'qty' => $qty,
                    'warranty_to' => ! empty($warrantyTo) ? $warrantyTo : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($inserts)) {
                DB::table('site_devices')->insert($inserts);
            }
        });
    }

    // =========================================================
    // ACTUAL MATERIALS FALLBACK FOR EDIT PAGE
    // =========================================================

    /**
     * Lấy vật tư thực đã xuất kho của công trình (dùng làm fallback cho trang sửa).
     */
    private function getActualMaterialsForEdit(Site $site)
    {
        if (
            ! Schema::hasTable('material_request_items')
            || ! Schema::hasTable('material_requests')
        ) {
            return collect();
        }

        $select = [
            'mri.id',
            'mri.material_request_id',
            'mri.product_id',
            'mri.qty',
            'mri.note',
            'mr.created_at as request_created_at',
            'mr.status as request_status',
        ];

        if (Schema::hasColumn('material_request_items', 'unit')) {
            $select[] = 'mri.unit';
        } else {
            $select[] = DB::raw("'' as unit");
        }

        if (Schema::hasColumn('material_request_items', 'unit_cost')) {
            $select[] = 'mri.unit_cost';
        } else {
            $select[] = DB::raw('0 as unit_cost');
        }

        if (Schema::hasColumn('material_request_items', 'vat_percent')) {
            $select[] = 'mri.vat_percent';
        } else {
            $select[] = DB::raw('0 as vat_percent');
        }

        if (Schema::hasColumn('material_request_items', 'line_total')) {
            $select[] = 'mri.line_total';
        } else {
            $select[] = DB::raw('0 as line_total');
        }

        if (Schema::hasTable('crm_product_catalog')) {
            $select[] = 'p.name as product_name';
            $select[] = 'p.sku as product_sku';
            $select[] = 'p.unit as product_unit';

            $query = DB::table('material_request_items as mri')
                ->join('material_requests as mr', 'mr.id', '=', 'mri.material_request_id')
                ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'mri.product_id');
        } else {
            $select[] = DB::raw("'' as product_name");
            $select[] = DB::raw("'' as product_sku");
            $select[] = DB::raw("'' as product_unit");

            $query = DB::table('material_request_items as mri')
                ->join('material_requests as mr', 'mr.id', '=', 'mri.material_request_id');
        }

        return $query
            ->where('mr.site_id', $site->id)
            ->where('mr.status', MaterialRequestStatus::EXPORTED->value)
            ->select($select)
            ->orderByDesc('mr.id')
            ->orderBy('mri.id')
            ->get();
    }

    /**
     * Loại bỏ các tag phân loại ([Thiết bị chính/Vật tư phụ...]) khỏi ghi chú vật tư.
     */
    private function cleanActualMaterialNote(?string $note): string
    {
        $note = (string) $note;

        $note = preg_replace(
            '/\[(Thiết bị chính - Trong kho|Vật tư phụ - Trong kho|Thiết bị chính - Ngoài kho|Vật tư phụ - Ngoài kho)\]\s*/u',
            '',
            $note
        );

        return trim((string) $note);
    }

    /**
     * Xác định tên vật tư: ưu tiên tên sản phẩm, fallback từ ghi chú.
     */
    private function actualMaterialName(object $item): string
    {
        if (! empty($item->product_id) && ! empty($item->product_name)) {
            return (string) $item->product_name;
        }

        $note = $this->cleanActualMaterialNote($item->note ?? '');

        $parts = array_values(array_filter(array_map('trim', explode('|', $note)), function ($part) {
            return $part !== '';
        }));

        return $parts[0] ?? 'Vật tư ngoài kho';
    }

    /**
     * Xác định đơn vị tính vật tư: từ dòng item, sản phẩm, hoặc ghi chú (ĐVT:...).
     */
    private function actualMaterialUnit(object $item): string
    {
        if (! empty($item->unit)) {
            return (string) $item->unit;
        }

        if (! empty($item->product_unit)) {
            return (string) $item->product_unit;
        }

        $note = (string) ($item->note ?? '');

        $parts = array_values(array_filter(array_map('trim', explode('|', $note)), function ($part) {
            return $part !== '';
        }));

        foreach ($parts as $part) {
            if (preg_match('/^ĐVT:\s*(.*)$/u', $part, $matches)) {
                return trim((string) ($matches[1] ?? ''));
            }
        }

        return '';
    }

    /**
     * Kiểm tra vật tư có phải thiết bị chính hay không (dựa vào tag trong ghi chú).
     */
    private function isMainActualMaterial(object $item): bool
    {
        $note = (string) ($item->note ?? '');

        if (str_contains($note, '[Thiết bị chính - Trong kho]')) {
            return true;
        }

        if (str_contains($note, '[Thiết bị chính - Ngoài kho]')) {
            return true;
        }

        if (str_contains($note, '[Vật tư phụ - Trong kho]')) {
            return false;
        }

        if (str_contains($note, '[Vật tư phụ - Ngoài kho]')) {
            return false;
        }

        return ! empty($item->product_id);
    }

    /**
     * Suy đoán loại thiết bị (inverter, battery, solar_panel...) từ tên và ghi chú.
     */
    private function detectDeviceType(string $name, string $note = ''): string
    {
        $text = mb_strtolower($name.' '.$note, 'UTF-8');

        if (str_contains($text, 'hybrid')) {
            return 'hybrid_inverter';
        }

        if (str_contains($text, 'inverter')) {
            return 'inverter';
        }

        if (
            str_contains($text, 'pin lưu trữ')
            || str_contains($text, 'battery')
            || str_contains($text, 'storage')
        ) {
            return 'battery';
        }

        if (
            str_contains($text, 'tấm pin')
            || str_contains($text, 'nlmt')
            || str_contains($text, 'solar')
            || str_contains($text, 'panel')
            || str_contains($text, 'pv')
        ) {
            return 'solar_panel';
        }

        if (str_contains($text, 'meter')) {
            return 'meter';
        }

        if (str_contains($text, 'tủ ac')) {
            return 'ac_panel';
        }

        if (str_contains($text, 'tủ dc')) {
            return 'dc_panel';
        }

        return 'other';
    }
}
