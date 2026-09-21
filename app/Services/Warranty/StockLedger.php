<?php

declare(strict_types=1);

namespace App\Services\Warranty;

use App\Support\Warranty\WarrantyException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ kho cho nghiệp vụ Bảo hành / Sửa chữa. Mọi hàm PHẢI gọi trong DB::transaction.
 *  - Serial: crm_serial_unit_states + crm_inventory_events + crm_serial_event_lines
 *  - Tồn tổng: crm_product_stock.qty (+serials_json) + crm_stock_movements
 * Khóa dòng (lockForUpdate) ở các bước tranh chấp để không trừ tồn 2 lần.
 */
final class StockLedger
{
    public function lockState(int $serialUnitId): ?object
    {
        return DB::table('crm_serial_unit_states')->where('serial_unit_id', $serialUnitId)->lockForUpdate()->first();
    }

    /** Đổi trạng thái serial KHÔNG làm thay đổi tồn (giữ hàng / nhả hàng). */
    public function setSerialState(int $serialUnitId, string $state, ?int $warehouseId, ?int $companyId, string $note, int $userId, string $eventType = 'adjustment'): void
    {
        $eventId = $this->event($eventType, $note, $userId);
        DB::table('crm_serial_event_lines')->insert([
            'event_id' => $eventId,
            'serial_unit_id' => $serialUnitId,
            'from_warehouse_id' => $warehouseId,
            'to_warehouse_id' => $warehouseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->writeState($serialUnitId, $state, $warehouseId, $companyId, $eventId);
    }

    /** Xuất serial khỏi kho: state → sold, giảm tồn tổng 1. */
    public function issueSerial(int $serialUnitId, int $productId, int $warehouseId, ?int $companyId, string $note, int $userId, int $claimId): void
    {
        $eventId = $this->event('warranty_out', $note, $userId);
        DB::table('crm_serial_event_lines')->insert([
            'event_id' => $eventId,
            'serial_unit_id' => $serialUnitId,
            'from_warehouse_id' => $warehouseId,
            'to_warehouse_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->writeState($serialUnitId, 'sold', null, $companyId, $eventId);
        $this->adjustProductStock($productId, $warehouseId, $companyId, -1, $note, $claimId, $userId, $this->serialCode($serialUnitId), 'remove', false);
    }

    /** Nhập serial vào kho (thu hồi lỗi / nhận thay thế): state → $state, tăng tồn tổng 1. */
    public function receiveSerial(int $serialUnitId, int $productId, int $warehouseId, ?int $companyId, string $state, string $note, int $userId, int $claimId): void
    {
        $eventId = $this->event('return_in', $note, $userId);
        DB::table('crm_serial_event_lines')->insert([
            'event_id' => $eventId,
            'serial_unit_id' => $serialUnitId,
            'from_warehouse_id' => null,
            'to_warehouse_id' => $warehouseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->writeState($serialUnitId, $state, $warehouseId, $companyId, $eventId);
        $this->adjustProductStock($productId, $warehouseId, $companyId, 1, $note, $claimId, $userId, $this->serialCode($serialUnitId), 'add', false);
    }

    /**
     * Điều chỉnh tồn tổng (không serial) — dùng cho linh kiện sửa chữa và cho serial.
     * Trả về [qty_before, qty_after]. Không cho âm.
     */
    public function adjustProductStock(int $productId, int $warehouseId, ?int $companyId, float $delta, string $reason, int $refId, int $userId, ?string $serialCode = null, ?string $serialOp = null, bool $strict = true): array
    {
        if (! Schema::hasTable('crm_product_stock')) {
            return [null, null];
        }

        $row = DB::table('crm_product_stock')
            ->where('product_id', $productId)->where('warehouse_id', $warehouseId)
            ->lockForUpdate()->first();

        if (! $row) {
            if ($delta < 0) {
                if (! $strict) {
                    return [null, null]; // serial: chưa có dòng tồn tổng để đồng bộ
                }
                throw new WarrantyException('Không có dòng tồn kho tổng cho sản phẩm tại kho này — không thể xuất.');
            }
            $data = ['product_id' => $productId, 'warehouse_id' => $warehouseId, 'qty' => 0];
            if (Schema::hasColumn('crm_product_stock', 'company_id')) {
                $data['company_id'] = $companyId ?: 0;
            }
            if (Schema::hasColumn('crm_product_stock', 'serials_json')) {
                $data['serials_json'] = json_encode([]);
            }
            $id = DB::table('crm_product_stock')->insertGetId($data);
            $row = DB::table('crm_product_stock')->where('id', $id)->lockForUpdate()->first();
        }

        $before = (float) $row->qty;
        $after = $before + $delta;
        if ($after < 0 && ! $strict) {
            $after = 0.0;
        }
        if ($after < 0) {
            throw new WarrantyException('Tồn kho không đủ để xuất (tồn '.rtrim(rtrim(number_format($before, 3, '.', ''), '0'), '.').').');
        }

        $update = ['qty' => (int) round($after)];
        if (Schema::hasColumn('crm_product_stock', 'last_updated')) {
            $update['last_updated'] = now();
        }
        if ($serialCode && $serialOp && Schema::hasColumn('crm_product_stock', 'serials_json')) {
            $list = json_decode((string) ($row->serials_json ?? '[]'), true);
            $list = is_array($list) ? $list : [];
            if ($serialOp === 'remove') {
                $list = array_values(array_filter($list, fn ($c) => (string) $c !== $serialCode));
            } elseif (! in_array($serialCode, $list, true)) {
                $list[] = $serialCode;
            }
            $update['serials_json'] = json_encode($list, JSON_UNESCAPED_UNICODE);
        }
        DB::table('crm_product_stock')->where('id', $row->id)->update($update);

        if (Schema::hasTable('crm_stock_movements')) {
            DB::table('crm_stock_movements')->insert([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'change_qty' => (int) round($delta),
                'qty_before' => (int) round($before),
                'qty_after' => (int) round($after),
                'reason' => 'Bảo hành/Sửa chữa #'.$refId.': '.$reason,
                'reference_id' => $refId,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$before, $after];
    }

    /** Tồn khả dụng của sản phẩm tại kho = tồn tổng − số đang giữ cho linh kiện sửa chữa. */
    public function availablePartQty(int $productId, int $warehouseId): float
    {
        $qty = (float) (DB::table('crm_product_stock')->where('product_id', $productId)->where('warehouse_id', $warehouseId)->value('qty') ?? 0);
        $held = Schema::hasTable('warranty_repair_parts')
            ? (float) DB::table('warranty_repair_parts')->where('product_id', $productId)->where('warehouse_id', $warehouseId)
                ->where('status', 'reserved')->sum('qty_reserved')
            : 0.0;

        return $qty - $held;
    }

    private function event(string $type, string $note, int $userId): int
    {
        return (int) DB::table('crm_inventory_events')->insertGetId([
            'event_type' => $type,
            'occurred_at' => now(),
            'created_by' => $userId,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function writeState(int $serialUnitId, string $state, ?int $warehouseId, ?int $companyId, int $eventId): void
    {
        $values = [
            'warehouse_id' => $warehouseId,
            'state' => $state,
            'last_event_id' => $eventId,
            'synced_at' => now(),
        ];
        if (Schema::hasColumn('crm_serial_unit_states', 'company_id') && $companyId) {
            $values['company_id'] = $companyId;
        }
        DB::table('crm_serial_unit_states')->updateOrInsert(['serial_unit_id' => $serialUnitId], $values);

        if (Schema::hasColumn('crm_serial_units', 'warehouse_id')) {
            DB::table('crm_serial_units')->where('id', $serialUnitId)->update(['warehouse_id' => $warehouseId, 'updated_at' => now()]);
        }
    }

    private function serialCode(int $serialUnitId): ?string
    {
        return DB::table('crm_serial_unit_identifiers as sui')
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('sui.serial_unit_id', $serialUnitId)->where('sui.is_primary', 1)->value('si.code');
    }
}
