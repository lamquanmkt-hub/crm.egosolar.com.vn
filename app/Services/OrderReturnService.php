<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\OrderReturnServiceInterface;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderReturn;
use App\Models\CRM\Orders\OrderReturnApproval;
use App\Models\CRM\Orders\OrderReturnItem;
use App\Models\CRM\Orders\OrderReturnSerial;
use App\Models\CRM\Orders\OrderReturnStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service vòng đời phiếu đổi/trả hàng: tạo, duyệt, nhận hàng, kiểm tra, chuyển trạng thái.
 */
class OrderReturnService implements OrderReturnServiceInterface
{
    private const CLOSED = ['rejected', 'cancelled'];

    /**
     * Tạo phiếu đổi/trả từ đơn hàng: kiểm tra số lượng còn được hoàn, serial và tính tiền hoàn.
     */
    public function create(Order $order, array $data, User $user): OrderReturn
    {
        return DB::transaction(function () use ($order, $data, $user) {
            $order = Order::query()->with(['items.product', 'lead.customer'])->lockForUpdate()->findOrFail($order->id);
            $type = (string) ($data['type'] ?? 'return');

            if (in_array($type, ['return', 'exchange', 'recall'], true) && ! (bool) $order->inventory_issued) {
                throw ValidationException::withMessages([
                    'type' => 'Đơn chưa xuất kho. Hãy dùng loại “Hủy trước xuất kho”.',
                ]);
            }

            if ($type === 'cancel' && (bool) $order->inventory_issued) {
                throw ValidationException::withMessages([
                    'type' => 'Đơn đã xuất kho nên không thể hủy trực tiếp. Hãy tạo yêu cầu thu hồi hoặc hoàn trả.',
                ]);
            }

            $customerId = (int) ($order->lead?->customer_id ?? 0) ?: null;

            $return = OrderReturn::create([
                'order_id' => $order->id,
                'company_id' => $order->company_id,
                'customer_id' => $customerId,
                'receiving_warehouse_id' => $data['receiving_warehouse_id'] ?? null,
                'type' => $type,
                'reason' => $data['reason_detail'] ?? $data['reason'] ?? null,
                'reason_code' => $data['reason_code'] ?? null,
                'reason_detail' => $data['reason_detail'] ?? null,
                'status' => 'draft',
                'requested_by' => $user->id,
                'refund_method' => $data['refund_method'] ?? null,
                'restocking_fee' => (float) ($data['restocking_fee'] ?? 0),
                'shipping_fee' => (float) ($data['shipping_fee'] ?? 0),
                'financial_status' => $type === 'cancel' ? 'not_required' : 'pending',
                'inventory_status' => $type === 'cancel' ? 'not_required' : 'not_received',
                'invoice_adjustment_status' => ($order->invoice_status ?? 'no_invoice') === 'issued' ? 'required' : 'not_required',
                'note' => $data['note'] ?? null,
            ]);

            $return->update([
                'return_code' => 'RTN-'.now()->format('Y').'-'.str_pad((string) $return->id, 6, '0', STR_PAD_LEFT),
            ]);

            $total = 0.0;
            $itemsData = $data['items'] ?? [];

            if ($type !== 'cancel') {
                if (empty($itemsData)) {
                    throw ValidationException::withMessages(['items' => 'Cần chọn ít nhất một sản phẩm.']);
                }

                foreach ($itemsData as $orderItemId => $row) {
                    $requested = (int) ($row['quantity'] ?? 0);
                    if ($requested <= 0) {
                        continue;
                    }

                    $orderItem = $order->items->firstWhere('id', (int) $orderItemId);
                    if (! $orderItem) {
                        throw ValidationException::withMessages(['items' => 'Có sản phẩm không thuộc đơn hàng.']);
                    }

                    $alreadyAccepted = (int) DB::table('order_return_items as ri')
                        ->join('order_returns as r', 'r.id', '=', 'ri.order_return_id')
                        ->where('ri.order_item_id', $orderItem->id)
                        ->whereNotIn('r.status', self::CLOSED)
                        ->sum('ri.accepted_quantity');

                    $alreadyRequested = (int) DB::table('order_return_items as ri')
                        ->join('order_returns as r', 'r.id', '=', 'ri.order_return_id')
                        ->where('ri.order_item_id', $orderItem->id)
                        ->whereNotIn('r.status', array_merge(self::CLOSED, ['completed']))
                        ->sum('ri.requested_quantity');

                    $available = max(0, (int) $orderItem->quantity - $alreadyAccepted - $alreadyRequested);
                    if ($requested > $available) {
                        throw ValidationException::withMessages([
                            "items.{$orderItemId}.quantity" => "Chỉ còn có thể hoàn {$available} sản phẩm.",
                        ]);
                    }

                    $qty = max(1, (int) $orderItem->quantity);
                    $effectiveUnit = ((float) ($orderItem->line_total ?? 0)) / $qty;
                    if ($effectiveUnit <= 0) {
                        $effectiveUnit = max(0, (float) $orderItem->unit_price * (1 - ((float) $orderItem->discount_percent / 100)) - ((float) $orderItem->discount_amount / $qty));
                    }
                    $lineAmount = round($effectiveUnit * $requested, 2);

                    $returnItem = OrderReturnItem::create([
                        'order_return_id' => $return->id,
                        'order_item_id' => $orderItem->id,
                        'product_id' => $orderItem->product_id,
                        'warehouse_id' => $orderItem->warehouse_id ?: $order->warehouse_id,
                        'ordered_quantity' => (int) $orderItem->quantity,
                        'requested_quantity' => $requested,
                        'unit_price' => $effectiveUnit,
                        'vat_rate' => (float) ($orderItem->vat_percent ?? 0),
                        'discount_amount' => (float) ($orderItem->discount_amount ?? 0),
                        'return_amount' => $lineAmount,
                        'condition' => $row['condition'] ?? null,
                        'resolution' => $row['resolution'] ?? null,
                        'note' => $row['note'] ?? null,
                    ]);

                    $serialIds = array_values(array_unique(array_map('intval', $row['serial_ids'] ?? [])));
                    $isSerialized = (bool) ($orderItem->product->is_serialized ?? false);
                    if ($isSerialized && count($serialIds) !== $requested) {
                        throw ValidationException::withMessages([
                            "items.{$orderItemId}.serial_ids" => 'Sản phẩm quản lý serial: phải chọn đúng số serial bằng số lượng yêu cầu hoàn.',
                        ]);
                    }
                    if (count($serialIds) > $requested) {
                        throw ValidationException::withMessages([
                            "items.{$orderItemId}.serial_ids" => 'Số serial không được vượt số lượng yêu cầu hoàn.',
                        ]);
                    }

                    foreach ($serialIds as $serialId) {
                        $isLinked = DB::table('crm_order_item_serial_units')
                            ->where('order_item_id', $orderItem->id)
                            ->where('serial_unit_id', $serialId)
                            ->exists();
                        if (! $isLinked) {
                            throw ValidationException::withMessages([
                                "items.{$orderItemId}.serial_ids" => "Serial #{$serialId} không thuộc dòng đơn này.",
                            ]);
                        }
                        $oldState = DB::table('crm_serial_unit_states')->where('serial_unit_id', $serialId)->value('state');
                        OrderReturnSerial::create([
                            'order_return_item_id' => $returnItem->id,
                            'serial_unit_id' => $serialId,
                            'old_state' => $oldState,
                        ]);
                    }

                    $total += $lineAmount;
                }
            }

            $return->update([
                'total_return_amount' => round($total, 2),
                'refund_amount' => max(0, round($total - (float) $return->restocking_fee - (float) $return->shipping_fee, 2)),
            ]);

            $this->history($return, null, 'draft', 'create', 'Tạo yêu cầu đổi/trả', $user);

            return $return->fresh(['items.serials']);
        });
    }

