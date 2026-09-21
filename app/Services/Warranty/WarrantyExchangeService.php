<?php

declare(strict_types=1);

namespace App\Services\Warranty;

use App\Models\SolarWarrantyClaim;
use App\Models\User;
use App\Support\SchemaCache;
use App\Support\SolarMaintenanceAccess;
use App\Support\Warranty\WarrantyException;
use App\Support\Warranty\WarrantyFlow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Quy trình A — ĐỔI HÀNG BẢO HÀNH (claim_type = replacement).
 * Mọi hành động: khóa dòng phiếu (lockForUpdate) → kiểm tra quyền + trạng thái (whitelist) → ghi dữ liệu → audit.
 */
final class WarrantyExchangeService
{
    private const T = WarrantyFlow::TYPE_EXCHANGE;

    public function __construct(private readonly StockLedger $ledger)
    {
    }

    // ------------------------------------------------------------------ tạo phiếu

    /**
     * @param  array<string,mixed>  $data  đã validate ở controller
     * @param  array<string,mixed>  $ctx   serial (object), site_id, order_id, customer_id, company_id, warranty_active, assignee_id/name
     */
    public function create(User $actor, array $data, array $ctx): SolarWarrantyClaim
    {
        $serial = $ctx['serial'];
        $exception = ! $ctx['warranty_active'];
        $reason = trim((string) ($data['exception_reason'] ?? ''));

        if ($exception && mb_strlen($reason) < $this->minReason()) {
            throw new WarrantyException('Serial không còn bảo hành: bắt buộc nhập LÝ DO ngoại lệ (tối thiểu '.$this->minReason().' ký tự).');
        }
        if ($exception && ! SolarMaintenanceAccess::canCreateWarrantyClaim($actor)) {
            throw new WarrantyException('Bạn không có quyền đề nghị ngoại lệ bảo hành.');
        }

        return DB::transaction(function () use ($actor, $data, $ctx, $serial, $exception, $reason): SolarWarrantyClaim {
            $this->guardNoOpenClaim((int) $serial->serial_unit_id);

            $internal = trim((string) ($data['internal_note'] ?? ''));
            $source = ($ctx['source_type'] ?? 'site') === 'order'
                ? 'Nguồn tạo phiếu: Đơn hàng #'.($ctx['order_id'] ?? '')
                : 'Nguồn tạo phiếu: Công trình #'.($ctx['site_id'] ?? '');

            try {
                $claim = SolarWarrantyClaim::create([
                    'company_id' => $ctx['company_id'] ?: null,
                    'site_id' => $ctx['site_id'],
                    'serial_unit_id' => (int) $serial->serial_unit_id,
                    'serial_code' => (string) $serial->serial_code,
                    'customer_id' => $ctx['customer_id'] ?: null,
                    'order_id' => $ctx['order_id'],
                    'claim_type' => self::T,
                    'priority' => $data['priority'],
                    'status' => 'pending_approval',
                    'approval_status' => 'pending',
                    'assigned_to' => $ctx['assignee_id'] ?? null,
                    'assigned_name' => $ctx['assignee_name'] ?? null,
                    'received_at' => now()->toDateString(),
                    'issue_description' => trim((string) $data['issue_description']),
                    'diagnosis' => trim((string) $data['diagnosis']),
                    'proposed_solution' => trim((string) $data['proposed_solution']),
                    'submitted_at' => now(),
                    'submitted_by' => $actor->id,
                    'estimated_cost' => (float) ($data['estimated_cost'] ?? 0),
                    'is_chargeable' => false,
                    'internal_note' => trim($source."\n".$internal),
                    'created_by' => $actor->id,
                ]);
            } catch (QueryException $e) {
                throw new WarrantyException('Serial này vừa có phiếu khác được tạo. Vui lòng tải lại trang.');
            }

            $extra = [
                'claim_code' => sprintf('DXBH-%s-%06d', now()->format('Y'), $claim->id),
                'open_serial_key' => (int) $serial->serial_unit_id,
                'status_changed_at' => now(),
                'warranty_eligibility' => $exception ? 'exception' : 'in_warranty',
            ];
            if (isset($data['diagnosis_cause'])) {
                $extra['diagnosis_cause'] = $data['diagnosis_cause'];
            }
            if ($exception) {
                $extra += [
                    'warranty_exception' => true,
                    'exception_reason' => $reason,
                    'exception_requested_by' => $actor->id,
                    'exception_requested_at' => now(),
                ];
            }
            DB::table('crm_serial_warranty_claims')->where('id', $claim->id)->update($extra);
            $claim->refresh();

            WarrantyAudit::log($claim->id, 'received', null, 'received', null, [
                'serial' => $claim->serial_code, 'site_id' => $claim->site_id, 'order_id' => $claim->order_id,
            ], null, $actor->id);
            WarrantyAudit::log($claim->id, 'eligibility_check', 'received', 'eligibility_check', null, [
                'warranty_active' => ! $exception, 'exception' => $exception,
            ], $exception ? $reason : null, $actor->id);
            if ($exception) {
                WarrantyAudit::log($claim->id, 'exception_requested', null, null, null, ['reason' => $reason], $reason, $actor->id);
            }
            WarrantyAudit::log($claim->id, 'create', 'eligibility_check', 'pending_approval', null, [
                'priority' => $claim->priority, 'estimated_cost' => (string) $claim->estimated_cost,
                'diagnosis' => $claim->diagnosis, 'proposed_solution' => $claim->proposed_solution,
            ], null, $actor->id);

            $this->serialEvent($claim, 'maintenance_replacement_proposed', 'Tạo đề xuất đổi hàng '.$claim->claim_code, $actor->id);
            $this->notify($claim, 'approver', 'approval_needed', 'Có đề xuất đổi hàng chờ duyệt', $claim->claim_code.' — serial '.$claim->serial_code);

            return $claim;
        });
    }

