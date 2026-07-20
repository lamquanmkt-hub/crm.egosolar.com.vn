<?php

declare(strict_types=1);

namespace App\Services\Order;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Serial theo đơn hàng: liệt kê serial khả dụng, xác thực lựa chọn
 * và kích hoạt bảo hành khi xuất kho.
 *
 * Lưu ý: payload ở đây làm việc theo serial_unit_id (khác với
 * OrderInventoryHandler::getShipSerialsPayload làm việc theo serial code
 * và chỉ xét sản phẩm is_serialized) — hai ngữ nghĩa này đang được
 * dùng ở hai chỗ khác nhau trên production, KHÔNG gộp làm một.
 *
 * Tách từ OrderController (P1a refactor) — hành vi giữ nguyên,
 * được chốt bằng WarehouseIssueCharacterizationTest.
 */
class OrderSerialWarrantyService
{
    /**
     * Gom payload serial còn trong kho (in_stock) theo từng order item của đơn hàng.
     *
     * Sản phẩm không serial hoá nhưng có serial khả dụng vẫn được liệt kê;
     * sản phẩm không serial hoá và không có serial thì bỏ qua.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSerialPayload(int $orderId): array
    {
        if (! Schema::hasTable('crm_order_items') || ! Schema::hasTable('crm_serial_units')) {
            return [];
        }

        $order = DB::table('crm_orders')->where('id', $orderId)->first();

        $items = DB::table('crm_order_items as oi')
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'oi.product_id')
            ->where('oi.order_id', $orderId)
            ->select(
                'oi.id as order_item_id',
                'oi.product_id',
                'oi.product_name',
                'oi.quantity',
                'oi.warehouse_id',
                'p.name as catalog_name',
                'p.sku',
                'p.is_serialized'
            )
            ->get();

        $payload = [];

        foreach ($items as $item) {
            $warehouseId = (int) ($item->warehouse_id ?: ($order->warehouse_id ?? 0));

            $serialQ = DB::table('crm_serial_units as su')
                ->join('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'su.id')
                ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
                ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
                ->leftJoin('crm_warehouses as w', 'w.id', '=', 'st.warehouse_id')
                ->where('su.product_id', (int) $item->product_id)
                ->where('sui.is_primary', 1)
                ->where('st.state', 'in_stock');

            if ($warehouseId > 0) {
                $serialQ->where('st.warehouse_id', $warehouseId);
            }

            $available = $serialQ
                ->select('su.id', 'si.code', 'st.warehouse_id', 'w.name as warehouse_name')
                ->orderBy('si.code')
                ->get();

            if ((int) ($item->is_serialized ?? 0) !== 1 && $available->isEmpty()) {
                continue;
            }

            $payload[] = [
                'order_item_id' => (int) $item->order_item_id,
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name ?: ($item->catalog_name ?: ('Sản phẩm #'.$item->product_id)),
                'sku' => $item->sku,
                'quantity' => (int) $item->quantity,
                'warehouse_id' => $warehouseId,
                'available_serials' => $available->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'code' => $r->code,
                    'warehouse_id' => (int) $r->warehouse_id,
                    'warehouse_name' => $r->warehouse_name,
                ])->values()->all(),
            ];
        }

        return $payload;
    }

    /**
     * Kiểm tra serial đã chọn cho từng dòng hàng: đủ số lượng và còn khả dụng, sai thì ném exception.
     *
     * @param  array<int|string, mixed>  $serials  Map order_item_id => danh sách serial_unit_id
     *
     * @throws \Exception Khi chọn sai số lượng hoặc serial không còn khả dụng
     */
    public function validateSerialSelection(int $orderId, array $serials): void
    {
        $items = $this->getSerialPayload($orderId);

        foreach ($items as $item) {
            $selected = array_values(array_filter((array) ($serials[$item['order_item_id']] ?? [])));
            $need = (int) $item['quantity'];

            if (count($selected) !== $need) {
                throw new \Exception('Sản phẩm "'.$item['product_name'].'" cần chọn đúng '.$need.' serial. Hiện đang chọn '.count($selected).'.');
            }

            $availableIds = collect($item['available_serials'])->pluck('id')->map(fn ($id) => (int) $id)->all();

            foreach ($selected as $sid) {
                if (! in_array((int) $sid, $availableIds, true)) {
                    throw new \Exception('Serial đã chọn không còn trong kho hoặc không đúng sản phẩm: #'.$sid);
                }
            }
        }
    }