    /**
     * Gửi phiếu đổi/trả cho quản lý sales duyệt.
     */
    public function submit(OrderReturn $return, User $user): OrderReturn
    {
        if (! in_array($return->status, ['draft', 'revision_requested'], true)) {
            throw ValidationException::withMessages(['status' => 'Phiếu không ở trạng thái có thể gửi duyệt.']);
        }

        return $this->transition($return, 'pending_sales_manager', 'submit', 'Gửi yêu cầu duyệt', $user, [
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Phê duyệt phiếu theo chuỗi: sales manager -> kế toán -> (BGĐ nếu giá trị lớn) -> chờ nhận hàng.
     */
    public function approve(OrderReturn $return, User $user, ?string $comment = null): OrderReturn
    {
        $next = match ($return->status) {
            'pending_sales_manager' => 'pending_accounting',
            'pending_accounting' => ((float) $return->refund_amount >= 10000000 || $return->type === 'exchange')
                ? 'pending_management'
                : 'approved_waiting_return',
            'pending_management' => 'approved_waiting_return',
            default => null,
        };
        if (! $next) {
            throw ValidationException::withMessages(['status' => 'Phiếu không ở bước phê duyệt hợp lệ.']);
        }

        OrderReturnApproval::create([
            'order_return_id' => $return->id,
            'level' => $return->status,
            'action' => 'approve',
            'status' => 'approved',
            'approver_id' => $user->id,
            'comment' => $comment,
        ]);

        $extra = $next === 'approved_waiting_return'
            ? ['approved_by' => $user->id, 'approved_at' => now()]
            : [];

        return $this->transition($return, $next, 'approve', $comment ?: 'Phê duyệt', $user, $extra);
    }

    /**
     * Từ chối phiếu đổi/trả kèm lý do.
     */
    public function reject(OrderReturn $return, User $user, string $comment): OrderReturn
    {
        OrderReturnApproval::create([
            'order_return_id' => $return->id,
            'level' => $return->status,
            'action' => 'reject',
            'status' => 'rejected',
            'approver_id' => $user->id,
            'comment' => $comment,
        ]);

        return $this->transition($return, 'rejected', 'reject', $comment, $user);
    }

    /**
     * Yêu cầu sales chỉnh sửa lại phiếu đổi/trả.
     */
    public function requestRevision(OrderReturn $return, User $user, string $comment): OrderReturn
    {
        OrderReturnApproval::create([
            'order_return_id' => $return->id,
            'level' => $return->status,
            'action' => 'request_revision',
            'status' => 'revision_requested',
            'approver_id' => $user->id,
            'comment' => $comment,
        ]);

        return $this->transition($return, 'revision_requested', 'request_revision', $comment, $user);
    }

    /**
     * Kho xác nhận số lượng hàng hoàn thực nhận.
     */
    public function receive(OrderReturn $return, User $user, array $quantities): OrderReturn
    {
        return DB::transaction(function () use ($return, $user, $quantities) {
            $return = OrderReturn::query()->lockForUpdate()->findOrFail($return->id);
            if (! in_array($return->status, ['approved_waiting_return', 'return_in_transit'], true)) {
                throw ValidationException::withMessages(['status' => 'Phiếu chưa được duyệt để kho nhận hàng.']);
            }
            foreach ($return->items as $item) {
                $received = (int) ($quantities[$item->id] ?? 0);
                if ($received < 0 || $received > $item->requested_quantity) {
                    throw ValidationException::withMessages(["received.{$item->id}" => 'Số lượng nhận không hợp lệ.']);
                }
                $item->update(['received_quantity' => $received]);
            }

            return $this->transition($return, 'received', 'receive', 'Kho đã nhận hàng thực tế', $user, [
                'received_by' => $user->id,
                'received_at' => now(),
                'inventory_status' => 'received',
            ]);
        });
    }

    /**
     * Kho kiểm tra, phân loại tình trạng hàng hoàn và cập nhật serial.
     */
    public function inspect(OrderReturn $return, User $user, array $rows): OrderReturn
    {
        return DB::transaction(function () use ($return, $user, $rows) {
            $return = OrderReturn::query()->lockForUpdate()->findOrFail($return->id);
            if (! in_array($return->status, ['received', 'inspecting'], true)) {
                throw ValidationException::withMessages(['status' => 'Kho cần xác nhận đã nhận hàng trước khi kiểm tra.']);
            }
            foreach ($return->items as $item) {
                $row = $rows[$item->id] ?? [];
                $accepted = (int) ($row['accepted_quantity'] ?? 0);
                $rejected = (int) ($row['rejected_quantity'] ?? max(0, $item->received_quantity - $accepted));
                if ($accepted < 0 || $rejected < 0 || ($accepted + $rejected) > $item->received_quantity) {
                    throw ValidationException::withMessages(["inspect.{$item->id}" => 'Số lượng kiểm tra không hợp lệ.']);
                }
                $condition = (string) ($row['condition'] ?? 'sellable');
                if (! in_array($condition, ['sellable', 'opened_box', 'defective', 'warranty_pending', 'damaged', 'scrap'], true)) {
                    throw ValidationException::withMessages(["inspect.{$item->id}.condition" => 'Tình trạng hàng không hợp lệ.']);
                }
                $item->update([
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => $rejected,
                    'condition' => $condition,
                    'resolution' => $row['resolution'] ?? null,
                    'note' => $row['note'] ?? $item->note,
                ]);
                foreach ($item->serials as $serial) {
                    $serial->update([
                        'inspected_state' => $condition,
                        'inspected_by' => $user->id,
                        'inspected_at' => now(),
                    ]);
                }
            }

            return $this->transition($return, 'inspected', 'inspect', 'Kho đã kiểm tra và phân loại hàng', $user, [
                'inspected_by' => $user->id,
                'inspected_at' => now(),
                'inventory_status' => 'inspected',
            ]);
        });
    }

    /**
     * Chuyển trạng thái phiếu và ghi lịch sử.
     */
    public function transition(OrderReturn $return, string $to, string $action, ?string $note, User $user, array $extra = []): OrderReturn
    {
        $from = $return->status;
        $return->forceFill(array_merge(['status' => $to], $extra))->save();
        $this->history($return, $from, $to, $action, $note, $user);

        return $return->fresh();
    }

    /**
     * Ghi lịch sử thay đổi trạng thái phiếu kèm IP và user agent.
     */
    public function history(OrderReturn $return, ?string $from, string $to, string $action, ?string $note, User $user): void
    {
        OrderReturnStatusHistory::create([
            'order_return_id' => $return->id,
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'note' => $note,
            'changed_by' => $user->id,
            'metadata' => [
                'ip' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            ],
            'created_at' => now(),
        ]);
    }
}