    public function guardNoOpenClaim(int $serialUnitId, ?int $exceptClaimId = null): void
    {
        $q = SolarWarrantyClaim::query()
            ->where('serial_unit_id', $serialUnitId)
            ->whereIn('claim_type', [WarrantyFlow::TYPE_EXCHANGE, WarrantyFlow::TYPE_REPAIR])
            ->whereNotIn('status', WarrantyFlow::TERMINAL)
            ->lockForUpdate();
        if ($exceptClaimId) {
            $q->where('id', '<>', $exceptClaimId);
        }
        if ($q->exists()) {
            throw new WarrantyException('Serial này đã có một phiếu (đổi hàng/sửa chữa) đang mở. Hãy xử lý phiếu hiện tại trước.');
        }
    }

    // ------------------------------------------------------------------ duyệt

    public function approve(int $claimId, User $actor, ?string $note = null, ?string $overrideReason = null): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor, $note, $overrideReason) {
            $c = $this->lock($claimId);
            $this->requireLead($actor);
            $this->requireStatus($c, ['pending_approval']);

            $self = in_array((int) $actor->id, array_filter([
                (int) $c->created_by, (int) $c->assigned_to, (int) $c->exception_requested_by,
            ]), true);
            $override = false;
            if ($self) {
                if (! SolarMaintenanceAccess::canOverrideWarranty($actor)) {
                    throw new WarrantyException('Người tạo/phụ trách/đề nghị ngoại lệ không được tự duyệt phiếu của mình. Cần người khác có thẩm quyền duyệt.');
                }
                $overrideReason = trim((string) $overrideReason);
                if (mb_strlen($overrideReason) < $this->minReason()) {
                    throw new WarrantyException('Tự duyệt (emergency override) bắt buộc nhập lý do.');
                }
                $override = true;
            }

            $before = ['status' => $c->status, 'approved_by' => $c->approved_by];
            $updates = [
                'approval_status' => 'approved',
                'approval_note' => $note ?: $c->approval_note,
            ];
            // KHÔNG ghi đè người duyệt cũ
            if (! $c->approved_by) {
                $updates['approved_by'] = $actor->id;
                $updates['approved_at'] = now();
            }
            if ($c->warranty_exception && ! $c->exception_approved_by) {
                $updates['exception_approved_by'] = $actor->id;
                $updates['exception_approved_at'] = now();
                if ($override) {
                    $updates['exception_override'] = true;
                }
            }
            if ($override) {
                $updates['override_reason'] = $overrideReason;
            }

            $this->transition($c, 'approved', $actor, $override ? 'approve_override' : 'approve', $overrideReason ?: $note, $updates, $before);
            $this->transition($c, 'waiting_stock', $actor, 'to_warehouse', null, []);
            $this->notify($c, 'warehouse', 'warehouse_task', 'Việc mới cho Kho: chọn serial thay thế', $c->claim_code.' — serial lỗi '.$c->serial_code);
            $this->notifyUser($c, (int) $c->assigned_to ?: (int) $c->created_by, 'approved', 'Đề xuất đã được duyệt', $c->claim_code);

            return $c;
        });
    }

    public function requestInfo(int $claimId, User $actor, string $reason): SolarWarrantyClaim
    {
        return $this->decide($claimId, $actor, $reason, 'needs_more_information', 'request_info', 'needs_info');
    }

    public function reject(int $claimId, User $actor, string $reason): SolarWarrantyClaim
    {
        return $this->decide($claimId, $actor, $reason, 'rejected', 'reject', 'rejected');
    }

    private function decide(int $claimId, User $actor, string $reason, string $to, string $action, string $approvalStatus): SolarWarrantyClaim
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < $this->minReason()) {
            throw new WarrantyException('Bắt buộc nhập lý do (tối thiểu '.$this->minReason().' ký tự).');
        }

        return DB::transaction(function () use ($claimId, $actor, $reason, $to, $action, $approvalStatus) {
            $c = $this->lock($claimId);
            $this->requireLead($actor);
            $this->requireStatus($c, ['pending_approval']);
            $this->transition($c, $to, $actor, $action, $reason, [
                'approval_status' => $approvalStatus,
                'decision_reason' => $reason,
                'decision_by' => $actor->id,
                'decision_at' => now(),
            ]);
            $this->notifyUser($c, (int) $c->assigned_to ?: (int) $c->created_by, $action, $to === 'rejected' ? 'Đề xuất bị từ chối' : 'Đề xuất cần bổ sung', $c->claim_code.': '.$reason);

            return $c;
        });
    }

    public function resubmit(int $claimId, User $actor, array $changes): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor, $changes) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['needs_more_information']);
            $this->requireOwnerOrLead($c, $actor);

            $fields = [];
            foreach (['issue_description', 'diagnosis', 'proposed_solution'] as $f) {
                if (array_key_exists($f, $changes) && trim((string) $changes[$f]) !== '') {
                    $fields[$f] = trim((string) $changes[$f]);
                }
            }
            $before = array_intersect_key($c->getAttributes(), $fields);
            $this->transition($c, 'pending_approval', $actor, 'resubmit', null, $fields + [
                'approval_status' => 'pending',
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
            ], $before);
            $this->notify($c, 'approver', 'approval_needed', 'Phiếu đã bổ sung, chờ duyệt lại', $c->claim_code);

            return $c;
        });
    }

    public function reopenRejected(int $claimId, User $actor, string $reason): SolarWarrantyClaim
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < $this->minReason()) {
            throw new WarrantyException('Bắt buộc nhập lý do mở lại.');
        }

        return DB::transaction(function () use ($claimId, $actor, $reason) {
            $c = $this->lock($claimId);
            $this->requireLead($actor);
            $this->requireStatus($c, ['rejected']);
            $this->guardNoOpenClaim((int) $c->serial_unit_id, (int) $c->id);
            $this->transition($c, 'needs_more_information', $actor, 'reopen', $reason, ['approval_status' => 'needs_info']);

            return $c;
        });
    }

    public function cancel(int $claimId, User $actor, string $reason): SolarWarrantyClaim
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < $this->minReason()) {
            throw new WarrantyException('Bắt buộc nhập lý do hủy phiếu.');
        }

        return DB::transaction(function () use ($claimId, $actor, $reason) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['pending_approval', 'needs_more_information', 'approved', 'waiting_stock', 'reserved']);
            $isCreator = (int) $c->created_by === (int) $actor->id && in_array($c->status, ['pending_approval', 'needs_more_information'], true);
            if (! $isCreator && ! SolarMaintenanceAccess::isTechnicalLead($actor)) {
                throw new WarrantyException('Chỉ Trưởng phòng/Admin (hoặc người tạo khi chưa duyệt) được hủy phiếu.');
            }

            if ($c->status === 'reserved') {
                $this->releaseActiveReservation($c, $actor, 'Hủy phiếu: '.$reason);
            }
            // hủy mọi phiếu kho còn mở của phiếu này
            DB::table('solar_warranty_stock_movements')
                ->where('warranty_claim_id', $c->id)->whereIn('status', ['pending', 'approved'])
                ->update(['status' => 'cancelled', 'updated_at' => now()]);

            $this->transition($c, 'cancelled', $actor, 'cancel', $reason, ['approval_status' => $c->approval_status]);
            $this->notify($c, 'warehouse', 'cancelled', 'Phiếu bảo hành đã hủy', $c->claim_code.': '.$reason);

            return $c;
        });
    }

    // ------------------------------------------------------------------ KHO

    public function reserveSerial(int $claimId, User $actor, string $serialCode, int $warehouseId, ?string $note = null): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor, $serialCode, $warehouseId, $note) {
            $c = $this->lock($claimId);
            $this->requireWarehouse($actor);
            $this->requireStatus($c, ['approved', 'waiting_stock', 'reserved']); // approved = phiếu cũ trước v2
            $this->assertWarehouseCompany($warehouseId, (int) $c->company_id);

            $old = $this->serialRow((int) $c->serial_unit_id);
            $new = $this->serialByCode(trim($serialCode));
            if (! $new) {
                throw new WarrantyException('Không tìm thấy serial “'.$serialCode.'”.');
            }
            if ((int) $new->serial_unit_id === (int) $c->serial_unit_id) {
                throw new WarrantyException('Serial thay thế phải khác serial thiết bị lỗi.');
            }
            if ($old && (int) $new->product_id !== (int) $old->product_id) {
                throw new WarrantyException('Serial thay thế phải CÙNG sản phẩm/model với thiết bị lỗi.');
            }

            // đang giữ serial khác cho chính phiếu này → nhả trước (đổi serial)
            if ($c->status === 'reserved') {
                if ((int) $c->reserved_serial_unit_id === (int) $new->serial_unit_id) {
                    throw new WarrantyException('Serial này đã được giữ cho phiếu.');
                }
                $this->releaseActiveReservation($c, $actor, 'Đổi sang serial khác: '.$new->serial_code);
                $this->transition($c, 'waiting_stock', $actor, 'swap_reservation', 'Đổi sang serial khác: '.$new->serial_code, [
                    'reserved_serial_unit_id' => null,
                    'reserved_serial_code' => null,
                ]);
            }

            $state = $this->ledger->lockState((int) $new->serial_unit_id);
            if (! $state || (string) $state->state !== 'in_stock') {
                throw new WarrantyException('Serial thay thế không ở trạng thái tồn kho sẵn sàng (hiện: '.($state->state ?? 'không rõ').').');
            }
            if ((int) $state->warehouse_id !== $warehouseId) {
                throw new WarrantyException('Serial thay thế không nằm trong kho đã chọn.');
            }
            $stateCompany = (int) ($state->company_id ?? 0);
            if ($stateCompany > 0 && (int) $c->company_id > 0 && $stateCompany !== (int) $c->company_id) {
                throw new WarrantyException('Serial thay thế thuộc công ty khác.');
            }
            $held = DB::table('warranty_serial_reservations')->where('serial_unit_id', $new->serial_unit_id)->where('status', 'active')->lockForUpdate()->exists();
            $openMovement = DB::table('solar_warranty_stock_movements')->where('serial_unit_id', $new->serial_unit_id)->whereIn('status', ['pending', 'approved'])->exists();
            if ($held || $openMovement) {
                throw new WarrantyException('Serial này đang được giữ/có phiếu kho đang mở cho phiếu khác.');
            }

            $movementId = (int) DB::table('solar_warranty_stock_movements')->insertGetId([
                'movement_code' => 'TMP-'.uniqid(),
                'company_id' => $c->company_id,
                'warranty_claim_id' => $c->id,
                'site_id' => $c->site_id,
                'movement_type' => 'warranty_out',
                'status' => 'approved',
                'warehouse_id' => $warehouseId,
                'product_id' => $new->product_id,
                'serial_unit_id' => $new->serial_unit_id,
                'serial_code' => $new->serial_code,
                'quantity' => 1,
                'requested_by' => $actor->id,
                'approved_by' => $actor->id,
                'requested_at' => now(),
                'approved_at' => now(),
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('solar_warranty_stock_movements')->where('id', $movementId)->update([
                'movement_code' => sprintf('XKBH-%s-%06d', now()->format('Y'), $movementId),
            ]);

            try {
                $resId = (int) DB::table('warranty_serial_reservations')->insertGetId([
                    'serial_unit_id' => $new->serial_unit_id,
                    'serial_code' => $new->serial_code,
                    'claim_id' => $c->id,
                    'movement_id' => $movementId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $new->product_id,
                    'status' => 'active',
                    'active_key' => $new->serial_unit_id,
                    'reserved_by' => $actor->id,
                    'reserved_at' => now(),
                    'previous_state' => 'in_stock',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException) {
                throw new WarrantyException('Serial này vừa được giữ cho phiếu khác.');
            }
            DB::table('solar_warranty_stock_movements')->where('id', $movementId)->update(['reservation_id' => $resId]);

            $this->ledger->setSerialState((int) $new->serial_unit_id, 'reserved', $warehouseId, (int) $c->company_id ?: null, 'Giữ hàng cho phiếu '.$c->claim_code, (int) $actor->id);

            $this->transition($c, 'reserved', $actor, 'reserve', $note, [
                'reserved_serial_unit_id' => $new->serial_unit_id,
                'reserved_serial_code' => $new->serial_code,
            ], null, 'reservation', $resId);

            return $c;
        });
    }

    public function releaseReservation(int $claimId, User $actor, string $reason): SolarWarrantyClaim
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < $this->minReason()) {
            throw new WarrantyException('Bắt buộc nhập lý do nhả hàng.');
        }

        return DB::transaction(function () use ($claimId, $actor, $reason) {
            $c = $this->lock($claimId);
            $this->requireWarehouse($actor);
            $this->requireStatus($c, ['reserved']);
            $this->releaseActiveReservation($c, $actor, $reason);
            $this->transition($c, 'waiting_stock', $actor, 'release_reservation', $reason, [
                'reserved_serial_unit_id' => null,
                'reserved_serial_code' => null,
            ]);

            return $c;
        });
    }

    public function issue(int $claimId, User $actor, ?string $note = null): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor, $note) {
            $c = $this->lock($claimId);
            $this->requireWarehouse($actor);
            $this->requireStatus($c, ['reserved']); // cancelled/rejected/completed/issued đều bị chặn tại đây

            $res = DB::table('warranty_serial_reservations')->where('claim_id', $c->id)->where('status', 'active')->lockForUpdate()->first();
            if (! $res) {
                throw new WarrantyException('Phiếu chưa có serial được giữ hàng.');
            }
            $move = DB::table('solar_warranty_stock_movements')->where('id', $res->movement_id)->lockForUpdate()->first();
            if (! $move || $move->status !== 'approved') {
                throw new WarrantyException('Phiếu kho không ở trạng thái chờ xuất.');
            }
            $state = $this->ledger->lockState((int) $res->serial_unit_id);
            if (! $state || $state->state !== 'reserved' || (int) $state->warehouse_id !== (int) $res->warehouse_id) {
                throw new WarrantyException('Serial không còn ở trạng thái đã giữ tại kho — kiểm tra lại trước khi xuất.');
            }

            $this->ledger->issueSerial((int) $res->serial_unit_id, (int) $res->product_id, (int) $res->warehouse_id, (int) $c->company_id ?: null, 'Xuất đổi bảo hành '.$c->claim_code, (int) $actor->id, (int) $c->id);

            DB::table('solar_warranty_stock_movements')->where('id', $move->id)->update([
                'status' => 'completed', 'completed_by' => $actor->id, 'completed_at' => now(), 'updated_at' => now(),
                'note' => $note ?: $move->note,
            ]);
            DB::table('warranty_serial_reservations')->where('id', $res->id)->update([
                'status' => 'consumed', 'active_key' => null, 'updated_at' => now(),
            ]);

            $this->transition($c, 'issued', $actor, 'issue', $note, [
                'replacement_serial_unit_id' => $res->serial_unit_id,
                'replacement_serial_code' => $res->serial_code,
                'issued_at' => now(),
                'issued_by' => $actor->id,
            ], null, 'movement', (int) $move->id);
            $this->serialEvent($c, 'maintenance_warranty_out', 'Xuất '.$res->serial_code.' thay cho '.$c->serial_code.' — '.$c->claim_code, (int) $actor->id, (int) $res->serial_unit_id);
            $this->notifyUser($c, (int) $c->assigned_to ?: (int) $c->created_by, 'issued', 'Kho đã xuất thiết bị thay thế', $c->claim_code.' — '.$res->serial_code.'. Vui lòng xác nhận đã nhận hàng.');

            return $c;
        });
    }

    public function confirmFaultyReturn(int $claimId, User $actor, int $warehouseId, string $returnedBy, string $condition, ?string $note): SolarWarrantyClaim
    {
        $returnedBy = trim($returnedBy);
        if ($returnedBy === '') {
            throw new WarrantyException('Vui lòng nhập người mang thiết bị lỗi về.');
        }
        if (! array_key_exists($condition, WarrantyFlow::FAULTY_CONDITIONS)) {
            throw new WarrantyException('Tình trạng thiết bị thu hồi không hợp lệ.');
        }

        return DB::transaction(function () use ($claimId, $actor, $warehouseId, $returnedBy, $condition, $note) {
            $c = $this->lock($claimId);
            $this->requireWarehouse($actor);
            if ($c->status === 'completed' && $c->faulty_return_status === 'deferred') {
                // thu hồi muộn sau khi phiếu đã hoàn tất: chỉ cập nhật dữ liệu thu hồi, không đổi trạng thái
            } else {
                $this->requireStatus($c, ['waiting_faulty_return']);
            }
            if ($c->faulty_return_status === 'returned' || $c->returned_serial_unit_id) {
                throw new WarrantyException('Thiết bị lỗi của phiếu này đã được thu hồi.');
            }
            $this->assertWarehouseCompany($warehouseId, (int) $c->company_id);

            $old = $this->serialRow((int) $c->serial_unit_id);
            $state = $this->ledger->lockState((int) $c->serial_unit_id);
            if ($state && in_array((string) $state->state, ['in_stock', 'reserved'], true)) {
                throw new WarrantyException('Serial lỗi đang nằm trong kho dưới trạng thái '.$state->state.' — không thể nhập thu hồi.');
            }

            $this->ledger->receiveSerial((int) $c->serial_unit_id, (int) ($old->product_id ?? 0), $warehouseId, (int) $c->company_id ?: null, 'damaged', 'Thu hồi thiết bị lỗi '.$c->claim_code, (int) $actor->id, (int) $c->id);

            $moveId = (int) DB::table('solar_warranty_stock_movements')->insertGetId([
                'movement_code' => 'TMP-'.uniqid(),
                'company_id' => $c->company_id, 'warranty_claim_id' => $c->id, 'site_id' => $c->site_id,
                'movement_type' => 'faulty_return', 'status' => 'completed', 'warehouse_id' => $warehouseId,
                'product_id' => $old->product_id ?? null, 'serial_unit_id' => $c->serial_unit_id, 'serial_code' => $c->serial_code,
                'quantity' => 1, 'requested_by' => $actor->id, 'approved_by' => $actor->id, 'completed_by' => $actor->id,
                'requested_at' => now(), 'approved_at' => now(), 'completed_at' => now(), 'note' => $note,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('solar_warranty_stock_movements')->where('id', $moveId)->update(['movement_code' => sprintf('XKBH-%s-%06d', now()->format('Y'), $moveId)]);

            $fields = [
                'returned_serial_unit_id' => $c->serial_unit_id,
                'returned_serial_code' => $c->serial_code,
                'returned_at' => now(),
                'faulty_return_status' => 'returned',
                'faulty_returned_by' => $returnedBy,
                'faulty_received_by' => $actor->id,
                'faulty_return_warehouse_id' => $warehouseId,
                'faulty_condition' => $condition,
                'faulty_return_note' => $note,
            ];
            if ($c->status === 'completed') {
                $before = array_intersect_key($c->getAttributes(), $fields);
                DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update($fields + ['updated_at' => now()]);
                WarrantyAudit::log((int) $c->id, 'faulty_return_late', 'completed', 'completed', $before, $fields, $note, (int) $actor->id, 'movement', $moveId);
                $c->refresh();
            } else {
                $this->transition($c, 'faulty_returned', $actor, 'faulty_return', $note, $fields, null, 'movement', $moveId);
            }
            $this->serialEvent($c, 'maintenance_faulty_return', 'Thu hồi thiết bị lỗi '.$c->serial_code.' — '.$c->claim_code, (int) $actor->id);

            return $c;
        });
    }

    // ------------------------------------------------------------------ KỸ THUẬT

    public function techReceive(int $claimId, User $actor, ?string $receivedAt, ?string $deliveredBy, ?string $note): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor, $receivedAt, $deliveredBy, $note) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['issued']);
            $this->requireAssignedOrLead($c, $actor);

            $at = $this->parseMoment($receivedAt);
            $delivered = trim((string) $deliveredBy);
            if ($delivered === '') {
                $delivered = (string) (User::find($c->issued_by)?->name ?? 'Kho');
            }
            $this->transition($c, 'technician_received', $actor, 'tech_receive', $note, [
                'tech_received_at' => $at,
                'tech_received_by' => $actor->id,
                'tech_delivered_by' => $delivered,
                'tech_receive_note' => $note,
            ]);

            return $c;
        });
    }

    public function startReplacing(int $claimId, User $actor): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['technician_received']);
            $this->requireAssignedOrLead($c, $actor);
            $this->transition($c, 'replacing', $actor, 'start_replacing', null, []);

            return $c;
        });
    }

    public function confirmReplaced(int $claimId, User $actor, ?string $replacedAt, string $result, ?string $note): SolarWarrantyClaim
    {
        if (! in_array($result, ['success', 'issue'], true)) {
            throw new WarrantyException('Kết quả thay thiết bị không hợp lệ.');
        }
        if ($result === 'issue' && mb_strlen(trim((string) $note)) < $this->minReason()) {
            throw new WarrantyException('Kết quả có vấn đề: bắt buộc ghi chú chi tiết.');
        }

        return DB::transaction(function () use ($claimId, $actor, $replacedAt, $result, $note) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['technician_received', 'replacing']);
            $this->requireAssignedOrLead($c, $actor);
            if ($result === 'issue') {
                throw new WarrantyException('Thay thiết bị chưa thành công: hãy liên hệ Trưởng phòng — chưa thể ghi nhận đã thay.');
            }

            $at = $this->parseMoment($replacedAt);
            $old = $this->serialRow((int) $c->serial_unit_id);
            $newId = (int) $c->replacement_serial_unit_id;

            $orig = DB::table('crm_serial_warranties')->where('serial_unit_id', $c->serial_unit_id)->first();
            $siteId = $c->site_id ?: ($orig->site_id ?? null);
            $orderId = $c->order_id ?: ($orig->order_id ?? null);
            $customerId = $c->customer_id ?: ($orig->customer_id ?? null);

            DB::table('warranty_serial_replacements')->insert([
                'claim_id' => $c->id,
                'old_serial_unit_id' => $c->serial_unit_id,
                'old_serial_code' => $c->serial_code,
                'new_serial_unit_id' => $newId,
                'new_serial_code' => $c->replacement_serial_code,
                'product_id' => $old->product_id ?? null,
                'order_id' => $orderId,
                'site_id' => $siteId,
                'customer_id' => $customerId,
                'replaced_at' => $at,
                'technician_id' => $actor->id,
                'created_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncWarranty($c, $orig, $siteId, $orderId, $customerId, $at);

            $next = $c->faulty_return_status === 'returned' ? 'faulty_returned' : 'waiting_faulty_return';
            if ($c->status === 'technician_received' || $c->status === 'replacing') {
                // qua 'replacing' nếu chưa bắt đầu (kỹ thuật bấm xác nhận luôn)
                if ($c->status === 'technician_received') {
                    $this->transition($c, 'replacing', $actor, 'start_replacing', null, []);
                }
            }
            $this->transition($c, $next, $actor, 'confirm_replaced', $note, [
                'replaced_at' => $at,
                'replaced_by' => $actor->id,
                'replace_result' => $result,
                'replace_note' => $note,
                'faulty_return_status' => $c->faulty_return_status ?: 'pending',
            ], null, 'replacement', null);

            $this->serialEvent($c, 'maintenance_replaced', 'Đã thay '.$c->serial_code.' bằng '.$c->replacement_serial_code, (int) $actor->id, $newId);
            $this->notify($c, 'warehouse', 'faulty_return_needed', 'Cần thu hồi thiết bị lỗi', $c->claim_code.' — serial lỗi '.$c->serial_code);

            return $c;
        });
    }

    public function deferFaultyReturn(int $claimId, User $actor, string $reason): SolarWarrantyClaim
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < $this->minReason()) {
            throw new WarrantyException('Bắt buộc nhập lý do hoãn thu hồi.');
        }

        return DB::transaction(function () use ($claimId, $actor, $reason) {
            $c = $this->lock($claimId);
            $this->requireLead($actor);
            $this->requireStatus($c, ['waiting_faulty_return']);
            $fields = ['faulty_return_status' => 'deferred', 'faulty_deferred_reason' => $reason, 'faulty_deferred_by' => $actor->id];
            $before = array_intersect_key($c->getAttributes(), $fields);
            DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update($fields + ['updated_at' => now()]);
            WarrantyAudit::log((int) $c->id, 'faulty_return_deferred', $c->status, $c->status, $before, $fields, $reason, (int) $actor->id);
            $c->refresh();

            return $c;
        });
    }

    // ------------------------------------------------------------------ hoàn tất

    /** @return array<int, array{key:string,label:string,ok:bool,required:bool,detail:string}> */
    public function checklist(object $c): array
    {
        $hasEvidence = SchemaCache::hasTable('solar_warranty_claim_attachments')
            && DB::table('solar_warranty_claim_attachments')->where('warranty_claim_id', $c->id)->whereNull('deleted_at')->exists();
        $pair = SchemaCache::hasTable('warranty_serial_replacements')
            && DB::table('warranty_serial_replacements')->where('claim_id', $c->id)->exists();
        $faultyOk = ($c->faulty_return_status ?? null) === 'returned' || ($c->faulty_return_status ?? null) === 'deferred';
        // Phiếu cũ (trước v2) đã xuất serial thay thế bằng luồng cũ: không có issued_at/tech_received_at.
        $legacy = empty($c->issued_at) && ! empty($c->replacement_serial_unit_id);

        return [
            ['key' => 'approved', 'label' => 'Phiếu đã được duyệt', 'ok' => ! empty($c->approved_by), 'required' => true, 'detail' => ''],
            ['key' => 'exception', 'label' => 'Ngoại lệ bảo hành đã được duyệt', 'ok' => empty($c->warranty_exception) || ! empty($c->exception_approved_by), 'required' => true, 'detail' => ''],
            ['key' => 'serial', 'label' => 'Serial thay thế hợp lệ & đã giữ hàng', 'ok' => ! empty($c->replacement_serial_unit_id), 'required' => true, 'detail' => (string) ($c->replacement_serial_code ?? '')],
            ['key' => 'issued', 'label' => 'Kho đã xuất kho', 'ok' => ! empty($c->issued_at) || $legacy, 'required' => true, 'detail' => ''],
            ['key' => 'received', 'label' => 'Kỹ thuật đã nhận hàng', 'ok' => ! empty($c->tech_received_at) || $legacy, 'required' => true, 'detail' => ''],
            ['key' => 'replaced', 'label' => 'Đã xác nhận thay thiết bị', 'ok' => ! empty($c->replaced_at), 'required' => true, 'detail' => ''],
            ['key' => 'pair', 'label' => 'Đã lưu cặp serial cũ ↔ mới', 'ok' => $pair, 'required' => true, 'detail' => ''],
            ['key' => 'faulty', 'label' => 'Thu hồi thiết bị lỗi (hoặc hoãn có lý do)', 'ok' => $faultyOk, 'required' => true, 'detail' => (string) ($c->faulty_return_status ?? '')],
            ['key' => 'evidence', 'label' => 'Có file minh chứng', 'ok' => $hasEvidence, 'required' => (bool) config('warranty.require_completion_evidence'), 'detail' => ''],
        ];
    }

    public function complete(int $claimId, User $actor, string $resolution, ?float $actualCost = null): SolarWarrantyClaim
    {
        $resolution = trim($resolution);
        if (mb_strlen($resolution) < $this->minReason()) {
            throw new WarrantyException('Bắt buộc nhập kết quả xử lý trước khi hoàn tất.');
        }

        return DB::transaction(function () use ($claimId, $actor, $resolution, $actualCost) {
            $c = $this->lock($claimId);
            $this->requireLead($actor);
            $this->requireStatus($c, ['faulty_returned', 'waiting_faulty_return']); // completed → completed bị chặn ở đây
            foreach ($this->checklist($c) as $item) {
                if ($item['required'] && ! $item['ok']) {
                    throw new WarrantyException('Chưa đủ điều kiện hoàn tất: '.$item['label'].'.');
                }
            }

            $updates = [
                'resolution' => $resolution,
                'resolved_at' => now()->toDateString(),
                'approval_status' => 'approved',
            ];
            if (! $c->closed_by) { // không ghi đè người đóng cũ
                $updates['closed_by'] = $actor->id;
                $updates['closed_at'] = now();
            }
            if ($actualCost !== null && ($actualCost >= 0)
                && (SolarMaintenanceAccess::canViewMaintenanceCosts($actor) || SolarMaintenanceAccess::canOverrideWarranty($actor))) {
                $updates['actual_cost'] = $actualCost;
                $updates['cost'] = $actualCost;
            }
            $updates['completion_snapshot'] = json_encode([
                'claim_code' => $c->claim_code,
                'old_serial' => $c->serial_code,
                'new_serial' => $c->replacement_serial_code,
                'replaced_at' => (string) $c->replaced_at,
                'faulty_return_status' => $c->faulty_return_status,
                'closed_by' => $actor->id,
                'closed_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE);

            $this->transition($c, 'completed', $actor, 'complete', $resolution, $updates);
            $this->notifyUser($c, (int) $c->assigned_to ?: (int) $c->created_by, 'completed', 'Phiếu đổi hàng đã hoàn tất', $c->claim_code);

            return $c;
        });
    }

    // ------------------------------------------------------------------ nội bộ

    private function lock(int $id): SolarWarrantyClaim
    {
        $c = SolarWarrantyClaim::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        if ((string) $c->claim_type !== self::T) {
            throw new WarrantyException('Phiếu không thuộc quy trình đổi hàng bảo hành.');
        }

        return $c;
    }

    private function requireStatus(SolarWarrantyClaim $c, array $allowed): void
    {
        if (! in_array((string) $c->status, $allowed, true)) {
            throw new WarrantyException('Thao tác không hợp lệ ở trạng thái “'.WarrantyFlow::label(self::T, (string) $c->status).'”.');
        }
    }

    private function requireLead(User $actor): void
    {
        if (! SolarMaintenanceAccess::isTechnicalLead($actor)) {
            throw new WarrantyException('Chỉ Trưởng phòng Kỹ thuật/Giám đốc/Admin được thực hiện thao tác này.');
        }
    }

    private function requireWarehouse(User $actor): void
    {
        if (! SolarMaintenanceAccess::canHandleWarrantyStock($actor)) {
            throw new WarrantyException('Chỉ bộ phận Kho được chọn/giữ/xuất/thu hồi serial.');
        }
    }

    private function requireAssignedOrLead(SolarWarrantyClaim $c, User $actor): void
    {
        $ok = SolarMaintenanceAccess::isTechnicalLead($actor)
            || ((int) $c->assigned_to === (int) $actor->id && SolarMaintenanceAccess::isTechnician($actor));
        if (! $ok) {
            throw new WarrantyException('Chỉ Kỹ thuật phụ trách phiếu (hoặc Trưởng phòng) được thực hiện thao tác này.');
        }
    }

    private function requireOwnerOrLead(SolarWarrantyClaim $c, User $actor): void
    {
        $ok = SolarMaintenanceAccess::isTechnicalLead($actor)
            || (int) $c->assigned_to === (int) $actor->id
            || (int) $c->created_by === (int) $actor->id;
        if (! $ok) {
            throw new WarrantyException('Bạn không phải người tạo/phụ trách phiếu này.');
        }
    }

    private function minReason(): int
    {
        return (int) config('warranty.min_reason_length', 5);
    }

    private function parseMoment(?string $v): Carbon
    {
        if (! $v) {
            return now();
        }
        try {
            $d = Carbon::parse($v);
        } catch (\Throwable) {
            throw new WarrantyException('Ngày giờ không hợp lệ.');
        }
        if ($d->isFuture() && $d->diffInMinutes(now()) > 5) {
            throw new WarrantyException('Ngày giờ không được ở tương lai.');
        }

        return $d;
    }

    /**
     * Chuyển trạng thái theo whitelist + ghi audit (từ/đến, trước/sau, lý do, user).
     * Đồng bộ khóa chống trùng serial (open_serial_key).
     */
    private function transition(SolarWarrantyClaim $c, string $to, User $actor, string $action, ?string $reason, array $fields, ?array $before = null, ?string $relType = null, ?int $relId = null): void
    {
        $from = (string) $c->status;
        WarrantyFlow::assertTransition(self::T, $from, $to);

        $before ??= array_intersect_key($c->getAttributes(), $fields) + ['status' => $from];
        $update = $fields + ['status' => $to, 'status_changed_at' => now(), 'updated_at' => now()];
        if (! WarrantyFlow::isOpen($to)) {
            $update['open_serial_key'] = null;
        } elseif (! WarrantyFlow::isOpen($from)) {
            $update['open_serial_key'] = (int) $c->serial_unit_id;
        }

        try {
            DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update($update);
        } catch (QueryException) {
            throw new WarrantyException('Không thể cập nhật: serial đang có phiếu mở khác.');
        }
        $c->refresh();

        $after = array_intersect_key($c->getAttributes(), $fields + ['status' => 1]);
        WarrantyAudit::log((int) $c->id, $action, $from, $to, $before, $after, $reason, (int) $actor->id, $relType, $relId);
    }

    private function releaseActiveReservation(SolarWarrantyClaim $c, User $actor, string $reason): void
    {
        $res = DB::table('warranty_serial_reservations')->where('claim_id', $c->id)->where('status', 'active')->lockForUpdate()->first();
        if (! $res) {
            return;
        }
        $state = $this->ledger->lockState((int) $res->serial_unit_id);
        if ($state && $state->state === 'reserved') {
            $this->ledger->setSerialState((int) $res->serial_unit_id, (string) ($res->previous_state ?: 'in_stock'), (int) $res->warehouse_id, (int) $c->company_id ?: null, 'Nhả hàng phiếu '.$c->claim_code.': '.$reason, (int) $actor->id);
        }
        DB::table('warranty_serial_reservations')->where('id', $res->id)->update([
            'status' => 'released', 'active_key' => null, 'released_by' => $actor->id, 'released_at' => now(),
            'release_reason' => $reason, 'updated_at' => now(),
        ]);
        DB::table('solar_warranty_stock_movements')->where('id', $res->movement_id)->whereIn('status', ['pending', 'approved'])
            ->update(['status' => 'cancelled', 'updated_at' => now()]);
        WarrantyAudit::log((int) $c->id, 'release_reservation_detail', null, null, ['serial' => $res->serial_code], ['released' => true], $reason, (int) $actor->id, 'reservation', (int) $res->id);
    }

    private function syncWarranty(SolarWarrantyClaim $c, ?object $orig, mixed $siteId, mixed $orderId, mixed $customerId, Carbon $at): void
    {
        if (! SchemaCache::hasTable('crm_serial_warranties')) {
            return;
        }
        $data = [
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'site_id' => $siteId,
            'order_item_id' => $orig->order_item_id ?? null,
            'sold_at' => $at->toDateString(),
            'warranty_months' => (int) ($orig->warranty_months ?? 0),
            // Thiết bị thay thế kế thừa thời hạn bảo hành còn lại của thiết bị gốc.
            'warranty_start_at' => $orig->warranty_start_at ?? $at->toDateString(),
            'warranty_end_at' => $orig->warranty_end_at ?? null,
            'status' => 'active',
            'note' => 'Serial thay thế theo phiếu '.$c->claim_code,
            'replaced_serial_unit_id' => $c->serial_unit_id,
            'replacement_claim_id' => $c->id,
            'updated_at' => now(),
        ];
        $exists = DB::table('crm_serial_warranties')->where('serial_unit_id', $c->replacement_serial_unit_id)->exists();
        if (! $exists) {
            $data['created_at'] = now();
        }
        DB::table('crm_serial_warranties')->updateOrInsert(['serial_unit_id' => $c->replacement_serial_unit_id], $data);

        if ($orig) {
            DB::table('crm_serial_warranties')->where('serial_unit_id', $c->serial_unit_id)->update([
                'status' => 'replaced',
                'note' => 'Đã thay bằng serial '.$c->replacement_serial_code.' theo phiếu '.$c->claim_code,
                'updated_at' => now(),
            ]);
        }
    }

    private function assertWarehouseCompany(int $warehouseId, int $companyId): void
    {
        $w = DB::table('crm_warehouses')->where('id', $warehouseId)->first();
        if (! $w) {
            throw new WarrantyException('Kho không tồn tại.');
        }
        if ($companyId <= 0) {
            return;
        }
        $direct = (int) ($w->company_id ?? 0);
        $pivot = SchemaCache::hasTable('company_warehouse')
            ? DB::table('company_warehouse')->where('warehouse_id', $warehouseId)->pluck('company_id')->map(fn ($i) => (int) $i)
            : collect();
        $explicit = $direct > 0 || $pivot->isNotEmpty();
        if ($explicit && $direct !== $companyId && ! $pivot->contains($companyId)) {
            throw new WarrantyException('Kho đã chọn không thuộc công ty của phiếu.');
        }
    }

    private function serialRow(int $serialUnitId): ?object
    {
        return DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', function ($j): void {
                $j->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('su.id', $serialUnitId)
            ->first(['su.id as serial_unit_id', 'su.product_id', 'si.code as serial_code']);
    }

    private function serialByCode(string $code): ?object
    {
        return DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', function ($j): void {
                $j->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('si.code', $code)
            ->first(['su.id as serial_unit_id', 'su.product_id', 'si.code as serial_code']);
    }

    private function serialEvent(SolarWarrantyClaim $c, string $type, string $note, int $userId, ?int $serialUnitId = null): void
    {
        if (! SchemaCache::hasTable('crm_serial_warranty_events')) {
            return;
        }
        $unit = $serialUnitId ?: (int) $c->serial_unit_id;
        $code = $serialUnitId && $serialUnitId !== (int) $c->serial_unit_id ? ($c->replacement_serial_code ?: $c->reserved_serial_code) : $c->serial_code;
        DB::table('crm_serial_warranty_events')->insert([
            'serial_unit_id' => $unit,
            'serial_code' => $code,
            'event_type' => mb_substr($type, 0, 40),
            'customer_id' => $c->customer_id,
            'order_id' => $c->order_id,
            'created_by' => $userId,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function notify(object $c, string $audience, string $type, string $title, ?string $message = null): void
    {
        if (! SchemaCache::hasTable('warranty_claim_notifications')) {
            return;
        }
        DB::table('warranty_claim_notifications')->insert([
            'claim_id' => $c->id, 'user_id' => null, 'audience' => $audience, 'type' => $type,
            'title' => $title, 'message' => $message, 'is_read' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function notifyUser(object $c, ?int $userId, string $type, string $title, ?string $message = null): void
    {
        if (! $userId || ! SchemaCache::hasTable('warranty_claim_notifications')) {
            return;
        }
        DB::table('warranty_claim_notifications')->insert([
            'claim_id' => $c->id, 'user_id' => $userId, 'audience' => null, 'type' => $type,
            'title' => $title, 'message' => $message, 'is_read' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