    /**
     * Chuyển serial sang trạng thái đã bán, gắn vào order item và kích hoạt bảo hành theo số tháng.
     *
     * @param  array<int|string, mixed>  $serials  Map order_item_id => danh sách serial_unit_id
     * @param  string  $shipDate  Ngày xuất kho (Y-m-d) — mốc bắt đầu bảo hành
     * @param  int  $months  Số tháng bảo hành
     * @param  string|null  $note  Ghi chú kèm theo sự kiện bảo hành
     */
    public function activateWarrantyForOrder(int $orderId, array $serials, string $shipDate, int $months, ?string $note = null): void
    {
        if (! Schema::hasTable('crm_serial_warranties')) {
            return;
        }

        $order = DB::table('crm_orders')->where('id', $orderId)->first();
        $customerId = $this->customerIdFromOrder($order);
        $start = Carbon::parse($shipDate)->startOfDay();

        foreach ($serials as $orderItemId => $unitIds) {
            foreach ((array) $unitIds as $unitId) {
                $unitId = (int) $unitId;

                if ($unitId <= 0) {
                    continue;
                }

                $this->sellSerialUnit($unitId, (int) $orderItemId, $orderId, $customerId, $start, $months, $note);
            }
        }
    }

    /**
     * Xử lý trọn vẹn 1 serial unit khi bán: đổi trạng thái, gắn order item,
     * tạo/ghi đè bảo hành và ghi sự kiện bảo hành.
     */
    private function sellSerialUnit(
        int $unitId,
        int $orderItemId,
        int $orderId,
        int $customerId,
        Carbon $start,
        int $months,
        ?string $note
    ): void {
        $code = DB::table('crm_serial_unit_identifiers as sui')
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('sui.serial_unit_id', $unitId)
            ->where('sui.is_primary', 1)
            ->value('si.code');

        $oldState = DB::table('crm_serial_unit_states')->where('serial_unit_id', $unitId)->first();

        DB::table('crm_serial_unit_states')->updateOrInsert(
            ['serial_unit_id' => $unitId],
            [
                'warehouse_id' => null,
                'state' => 'sold',
                'synced_at' => now(),
            ]
        );

        if (Schema::hasColumn('crm_serial_units', 'warehouse_id')) {
            DB::table('crm_serial_units')->where('id', $unitId)->update([
                'warehouse_id' => null,
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('crm_order_item_serial_units')) {
            DB::table('crm_order_item_serial_units')->updateOrInsert(
                ['serial_unit_id' => $unitId],
                [
                    'order_item_id' => $orderItemId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('crm_serial_warranties')->updateOrInsert(
            ['serial_unit_id' => $unitId],
            [
                'customer_id' => $customerId ?: null,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'sold_at' => $start->toDateString(),
                'warranty_months' => $months,
                'warranty_start_at' => $start->toDateString(),
                'warranty_end_at' => $start->copy()->addMonths($months)->toDateString(),
                'status' => 'active',
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (Schema::hasTable('crm_serial_warranty_events')) {
            DB::table('crm_serial_warranty_events')->insert([
                'serial_unit_id' => $unitId,
                'serial_code' => $code,
                'event_type' => 'issue',
                'from_state' => $oldState->state ?? 'in_stock',
                'to_state' => 'sold',
                'from_warehouse_id' => $oldState->warehouse_id ?? null,
                'to_warehouse_id' => null,
                'customer_id' => $customerId ?: null,
                'order_id' => $orderId,
                'created_by' => Auth::id(),
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Lấy customer_id từ đơn hàng (trực tiếp hoặc tra qua lead); trả về 0 nếu không xác định được.
     */
    private function customerIdFromOrder(?object $order): int
    {
        if (! $order) {
            return 0;
        }

        if (isset($order->customer_id) && (int) $order->customer_id > 0) {
            return (int) $order->customer_id;
        }

        if (! empty($order->lead_id) && Schema::hasTable('crm_leads')) {
            return (int) DB::table('crm_leads')->where('id', (int) $order->lead_id)->value('customer_id');
        }

        return 0;
    }
}
