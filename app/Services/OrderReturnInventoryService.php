<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CRM\Orders\OrderReturn;
use App\Models\Inventory\Catalog\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Service xử lý kho cho hàng đổi/trả: nhập hoàn, tạo sự kiện tồn kho, cập nhật serial.
 */
class OrderReturnInventoryService
{
    /**
     * Khởi tạo service với service quản lý lô tồn kho.
     */
    public function __construct(private readonly StockLotService $stockLotService) {}

    /**
     * Nhập kho hàng hoàn: chỉ hàng đạt điều kiện bán lại được cộng tồn, cập nhật serial.
     */
    public function stockIn(OrderReturn $return, User $user): OrderReturn
    {
        return DB::transaction(function () use ($return, $user) {
            $return = OrderReturn::query()
                ->with(['order', 'items.serials'])
                ->lockForUpdate()
                ->findOrFail($return->id);

            if ($return->inventory_posted_at || $return->inventory_status === 'posted') {
                throw ValidationException::withMessages(['inventory' => 'Phiếu này đã xử lý kho trước đó.']);
            }
            if ($return->status !== 'inspected') {
                throw ValidationException::withMessages(['status' => 'Phiếu phải được kho kiểm tra trước khi nhập hoàn.']);
            }
            if (!$return->receiving_warehouse_id) {
                throw ValidationException::withMessages(['receiving_warehouse_id' => 'Chưa chọn kho nhận hàng hoàn.']);
            }

            $companyId = (int) ($return->company_id ?: $return->order?->company_id ?: 0);
            if ($companyId <= 0) {
                $companyId = (int) (DB::table('company_warehouse')
                    ->where('warehouse_id', $return->receiving_warehouse_id)
                    ->value('company_id') ?? 0);
            }
            if ($companyId <= 0) {
                throw ValidationException::withMessages(['company_id' => 'Không xác định được công ty để nhập kho.']);
            }

            $stockRef = $return->stock_in_reference ?: ('RTN-STOCK-' . $return->id);
            $eventId = $this->createInventoryEvent($return, $user);

            foreach ($return->items as $item) {
                $qtyToPost = max(0, (int) $item->accepted_quantity - (int) $item->stock_posted_quantity);
                if ($qtyToPost <= 0) continue;

                $condition = (string) ($item->condition ?: 'sellable');
                if ($condition === 'sellable') {
                    $product = Product::findOrFail($item->product_id);
                    [$costBeforeVat, $costAfterVat] = $this->resolveOriginalCost($item->order_item_id);
                    $vatPercent = $costBeforeVat > 0 ? max(0, (($costAfterVat / $costBeforeVat) - 1) * 100) : 0;

                    $existingLot = DB::table('crm_product_stock_lots')
                        ->where('source_type', 'sales_return')
                        ->where('source_id', $return->id)
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $return->receiving_warehouse_id)
                        ->first();

                    if (!$existingLot) {
                        $this->stockLotService->receiveLot(
                            $product,
                            $companyId,
                            (int) $return->receiving_warehouse_id,
                            $qtyToPost,
                            $costBeforeVat,
                            $vatPercent,
                            0,
                            [
                                'lot_code' => $return->return_code . '-I' . $item->id,
                                'lot_name' => 'Hàng hoàn ' . $return->return_code,
                                'source_type' => 'sales_return',
                                'source_id' => $return->id,
                                'reference_type' => 'order_return',
                                'reason' => 'Nhập hoàn từ ' . $return->return_code,
                                'note' => 'Hàng đạt điều kiện bán lại; không sửa ngược lô xuất cũ.',
                            ]
                        );
                    }
                }

                $this->postSerials($return, $item, $condition, $eventId, $companyId, $user);
                $item->update(['stock_posted_quantity' => (int) $item->accepted_quantity]);
            }

            $return->update([
                'inventory_status' => 'posted',
                'inventory_posted_at' => now(),
                'stock_in_reference' => $stockRef,
                'status' => 'stocked_in',
            ]);

            app(OrderReturnService::class)->history(
                $return, 'inspected', 'stocked_in', 'stock_in',
                'Kho đã xử lý hàng hoàn. Chỉ hàng sellable được cộng tồn bán được.', $user
            );

            return $return->fresh(['items.serials']);
        });
    }

    /**
     * Tính giá vốn gốc bình quân (trước/sau VAT) của dòng đơn từ phân bổ lô.
     */
    private function resolveOriginalCost(int $orderItemId): array
    {
        if (!Schema::hasTable('crm_order_item_stock_allocations')) return [0.0, 0.0];
        $rows = DB::table('crm_order_item_stock_allocations')->where('order_item_id', $orderItemId)->get();
        $qty = max(1, (int) $rows->sum('qty'));
        return [
            (float) $rows->sum(fn ($r) => (float) $r->unit_cost_before_vat * (int) $r->qty) / $qty,
            (float) $rows->sum(fn ($r) => (float) $r->unit_cost_after_vat * (int) $r->qty) / $qty,
        ];
    }

    /**
     * Tạo sự kiện tồn kho loại return_in kèm tham chiếu phiếu trả.
     */
    private function createInventoryEvent(OrderReturn $return, User $user): ?int
    {
        if (!Schema::hasTable('crm_inventory_events')) return null;
        $eventId = (int) DB::table('crm_inventory_events')->insertGetId([
            'event_type' => 'return_in',
            'occurred_at' => now(),
            'created_by' => $user->id,
            'note' => 'Nhận hàng hoàn ' . $return->return_code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (Schema::hasTable('crm_inventory_event_refs')) {
            DB::table('crm_inventory_event_refs')->insertOrIgnore([
                'event_id' => $eventId,
                'ref_type' => 'return',
                'ref_id' => $return->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $eventId;
    }

    /**
     * Cập nhật trạng thái từng serial hoàn về kho và ghi lịch sử bảo hành.
     */
    private function postSerials($return, $item, string $condition, ?int $eventId, int $companyId, User $user): void
    {
        foreach ($item->serials as $serial) {
            $linked = DB::table('crm_order_item_serial_units')
                ->where('order_item_id', $item->order_item_id)
                ->where('serial_unit_id', $serial->serial_unit_id)
                ->exists();
            if (!$linked) {
                throw ValidationException::withMessages(['serial' => 'Có serial không thuộc đơn gốc.']);
            }

            $stateRow = DB::table('crm_serial_unit_states')->where('serial_unit_id', $serial->serial_unit_id)->lockForUpdate()->first();
            $fromState = $stateRow->state ?? $serial->old_state;
            $toState = match ($condition) {
                'sellable' => 'in_stock',
                'damaged', 'scrap' => 'damaged',
                default => 'returned',
            };

            DB::table('crm_serial_unit_states')->updateOrInsert(
                ['serial_unit_id' => $serial->serial_unit_id],
                [
                    'warehouse_id' => (int) $return->receiving_warehouse_id,
                    'company_id' => $companyId,
                    'state' => $toState,
                    'last_event_id' => $eventId,
                    'synced_at' => now(),
                    'note' => 'Hoàn từ ' . $return->return_code . '; condition=' . $condition,
                ]
            );

            if ($eventId && Schema::hasTable('crm_serial_event_lines')) {
                DB::table('crm_serial_event_lines')->insertOrIgnore([
                    'event_id' => $eventId,
                    'serial_unit_id' => $serial->serial_unit_id,
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => (int) $return->receiving_warehouse_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('crm_serial_warranty_events')) {
                $serialCode = DB::table('crm_serial_unit_identifiers as sui')
                    ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
                    ->where('sui.serial_unit_id', $serial->serial_unit_id)
                    ->value('si.code');
                DB::table('crm_serial_warranty_events')->insert([
                    'serial_unit_id' => $serial->serial_unit_id,
                    'serial_code' => $serialCode,
                    'event_type' => 'sales_return',
                    'from_state' => $fromState,
                    'to_state' => $toState,
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => (int) $return->receiving_warehouse_id,
                    'customer_id' => $return->customer_id,
                    'order_id' => $return->order_id,
                    'created_by' => $user->id,
                    'note' => 'Hoàn hàng ' . $return->return_code . '; condition=' . $condition,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $serial->update(['final_state' => $toState]);
        }
    }
}
