<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Enums\OrderDepartment;
use App\Models\CRM\Customers\CustomerDebt;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderApproval;
use App\Services\Inventory\Stock\StockLotService;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Xử lý xuất kho, trừ tồn và quản lý serial.
 *
 * Chịu trách nhiệm:
 * - Trừ tồn kho khi xuất hàng
 * - Cập nhật trạng thái serial (removed)
 * - Tạo công nợ khi chưa thanh toán đủ
 * - Lấy danh sách serial có sẵn cho modal xuất kho
 */
class OrderInventoryHandler
{
    /** Các trạng thái serial được coi là "còn trong kho". */
    private const AVAILABLE_SERIAL_STATES = [
        'in_stock', 'available', 'in_warehouse', 'stock',
        'IN_STOCK', 'AVAILABLE', 'IN_WAREHOUSE', 'STOCK',
    ];

    /**
     * Xuất kho: trừ tồn kho, đánh dấu serial, tạo công nợ, hoàn tất đơn.
     *
     * @param  int|string  $id
     * @param  array  $data  {shipping_note?, serials?}
     * @param  OrderService  $orderService  Để ghi lịch sử
     *
     * @throws \Exception Khi không đủ tồn kho hoặc đã xuất rồi
     */
    public function shipOrder($id, array $data, OrderRepositoryInterface $orderRepo, OrderService $orderService): void
    {
        DB::transaction(function () use ($id, $data, $orderRepo, $orderService) {
            $order = $orderRepo->find($id);
            $order->loadMissing('items');

            if ($order->current_department !== OrderDepartment::WAREHOUSE->value) {
                throw new \Exception('Đơn hàng chưa đến bước xuất kho hoặc đã xuất rồi.');
            }

            if ((int) ($order->inventory_issued ?? 0) === 1) {
                throw new \Exception('Đơn hàng đã xuất kho trước đó.');
            }

            $this->markSerialsRemoved($order, $data['serials'] ?? []);
            $this->adjustInventory($order);

            $order->update([
                'inventory_issued' => true,
                'inventory_issued_at' => now(),
                'inventory_issued_by' => Auth::id(),
                'shipping_status' => 'ready',
            ]);
            $approvalHandler = app(OrderApprovalHandler::class);
            $approvalHandler->transitionToDepartment($order, OrderDepartment::COMPLETED);

            OrderApproval::where('order_id', $order->id)
                ->where('level', OrderDepartment::WAREHOUSE->value)
                ->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

            $this->createDebtIfNeeded($order);
            $this->recordShippingHistory($order, $data, $orderService);

            app(NotificationService::class)->notifySales(
                $order->created_by,
                $order,
                'shipped',
                "Đơn hàng #{$order->order_code} đã xuất kho"
            );
        });
    }

    /**
     * Lấy payload serial cho modal xuất kho.
     *
     * Chỉ trả serial cho sản phẩm có is_serialized = 1.
     *
     * @return array{order_id: int, items: array}
     */
    public function getShipSerialsPayload(Order $order): array
    {
        $items = [];
        $order->loadMissing(['items.product']);

        foreach ($order->items as $item) {
            $product = $item->product;

            if (! (int) ($product->is_serialized ?? 0)) {
                continue;
            }

            $warehouseId = $item->warehouse_id ?: $order->warehouse_id;
            if (! $warehouseId) {
                continue;
            }

            $serials = $this->getAvailableSerials($product->id, $warehouseId);

            $items[] = [
                'item_id' => $item->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku ?? '',
                'qty' => (int) $item->quantity,
                'requires_serial' => true,
                'serials' => $serials,
            ];
        }

        return [
            'order_id' => $order->id,
            'items' => $items,
        ];
    }

    /**
     * Trừ tồn kho cho tất cả items trong đơn.
     *
     * Sử dụng lockForUpdate để tránh race condition.
     *
     *
     * @throws \Exception Khi không đủ tồn hoặc không tìm thấy bản ghi stock
     */
    private function adjustInventory(Order $order): void
    {
        $order->loadMissing(['items.product']);

        foreach ($order->items as $item) {
            app(StockLotService::class)->issueOrderItemFifo($order, $item);
        }
    }

