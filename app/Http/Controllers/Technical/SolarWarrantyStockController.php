<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\Core\Warehouse;
use App\Models\SolarWarrantyClaim;
use App\Models\SolarWarrantyStockMovement;
use App\Support\Synced\EgoCompanyScope;
use App\Support\SchemaCache;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SolarWarrantyStockController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(SolarMaintenanceAccess::canHandleWarrantyStock($request->user()), 403);

        $data = $request->validateWithBag('warrantyStock', [
            'warranty_claim_id' => ['required', 'integer', 'exists:crm_serial_warranty_claims,id'],
            'movement_type' => ['required', Rule::in(array_keys(SolarWarrantyStockMovement::TYPES))],
            'warehouse_id' => ['required', 'integer', 'exists:crm_warehouses,id'],
            'serial_code' => ['required', 'string', 'max:190'],
            'related_serial_code' => ['nullable', 'string', 'max:190'],
            'quantity' => ['nullable', 'numeric', 'min:1', 'max:1'],
            'note' => ['nullable', 'string', 'max:5000'],
            'return_to' => ['nullable', Rule::in(['warranty_exchange'])],
        ]);

        try {
            $claim = SolarWarrantyClaim::with('site')->findOrFail((int) $data['warranty_claim_id']);
            $this->assertCompany((int) ($claim->company_id ?: $claim->site?->company_id), $request);

            if (\App\Support\Warranty\WarrantyFlow::isFlowType((string) $claim->claim_type)) {
                throw ValidationException::withMessages([
                    'warranty_claim_id' => 'Phiếu đổi hàng/sửa chữa dùng thao tác Kho riêng trong trang chi tiết phiếu (giữ hàng → xuất → thu hồi).',
                ]);
            }

            if (! in_array($claim->status, ['approved', 'waiting_stock', 'replacing', 'waiting_customer'], true)) {
                throw ValidationException::withMessages([
                    'warranty_claim_id' => 'Phiếu bảo hành phải được Trưởng phòng duyệt trước khi Kho xuất đổi hoặc thu hồi thiết bị.',
                ]);
            }

            $claimCompanyId = (int) ($claim->company_id ?: $claim->site?->company_id ?: EgoCompanyScope::currentId());
            $warehouse = Warehouse::query()->findOrFail((int) $data['warehouse_id']);
            $this->assertWarehouseCompany($warehouse, $claimCompanyId);

            $serial = $this->resolveSerial($data['serial_code']);
            $related = ! empty($data['related_serial_code']) ? $this->resolveSerial($data['related_serial_code']) : null;
            $this->assertSerialCompany($serial, $claimCompanyId, $request);
            if ($related) {
                $this->assertSerialCompany($related, $claimCompanyId, $request);
            }
            $this->assertMovementSerials($claim, $data['movement_type'], $serial);
            $this->assertSerialAvailableForMovement($data['movement_type'], $serial, (int) $warehouse->id);

            $duplicated = SolarWarrantyStockMovement::query()
                ->where('serial_unit_id', $serial->serial_unit_id)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();
            if ($duplicated) {
                throw ValidationException::withMessages([
                    'serial_code' => 'Serial này đã có một phiếu kho đang chờ xử lý.',
                ]);
            }

            $alreadyCompleted = SolarWarrantyStockMovement::query()
                ->where('warranty_claim_id', $claim->id)
                ->where('movement_type', $data['movement_type'])
                ->where('serial_unit_id', $serial->serial_unit_id)
                ->where('status', 'completed')
                ->exists();
            if ($alreadyCompleted) {
                throw ValidationException::withMessages([
                    'serial_code' => 'Nghiệp vụ này đã hoàn thành cho serial đã chọn; không thể ghi nhận trùng lần nữa.',
                ]);
            }

            $movement = DB::transaction(function () use ($data, $claim, $serial, $related, $request) {
                $movement = SolarWarrantyStockMovement::create([
                    'company_id' => $claim->company_id ?: $claim->site?->company_id ?: EgoCompanyScope::currentId(),
                    'warranty_claim_id' => $claim->id,
                    'site_id' => $claim->site_id,
                    'movement_type' => $data['movement_type'],
                    'status' => 'pending',
                    'warehouse_id' => $data['warehouse_id'] ?? null,
                    'product_id' => $serial?->product_id,
                    'serial_unit_id' => $serial?->serial_unit_id,
                    'serial_code' => $serial?->serial_code ?: $data['serial_code'],
                    'related_serial_unit_id' => $related?->serial_unit_id,
                    'related_serial_code' => $related?->serial_code ?: ($data['related_serial_code'] ?? null),
                    'quantity' => 1,
                    'requested_by' => $request->user()->id,
                    'requested_at' => now(),
                    'note' => $data['note'] ?? null,
                ]);
                $movement->update(['movement_code' => sprintf('XKBH-%s-%06d', now()->format('Y'), $movement->id)]);

                if (in_array($claim->status, ['approved', 'diagnosing', 'solution_proposed'], true)) {
                    $claim->update(['status' => 'waiting_stock']);
                }

                return $movement;
            });

            if (($data['return_to'] ?? null) === 'warranty_exchange') {
                return redirect()->route('ky-thuat.warranty-exchange.show', ['claim' => $claim->id])
                    ->with('success', 'Đã tạo phiếu kho '.$movement->movement_code.' cho đề xuất đổi hàng.');
            }

            return redirect()->route('projects-unified.maintenance.index', ['view' => 'stock'])
                ->with('success', 'Đã tạo phiếu kho '.$movement->movement_code.'.');
        } catch (ValidationException $exception) {
            $exception->errorBag = 'warrantyStock';
            throw $exception;
        }
    }

    public function updateStatus(Request $request, SolarWarrantyStockMovement $movement): RedirectResponse
    {
        abort_unless(SolarMaintenanceAccess::canHandleWarrantyStock($request->user()), 403);
        $this->assertCompany((int) $movement->company_id, $request);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SolarWarrantyStockMovement::STATUSES))],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $linked = SolarWarrantyClaim::find($movement->warranty_claim_id);
        if ($linked && \App\Support\Warranty\WarrantyFlow::isFlowType((string) $linked->claim_type)) {
            throw ValidationException::withMessages(['status' => 'Phiếu kho của đổi hàng/sửa chữa chỉ xử lý trong trang chi tiết phiếu.']);
        }
        if ($linked && in_array((string) $linked->status, ['cancelled', 'rejected', 'completed'], true)
            && (string) $data['status'] === 'completed') {
            throw ValidationException::withMessages(['status' => 'Phiếu bảo hành đã hủy/từ chối/hoàn tất — không được hoàn tất phiếu kho.']);
        }

        $old = (string) $movement->status;
        $new = (string) $data['status'];
        $allowed = [
            'pending' => ['approved', 'completed', 'cancelled'],
            'approved' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => ['pending'],
        ];
        if ($new !== $old && ! in_array($new, $allowed[$old] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'Trạng thái phiếu kho không hợp lệ.']);
        }

        DB::transaction(function () use ($movement, $old, $new, $data, $request) {
            $statusChanged = $new !== $old;
            $movement->status = $new;
            $movement->note = $data['note'] ?? $movement->note;
            if ($statusChanged && $new === 'approved') {
                $movement->approved_by = $request->user()->id;
                $movement->approved_at = now();
            }
            if ($statusChanged && $new === 'completed') {
                $movement->approved_by = $movement->approved_by ?: $request->user()->id;
                $movement->approved_at = $movement->approved_at ?: now();
                $movement->completed_by = $request->user()->id;
                $movement->completed_at = now();
            }
            $movement->save();

            if ($statusChanged && $new === 'completed') {
                $this->applyInventoryMovement($movement, $request);
                $claim = SolarWarrantyClaim::find($movement->warranty_claim_id);
                if ($claim) {
                    if ($movement->movement_type === 'warranty_out') {
                        $claim->replacement_serial_unit_id = $movement->serial_unit_id;
                        $claim->replacement_serial_code = $movement->serial_code;
                        $claim->status = 'replacing';
                        $this->syncReplacementWarranty($movement, $claim);
                    } elseif ($movement->movement_type === 'faulty_return') {
                        $claim->returned_serial_unit_id = $movement->serial_unit_id;
                        $claim->returned_serial_code = $movement->serial_code;
                        $claim->returned_at = now();
                    } elseif ($movement->movement_type === 'replacement_receive') {
                        $claim->replacement_serial_unit_id = $movement->serial_unit_id;
                        $claim->replacement_serial_code = $movement->serial_code;
                        if ($claim->status === 'replacing') {
                            $claim->status = 'waiting_stock';
                        }
                    }
                    $claim->save();
                    $this->logSerialEvent($movement, $claim);
                }
            }
        });

        return back()->with('success', 'Đã cập nhật phiếu kho '.$movement->movement_code.'.');
    }

    private function resolveSerial(string $code): object
    {
        foreach (['crm_serial_units', 'crm_serial_unit_identifiers', 'crm_serial_identifiers'] as $table) {
            if (! SchemaCache::hasTable($table)) {
                throw ValidationException::withMessages(['serial_code' => 'Kho serial chưa sẵn sàng để tra cứu.']);
            }
        }
        $query = DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', function ($join) {
                $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id');

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

        $row = $query->where('si.code', trim($code))
            ->addSelect('su.id as serial_unit_id', 'su.product_id', 'si.code as serial_code')
            ->first();
        if (! $row) {
            throw ValidationException::withMessages(['serial_code' => 'Không tìm thấy serial “'.$code.'”.']);
        }

        return $row;
    }

    private function assertCompany(int $companyId, Request $request): void
    {
        $current = EgoCompanyScope::currentId();
        if ($current > 0 && $companyId > 0 && $current !== $companyId && ! SolarMaintenanceAccess::isAdmin($request->user())) {
            abort(403, 'Dữ liệu không thuộc công ty đang làm việc.');
        }
    }

    private function assertSerialCompany(object $serial, int $companyId, Request $request): void
    {
        if ($companyId <= 0 || SolarMaintenanceAccess::isAdmin($request->user())) {
            return;
        }

        $serialCompanyId = (int) ($serial->state_company_id ?? 0);
        if ($serialCompanyId > 0 && $serialCompanyId !== $companyId) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial đã chọn đang thuộc công ty khác.',
            ]);
        }
    }

    private function assertWarehouseCompany(Warehouse $warehouse, int $companyId): void
    {
        if ($companyId <= 0) {
            return;
        }

        $directCompanyId = (int) ($warehouse->company_id ?? 0);
        $pivotCompanyIds = collect();
        if (SchemaCache::hasTable('company_warehouse')) {
            $pivotCompanyIds = DB::table('company_warehouse')
                ->where('warehouse_id', $warehouse->id)
                ->pluck('company_id')
                ->map(fn ($id) => (int) $id);
        }

        $hasExplicitOwnership = $directCompanyId > 0 || $pivotCompanyIds->isNotEmpty();
        $belongsToCompany = $directCompanyId === $companyId || $pivotCompanyIds->contains($companyId);

        if ($hasExplicitOwnership && ! $belongsToCompany) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Kho đã chọn không thuộc công ty của công trình.',
            ]);
        }
    }

    private function assertMovementSerials(SolarWarrantyClaim $claim, string $movementType, object $serial): void
    {
        $originalCode = trim((string) $claim->serial_code);
        $movementCode = trim((string) $serial->serial_code);

        if (in_array($movementType, ['faulty_return', 'supplier_send'], true)
            && $originalCode !== ''
            && strcasecmp($originalCode, $movementCode) !== 0) {
            throw ValidationException::withMessages([
                'serial_code' => 'Nghiệp vụ thu hồi/gửi nhà cung cấp phải dùng đúng serial lỗi của phiếu bảo hành.',
            ]);
        }

        if (in_array($movementType, ['warranty_out', 'replacement_receive'], true)
            && $originalCode !== ''
            && strcasecmp($originalCode, $movementCode) === 0) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial thay thế phải khác serial thiết bị lỗi.',
            ]);
        }

        if ($movementType === 'warranty_out'
            && $claim->replacement_serial_unit_id
            && (int) $claim->replacement_serial_unit_id !== (int) $serial->serial_unit_id) {
            throw ValidationException::withMessages([
                'serial_code' => 'Phiếu này đã ghi nhận một serial thay thế khác. Hãy mở lại và xử lý hồ sơ cũ trước khi đổi tiếp.',
            ]);
        }

        if ($movementType === 'faulty_return' && $claim->returned_serial_unit_id) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial lỗi của phiếu này đã được ghi nhận thu hồi.',
            ]);
        }
    }

    private function assertSerialAvailableForMovement(string $movementType, object $serial, int $warehouseId): void
    {
        if (! SchemaCache::hasTable('crm_serial_unit_states')) {
            return;
        }

        $state = DB::table('crm_serial_unit_states')
            ->where('serial_unit_id', $serial->serial_unit_id)
            ->first();

        if ($movementType === 'warranty_out'
            && (! $state || (string) $state->state !== 'in_stock' || (int) $state->warehouse_id !== $warehouseId)) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial thay thế không ở trạng thái sẵn sàng trong kho đã chọn.',
            ]);
        }

        if ($movementType === 'supplier_send'
            && (! $state || (int) $state->warehouse_id !== $warehouseId || ! in_array((string) $state->state, ['in_stock', 'returned', 'damaged'], true))) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial gửi nhà cung cấp phải đang nằm trong kho đã chọn.',
            ]);
        }

        if ($movementType === 'replacement_receive'
            && $state
            && in_array((string) $state->state, ['in_stock', 'reserved', 'sold'], true)) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial nhận thay thế đang có trạng thái sử dụng/tồn kho; không thể nhập trùng.',
            ]);
        }

        if ($movementType === 'faulty_return'
            && $state
            && in_array((string) $state->state, ['in_stock', 'reserved'], true)) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial lỗi đã nằm trong kho. Hãy dùng nghiệp vụ gửi nhà cung cấp hoặc kiểm tra phiếu trước đó.',
            ]);
        }
    }

    private function applyInventoryMovement(SolarWarrantyStockMovement $movement, Request $request): void
    {
        foreach (['crm_inventory_events', 'crm_serial_event_lines', 'crm_serial_unit_states'] as $table) {
            if (! SchemaCache::hasTable($table)) {
                throw ValidationException::withMessages([
                    'status' => 'Kho serial chưa đủ bảng dữ liệu để hoàn tất phiếu. Vui lòng liên hệ Admin.',
                ]);
            }
        }

        $current = DB::table('crm_serial_unit_states')
            ->where('serial_unit_id', $movement->serial_unit_id)
            ->lockForUpdate()
            ->first();

        $fromWarehouseId = null;
        $toWarehouseId = null;
        $nextState = 'unknown';
        $eventType = 'adjustment';

        if ($movement->movement_type === 'warranty_out') {
            if (! $current || (string) $current->state !== 'in_stock' || (int) $current->warehouse_id !== (int) $movement->warehouse_id) {
                throw ValidationException::withMessages(['status' => 'Serial thay thế không còn sẵn sàng trong kho. Vui lòng kiểm tra lại trước khi hoàn tất.']);
            }
            $fromWarehouseId = (int) $movement->warehouse_id;
            $nextState = 'sold';
            $eventType = 'warranty_out';
        } elseif ($movement->movement_type === 'faulty_return') {
            $toWarehouseId = (int) $movement->warehouse_id;
            $nextState = 'damaged';
            $eventType = 'return_in';
        } elseif ($movement->movement_type === 'supplier_send') {
            if (! $current || (int) $current->warehouse_id !== (int) $movement->warehouse_id) {
                throw ValidationException::withMessages(['status' => 'Serial lỗi không còn ở kho đã chọn để gửi nhà cung cấp.']);
            }
            $fromWarehouseId = (int) $movement->warehouse_id;
            $nextState = 'supplier_warranty';
            $eventType = 'return_out';
        } elseif ($movement->movement_type === 'replacement_receive') {
            $toWarehouseId = (int) $movement->warehouse_id;
            $nextState = 'in_stock';
            $eventType = 'return_in';
        }

        $eventId = DB::table('crm_inventory_events')->insertGetId([
            'event_type' => $eventType,
            'occurred_at' => now(),
            'created_by' => $request->user()->id,
            'note' => 'Phiếu kho bảo hành '.$movement->movement_code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('crm_serial_event_lines')->insert([
            'event_id' => $eventId,
            'serial_unit_id' => $movement->serial_unit_id,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $toWarehouseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stateValues = [
            'warehouse_id' => $toWarehouseId,
            'state' => $nextState,
            'last_event_id' => $eventId,
            'synced_at' => now(),
        ];
        if (SchemaCache::hasColumn('crm_serial_unit_states', 'company_id')) {
            $stateValues['company_id'] = $movement->company_id;
        }

        DB::table('crm_serial_unit_states')->updateOrInsert(
            ['serial_unit_id' => $movement->serial_unit_id],
            $stateValues
        );

        if (SchemaCache::hasColumn('crm_serial_units', 'warehouse_id')) {
            DB::table('crm_serial_units')->where('id', $movement->serial_unit_id)->update([
                'warehouse_id' => $toWarehouseId,
                'updated_at' => now(),
            ]);
        }
    }

    private function syncReplacementWarranty(SolarWarrantyStockMovement $movement, SolarWarrantyClaim $claim): void
    {
        if (! SchemaCache::hasTable('crm_serial_warranties') || ! $movement->serial_unit_id) {
            return;
        }

        $original = $claim->serial_unit_id
            ? DB::table('crm_serial_warranties')->where('serial_unit_id', $claim->serial_unit_id)->first()
            : null;

        if (! $original) {
            return;
        }

        $replacementData = [
            'customer_id' => $claim->customer_id ?: $original->customer_id,
            'order_id' => $claim->order_id ?: $original->order_id,
            'order_item_id' => $original->order_item_id,
            'sold_at' => now()->toDateString(),
            'warranty_months' => (int) $original->warranty_months,
            'warranty_start_at' => $original->warranty_start_at,
            'warranty_end_at' => $original->warranty_end_at,
            'status' => 'active',
            'note' => 'Serial thay thế theo phiếu '.$claim->claim_code,
            'updated_at' => now(),
        ];

        if (! DB::table('crm_serial_warranties')->where('serial_unit_id', $movement->serial_unit_id)->exists()) {
            $replacementData['created_at'] = now();
        }

        DB::table('crm_serial_warranties')->updateOrInsert(
            ['serial_unit_id' => $movement->serial_unit_id],
            $replacementData
        );

        if ($claim->serial_unit_id) {
            DB::table('crm_serial_warranties')->where('serial_unit_id', $claim->serial_unit_id)->update([
                'status' => 'replaced',
                'note' => 'Đã thay bằng serial '.$movement->serial_code.' theo phiếu '.$claim->claim_code,
                'updated_at' => now(),
            ]);
        }
    }

    private function logSerialEvent(SolarWarrantyStockMovement $movement, SolarWarrantyClaim $claim): void
    {
        if (! $movement->serial_unit_id || ! SchemaCache::hasTable('crm_serial_warranty_events')) {
            return;
        }
        $isOutbound = in_array($movement->movement_type, ['warranty_out', 'supplier_send'], true);
        DB::table('crm_serial_warranty_events')->insert([
            'serial_unit_id' => $movement->serial_unit_id,
            'serial_code' => $movement->serial_code,
            'event_type' => 'maintenance_'.$movement->movement_type,
            'from_warehouse_id' => $isOutbound ? $movement->warehouse_id : null,
            'to_warehouse_id' => $isOutbound ? null : $movement->warehouse_id,
            'customer_id' => $claim->customer_id,
            'order_id' => $claim->order_id,
            'created_by' => auth()->id(),
            'note' => 'Phiếu '.$movement->movement_code.' / '.$claim->claim_code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
