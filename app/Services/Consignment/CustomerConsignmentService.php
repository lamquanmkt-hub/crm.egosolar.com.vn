<?php

declare(strict_types=1);

namespace App\Services\Consignment;

use App\Models\CRM\Consignments\CustomerConsignment;
use App\Models\CRM\Consignments\CustomerConsignmentActivity;
use App\Models\CRM\Consignments\CustomerConsignmentItem;
use App\Models\CRM\Consignments\CustomerConsignmentSerial;
use App\Models\Inventory\Catalog\Product;
use App\Services\Inventory\Stock\StockLotService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class CustomerConsignmentService
{
    public function __construct(private readonly StockLotService $stockLotService)
    {
    }

    public function createDirect(array $data, bool $submit): CustomerConsignment
    {
        return DB::transaction(function () use ($data, $submit) {
            $items = $this->normalizeItems((array) $data['items'], (int) $data['company_id']);

            $status = $submit
                ? CustomerConsignment::STATUS_PENDING_APPROVAL
                : CustomerConsignment::STATUS_DRAFT;

            $consignment = CustomerConsignment::create([
                'code' => $this->nextCode('KGH'),
                'order_id' => null,
                'company_id' => (int) $data['company_id'],
                'customer_id' => (int) $data['customer_id'],
                'sales_user_id' => Auth::id(),
                'status' => $status,
                'expires_at' => $data['expires_at'] ?? null,
                'terms_note' => $data['terms_note'] ?? null,
                'receiver_name' => $data['receiver_name'] ?? null,
                'receiver_phone' => $data['receiver_phone'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'created_by' => Auth::id(),
                'submitted_by' => $submit ? Auth::id() : null,
                'submitted_at' => $submit ? now() : null,
            ]);

            $this->replaceItems($consignment, $items);
            $this->activity($consignment, 'created', null, $status, [
                'customer_id' => $consignment->customer_id,
                'total_quantity' => collect($items)->sum('quantity'),
                'submitted' => $submit,
            ]);

            return $consignment->fresh(['customer', 'items.product', 'items.sourceWarehouse']);
        });
    }

    public function updateDraft(CustomerConsignment $consignment, array $data, bool $submit): CustomerConsignment
    {
        return DB::transaction(function () use ($consignment, $data, $submit) {
            $consignment = CustomerConsignment::query()->lockForUpdate()->findOrFail($consignment->id);
            if (! in_array($consignment->status, [
                CustomerConsignment::STATUS_DRAFT,
                CustomerConsignment::STATUS_REVISION_REQUESTED,
            ], true)) {
                throw new RuntimeException('Chỉ đơn Nháp hoặc Yêu cầu chỉnh sửa mới được cập nhật.');
            }

            $items = $this->normalizeItems((array) $data['items'], (int) $data['company_id']);
            $from = $consignment->status;
            $status = $submit
                ? CustomerConsignment::STATUS_PENDING_APPROVAL
                : CustomerConsignment::STATUS_DRAFT;

            $consignment->update([
                'customer_id' => (int) $data['customer_id'],
                'company_id' => (int) $data['company_id'],
                'sales_user_id' => $consignment->sales_user_id ?: Auth::id(),
                'status' => $status,
                'expires_at' => $data['expires_at'] ?? null,
                'terms_note' => $data['terms_note'] ?? null,
                'receiver_name' => $data['receiver_name'] ?? null,
                'receiver_phone' => $data['receiver_phone'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'submitted_by' => $submit ? Auth::id() : null,
                'submitted_at' => $submit ? now() : null,
                'revision_reason' => null,
                'revision_requested_by' => null,
                'revision_requested_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'reject_reason' => null,
            ]);

            $this->replaceItems($consignment, $items);
            $this->activity($consignment, $submit ? 'resubmitted' : 'updated', $from, $status, [
                'total_quantity' => collect($items)->sum('quantity'),
            ]);

            return $consignment->fresh(['customer', 'items.product', 'items.sourceWarehouse']);
        });
    }

    public function submit(CustomerConsignment $consignment): void
    {
        DB::transaction(function () use ($consignment) {
            $consignment = CustomerConsignment::query()->with('items')->lockForUpdate()->findOrFail($consignment->id);
            if (! in_array($consignment->status, [CustomerConsignment::STATUS_DRAFT, CustomerConsignment::STATUS_REVISION_REQUESTED], true)) {
                throw new RuntimeException('Đơn không ở trạng thái có thể gửi duyệt.');
            }
            if ($consignment->items->isEmpty()) {
                throw new RuntimeException('Đơn ký gửi chưa có hàng hóa.');
            }
            $from = $consignment->status;
            $consignment->update([
                'status' => CustomerConsignment::STATUS_PENDING_APPROVAL,
                'submitted_by' => Auth::id(),
                'submitted_at' => now(),
                'revision_reason' => null,
            ]);
            $this->activity($consignment, 'submitted', $from, $consignment->status);
        });
    }

    public function approve(CustomerConsignment $consignment, ?string $note = null): void
    {
        DB::transaction(function () use ($consignment, $note) {
            $consignment = CustomerConsignment::query()->lockForUpdate()->findOrFail($consignment->id);
            if ($consignment->status !== CustomerConsignment::STATUS_PENDING_APPROVAL) {
                throw new RuntimeException('Chỉ đơn đang Chờ duyệt mới được phê duyệt.');
            }
            $from = $consignment->status;
            $consignment->update([
                'status' => CustomerConsignment::STATUS_APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_note' => $note,
                'rejected_by' => null,
                'rejected_at' => null,
                'reject_reason' => null,
            ]);
            $this->activity($consignment, 'approved', $from, $consignment->status, ['note' => $note]);
        });
    }

    public function requestRevision(CustomerConsignment $consignment, string $reason): void
    {
        DB::transaction(function () use ($consignment, $reason) {
            $consignment = CustomerConsignment::query()->lockForUpdate()->findOrFail($consignment->id);
            if ($consignment->status !== CustomerConsignment::STATUS_PENDING_APPROVAL) {
                throw new RuntimeException('Chỉ đơn đang Chờ duyệt mới được yêu cầu chỉnh sửa.');
            }
            $from = $consignment->status;
            $consignment->update([
                'status' => CustomerConsignment::STATUS_REVISION_REQUESTED,
                'revision_requested_by' => Auth::id(),
                'revision_requested_at' => now(),
                'revision_reason' => $reason,
            ]);
            $this->activity($consignment, 'revision_requested', $from, $consignment->status, ['reason' => $reason]);
        });
    }

    public function reject(CustomerConsignment $consignment, string $reason): void
    {
        DB::transaction(function () use ($consignment, $reason) {
            $consignment = CustomerConsignment::query()->lockForUpdate()->findOrFail($consignment->id);
            if ($consignment->status !== CustomerConsignment::STATUS_PENDING_APPROVAL) {
                throw new RuntimeException('Chỉ đơn đang Chờ duyệt mới được từ chối.');
            }
            $from = $consignment->status;
            $consignment->update([
                'status' => CustomerConsignment::STATUS_REJECTED,
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
                'reject_reason' => $reason,
            ]);
            $this->activity($consignment, 'rejected', $from, $consignment->status, ['reason' => $reason]);
        });
    }

    public function availableSerials(CustomerConsignment $consignment): array
    {
        $consignment->loadMissing(['items.product', 'items.sourceWarehouse']);
        $result = [];
        foreach ($consignment->items as $item) {
            if (! (bool) ($item->product->is_serialized ?? false)) {
                continue;
            }
            $result[(int) $item->id] = DB::table('crm_serial_units as unit')
                ->join('crm_serial_unit_states as state', 'state.serial_unit_id', '=', 'unit.id')
                ->join('crm_serial_unit_identifiers as link', function ($join) {
                    $join->on('link.serial_unit_id', '=', 'unit.id')->where('link.is_primary', 1);
                })
                ->join('crm_serial_identifiers as identifier', 'identifier.id', '=', 'link.serial_identifier_id')
                ->where('unit.product_id', $item->product_id)
                ->where('state.warehouse_id', $item->source_warehouse_id)
                ->where('state.state', 'in_stock')
                ->orderBy('identifier.code')
                ->select('unit.id', 'identifier.code')
                ->get()
                ->map(fn ($row) => ['id' => (int) $row->id, 'code' => (string) $row->code])
                ->all();
        }
        return $result;
    }

    public function issueFromWarehouse(CustomerConsignment $consignment, array $serialsByItem, string $issueDate, ?string $note): void
    {
        DB::transaction(function () use ($consignment, $serialsByItem, $issueDate, $note) {
            $consignment = CustomerConsignment::query()
                ->with(['items.product', 'customer'])
                ->lockForUpdate()
                ->findOrFail($consignment->id);

            if ($consignment->status !== CustomerConsignment::STATUS_APPROVED) {
                throw new RuntimeException('Kho chỉ được xuất khi Admin/Giám đốc đã phê duyệt đơn ký gửi.');
            }

            $targetWarehouseId = $this->ensureConsignmentWarehouse((int) $consignment->company_id);
            $this->validateAndMoveSerials($consignment, $serialsByItem, $targetWarehouseId);

            foreach ($consignment->items as $item) {
                $this->assertStockAvailable((int) $item->product_id, (int) $consignment->company_id, (int) $item->source_warehouse_id, (int) $item->quantity);
                $product = $item->product ?: Product::findOrFail($item->product_id);
                $allocations = $this->stockLotService->issueLots(
                    (int) $item->product_id,
                    (int) $consignment->company_id,
                    (int) $item->source_warehouse_id,
                    (int) $item->quantity,
                    [
                        'reference_type' => 'customer_consignment',
                        'reference_id' => $consignment->id,
                        'reason' => 'Xuất hàng ký gửi '.$consignment->code,
                        'note' => 'Khách: '.($consignment->customer->name ?? ('#'.$consignment->customer_id)),
                    ]
                );

                if ($allocations === []) {
                    $this->stockLotService->receiveLot(
                        $product, (int) $consignment->company_id, $targetWarehouseId,
                        (int) $item->quantity, 0, (float) ($product->vat_percent ?? 0), 0,
                        [
                            'source_type' => 'customer_consignment',
                            'source_id' => $consignment->id,
                            'reference_type' => 'customer_consignment',
                            'reason' => 'Theo dõi hàng đang ký gửi '.$consignment->code,
                            'note' => 'Hàng đã xuất khỏi kho và đang ở khách hàng',
                        ]
                    );
                } else {
                    foreach ($allocations as $allocation) {
                        $vat = (float) ($product->vat_percent ?? 0);
                        $this->stockLotService->receiveLot(
                            $product, (int) $consignment->company_id, $targetWarehouseId,
                            (int) $allocation['qty'], (float) ($allocation['unit_cost_before_vat'] ?? 0),
                            $vat, $this->allocationExtraCost($allocation, $vat),
                            [
                                'lot_code' => 'KGH-'.$consignment->id.'-'.$item->id.'-'.($allocation['stock_lot_id'] ?? Str::random(5)),
                                'lot_name' => 'Đang ký gửi '.$consignment->code,
                                'source_type' => 'customer_consignment',
                                'source_id' => $consignment->id,
                                'reference_type' => 'customer_consignment',
                                'reason' => 'Theo dõi hàng đang ký gửi '.$consignment->code,
                                'note' => 'Hàng đã xuất khỏi kho và đang ở khách hàng',
                            ]
                        );
                    }
                }

                $item->update([
                    'issued_quantity' => (int) $item->quantity,
                    'released_quantity' => (int) $item->quantity,
                ]);
            }

            $from = $consignment->status;
            $consignment->update([
                'status' => CustomerConsignment::STATUS_ACTIVE,
                'consignment_warehouse_id' => $targetWarehouseId,
                'consigned_at' => $issueDate,
                'warehouse_issue_date' => $issueDate,
                'warehouse_issue_note' => $note,
                'warehouse_confirmed_by' => Auth::id(),
                'warehouse_confirmed_at' => now(),
            ]);
            $this->activity($consignment, 'warehouse_issued', $from, $consignment->status, [
                'issue_date' => $issueDate,
                'note' => $note,
                'total_quantity' => $consignment->items->sum('quantity'),
            ]);
        });
    }

    public function cancel(CustomerConsignment $consignment, string $reason): void
    {
        DB::transaction(function () use ($consignment, $reason) {
            $consignment = CustomerConsignment::query()->lockForUpdate()->findOrFail($consignment->id);
            if (in_array($consignment->status, [CustomerConsignment::STATUS_ACTIVE, CustomerConsignment::STATUS_COMPLETED], true)) {
                throw new RuntimeException('Hàng đã được Kho xuất cho khách. Không thể hủy trực tiếp; cần làm nghiệp vụ thu hồi hàng ký gửi.');
            }
            if ($consignment->status === CustomerConsignment::STATUS_CANCELLED) {
                throw new RuntimeException('Đơn đã hủy trước đó.');
            }
            $from = $consignment->status;
            $consignment->update([
                'status' => CustomerConsignment::STATUS_CANCELLED,
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
            $this->activity($consignment, 'cancelled', $from, $consignment->status, ['reason' => $reason]);
        });
    }

    private function normalizeItems(array $rows, int $companyId): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $warehouseId = (int) ($row['source_warehouse_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);
            if ($productId <= 0 || $warehouseId <= 0 || $quantity <= 0) {
                continue;
            }
            $key = $productId.'-'.$warehouseId;
            if (isset($normalized[$key])) {
                throw new RuntimeException('Sản phẩm và kho nguồn bị chọn trùng ở nhiều dòng.');
            }
            $product = Product::query()->where('is_active', 1)->find($productId);
            if (! $product) {
                throw new RuntimeException('Sản phẩm dòng '.($index + 1).' không tồn tại hoặc đã ngừng sử dụng.');
            }
            $warehouseOk = DB::table('crm_warehouses')
                ->where('id', $warehouseId)
                ->where('company_id', $companyId)
                ->when(Schema::hasColumn('crm_warehouses', 'warehouse_type'), fn ($q) => $q->where('warehouse_type', '!=', 'customer_consignment'))
                ->exists();
            if (! $warehouseOk) {
                throw new RuntimeException('Kho nguồn dòng '.($index + 1).' không hợp lệ.');
            }
            $this->assertStockAvailable($productId, $companyId, $warehouseId, $quantity);
            $normalized[$key] = [
                'product_id' => $productId,
                'source_warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'unit_price' => max(0, (float) ($row['unit_price'] ?? ($product->price_agent ?? $product->price_retail ?? $product->price ?? 0))),
                'item_note' => trim((string) ($row['item_note'] ?? '')) ?: null,
            ];
        }
        if ($normalized === []) {
            throw new RuntimeException('Sales cần chọn ít nhất một sản phẩm và nhập số lượng ký gửi lớn hơn 0.');
        }
        return array_values($normalized);
    }

    private function replaceItems(CustomerConsignment $consignment, array $items): void
    {
        CustomerConsignmentItem::query()->where('consignment_id', $consignment->id)->delete();
        foreach ($items as $item) {
            CustomerConsignmentItem::create([
                'consignment_id' => $consignment->id,
                'order_item_id' => null,
                'product_id' => $item['product_id'],
                'source_warehouse_id' => $item['source_warehouse_id'],
                'quantity' => $item['quantity'],
                'issued_quantity' => 0,
                'returned_quantity' => 0,
                'sold_quantity' => 0,
                'released_quantity' => 0,
                'unit_price' => $item['unit_price'],
                'item_note' => $item['item_note'],
            ]);
        }
    }

    private function assertStockAvailable(int $productId, int $companyId, int $warehouseId, int $quantity): void
    {
        $available = (int) DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->when(Schema::hasColumn('crm_product_stock', 'company_id'), fn ($q) => $q->where('company_id', $companyId))
            ->value('qty');
        if ($available < $quantity) {
            $name = DB::table('crm_product_catalog')->where('id', $productId)->value('name') ?: ('Sản phẩm #'.$productId);
            throw new RuntimeException('Tồn kho "'.$name.'" chỉ còn '.$available.', không đủ số lượng Sales chọn là '.$quantity.'.');
        }
    }

    private function validateAndMoveSerials(CustomerConsignment $consignment, array $serialsByItem, int $targetWarehouseId): void
    {
        foreach ($consignment->items as $item) {
            if (! (bool) ($item->product->is_serialized ?? false)) {
                continue;
            }
            $picked = array_values(array_unique(array_filter(array_map('intval', (array) ($serialsByItem[$item->id] ?? [])))));
            if (count($picked) !== (int) $item->quantity) {
                throw new RuntimeException('Sản phẩm "'.($item->product->name ?? ('#'.$item->product_id)).'" cần chọn đúng '.$item->quantity.' serial.');
            }
            $valid = DB::table('crm_serial_units as unit')
                ->join('crm_serial_unit_states as state', 'state.serial_unit_id', '=', 'unit.id')
                ->where('unit.product_id', $item->product_id)
                ->where('state.warehouse_id', $item->source_warehouse_id)
                ->where('state.state', 'in_stock')
                ->whereIn('unit.id', $picked)
                ->pluck('unit.id')->map(fn ($id) => (int) $id)->all();
            if (count($valid) !== count($picked)) {
                throw new RuntimeException('Có serial không còn trong kho nguồn hoặc không đúng sản phẩm.');
            }
            foreach ($valid as $serialUnitId) {
                $this->moveSerial($serialUnitId, (int) $item->source_warehouse_id, $targetWarehouseId, 'reserved', 'Xuất ký gửi '.$consignment->code);
                CustomerConsignmentSerial::query()->updateOrCreate(
                    ['serial_unit_id' => $serialUnitId],
                    ['consignment_item_id' => $item->id, 'status' => 'reserved']
                );
            }
        }
    }

    private function ensureConsignmentWarehouse(int $companyId): int
    {
        if ($companyId <= 0) {
            throw new RuntimeException('Không xác định được công ty.');
        }
        $query = DB::table('crm_warehouses')->where('company_id', $companyId);
        if (Schema::hasColumn('crm_warehouses', 'warehouse_type')) {
            $query->where('warehouse_type', 'customer_consignment');
        } else {
            $query->where('name', 'like', '[KÝ GỬI]%');
        }
        if ($id = (int) $query->value('id')) {
            return $id;
        }
        $payload = [
            'company_id' => $companyId,
            'name' => '[KÝ GỬI] Hàng đang ở khách hàng',
            'location' => 'Kho theo dõi ảo: hàng đã xuất ký gửi và đang do khách hàng giữ',
        ];
        if (Schema::hasColumn('crm_warehouses', 'warehouse_type')) $payload['warehouse_type'] = 'customer_consignment';
        if (Schema::hasColumn('crm_warehouses', 'is_sales_selectable')) $payload['is_sales_selectable'] = false;
        if (Schema::hasColumn('crm_warehouses', 'is_active')) $payload['is_active'] = true;
        $id = (int) DB::table('crm_warehouses')->insertGetId($payload);
        if (Schema::hasTable('company_warehouse')) {
            DB::table('company_warehouse')->updateOrInsert(
                ['company_id' => $companyId, 'warehouse_id' => $id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
        return $id;
    }

    private function moveSerial(int $serialUnitId, ?int $fromWarehouseId, ?int $toWarehouseId, string $state, string $note): void
    {
        $eventId = null;
        if (Schema::hasTable('crm_inventory_events')) {
            $eventId = DB::table('crm_inventory_events')->insertGetId([
                'event_type' => 'transfer', 'occurred_at' => now(), 'created_by' => Auth::id(),
                'note' => $note, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if ($eventId && Schema::hasTable('crm_serial_event_lines')) {
            DB::table('crm_serial_event_lines')->insert([
                'event_id' => $eventId, 'serial_unit_id' => $serialUnitId,
                'from_warehouse_id' => $fromWarehouseId, 'to_warehouse_id' => $toWarehouseId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $payload = ['warehouse_id' => $toWarehouseId, 'state' => $state, 'synced_at' => now()];
        if (Schema::hasColumn('crm_serial_unit_states', 'last_event_id')) $payload['last_event_id'] = $eventId;
        DB::table('crm_serial_unit_states')->updateOrInsert(['serial_unit_id' => $serialUnitId], $payload);
        if (Schema::hasColumn('crm_serial_units', 'warehouse_id')) {
            DB::table('crm_serial_units')->where('id', $serialUnitId)->update(['warehouse_id' => $toWarehouseId, 'updated_at' => now()]);
        }
    }

    private function allocationExtraCost(array $allocation, float $vatPercent): float
    {
        $qty = max(0, (int) ($allocation['qty'] ?? 0));
        $beforeVat = (float) ($allocation['unit_cost_before_vat'] ?? 0);
        $actualAfterVat = (float) ($allocation['unit_cost_after_vat'] ?? 0);
        $calculatedAfterVat = round($beforeVat * (1 + max(0, $vatPercent) / 100), 2);
        return round(max(0, $actualAfterVat - $calculatedAfterVat) * $qty, 2);
    }

    private function nextCode(string $prefix): string
    {
        $year = now()->format('Y');
        $like = $prefix.'-'.$year.'-%';
        $last = DB::table('customer_consignments')->where('code', 'like', $like)->lockForUpdate()->orderByDesc('id')->value('code');
        $number = $last ? ((int) substr((string) $last, -5) + 1) : 1;
        return $prefix.'-'.$year.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    private function activity(CustomerConsignment $consignment, string $action, ?string $from, ?string $to, array $payload = []): void
    {
        CustomerConsignmentActivity::create([
            'consignment_id' => $consignment->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'payload' => $payload ?: null,
            'user_id' => Auth::id(),
        ]);
    }
}