    /**
     * Đánh dấu serial đã xuất (state = removed).
     *
     * @param  array  $serialsByItem  Map [itemId => [serial1, serial2, ...]]
     */
    private function markSerialsRemoved(Order $order, array $serialsByItem): void
    {
        if (empty($serialsByItem)) {
            return;
        }

        $order->loadMissing(['items.product']);

        foreach ($order->items as $item) {
            $picked = $serialsByItem[(string) $item->id] ?? [];

            if (! is_array($picked) || empty($picked)) {
                continue;
            }

            $warehouseId = $item->warehouse_id ?: $order->warehouse_id;
            if (! $warehouseId) {
                continue;
            }

            $picked = array_values(array_unique(array_filter(array_map('trim', $picked))));
            if (empty($picked)) {
                continue;
            }

            $serialUnitIds = DB::table('crm_serial_units as su')
                ->join('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'su.id')
                ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
                ->join('crm_serial_unit_states as sus', function ($join) use ($warehouseId) {
                    $join->on('sus.serial_unit_id', '=', 'su.id')
                        ->where('sus.warehouse_id', '=', $warehouseId);
                })
                ->where('su.product_id', $item->product_id)
                ->whereIn('si.code', $picked)
                ->pluck('su.id')
                ->unique()
                ->values()
                ->toArray();

            if (empty($serialUnitIds)) {
                continue;
            }

            DB::table('crm_serial_unit_states')
                ->whereIn('serial_unit_id', $serialUnitIds)
                ->where('warehouse_id', $warehouseId)
                ->update([
                    'state' => 'removed',
                    'synced_at' => now(),
                ]);
        }
    }

    /**
     * Tạo công nợ nếu khách chưa thanh toán đủ.
     */
    private function createDebtIfNeeded(Order $order): void
    {
        $totalPaid = $order->payments()->sum('amount');

        if ($totalPaid < $order->total_amount) {
            CustomerDebt::create([
                'customer_id' => $order->lead->customer_id,
                'order_id' => $order->id,
                'total_amount' => $order->total_amount,
                'paid_amount' => $totalPaid,
                'due_date' => Carbon::now()->addDays(30),
                'status' => $totalPaid > 0 ? 'partial' : 'unpaid',
            ]);
        }
    }

    /**
     * Lấy danh sách serial còn trong kho cho 1 sản phẩm.
     */
    private function getAvailableSerials(int $productId, int $warehouseId): array
    {
        return DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'su.id')
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->join('crm_serial_unit_states as sus', function ($join) use ($warehouseId) {
                $join->on('sus.serial_unit_id', '=', 'su.id')
                    ->where('sus.warehouse_id', '=', $warehouseId);
            })
            ->where('su.product_id', $productId)
            ->whereIn('sus.state', self::AVAILABLE_SERIAL_STATES)
            ->whereIn('si.type', ['serial', 'imei'])
            ->orderBy('si.code')
            ->distinct()
            ->pluck('si.code')
            ->toArray();
    }

    /**
     * Ghi lịch sử xuất kho kèm thông tin serial.
     */
    private function recordShippingHistory(Order $order, array $data, OrderService $orderService): void
    {
        $lines = [];
        $noteText = trim((string) ($data['shipping_note'] ?? ''));

        if ($noteText !== '') {
            $lines[] = "Ghi chú: {$noteText}";
        }

        $serialsByItem = $data['serials'] ?? [];
        if (is_array($serialsByItem) && ! empty($serialsByItem)) {
            $lines[] = 'Serial/IMEI đã xuất:';

            foreach ($order->items as $item) {
                $picked = $serialsByItem[(string) $item->id] ?? [];
                if (! is_array($picked) || empty($picked)) {
                    continue;
                }

                $productName = $item->product->name ?? ('SP#'.$item->product_id);
                $sku = $item->product->sku ?? '';
                $label = $sku ? "{$productName} ({$sku})" : $productName;
                $picked = array_values(array_unique(array_filter(array_map('trim', $picked))));

                $lines[] = "- {$label}: ".implode(', ', $picked);
            }
        }

        $finalNote = 'Xuất kho';
        if (! empty($lines)) {
            $finalNote .= "\n".implode("\n", $lines);
        }

        $orderService->recordStatusHistory($order, OrderDepartment::WAREHOUSE->value, OrderDepartment::COMPLETED->value, $finalNote);
    }
}
