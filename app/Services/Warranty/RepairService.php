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
 * Quy trình B — SỬA CHỮA SẢN PHẨM TÍNH PHÍ (claim_type = paid_repair), 10 bước.
 * CHI PHÍ = LINH KIỆN + CÔNG SỬA + PHÍ ONSITE + VẬN CHUYỂN + PHÁT SINH − GIẢM GIÁ (tính server-side).
 */
final class RepairService
{
    private const T = WarrantyFlow::TYPE_REPAIR;

    public function __construct(private readonly StockLedger $ledger, private readonly WarrantyExchangeService $exchange)
    {
    }

    // ------------------------------------------------------------------ tính báo giá (server-side)

    /**
     * @param  array<int, array{product_id?:int|null,name?:string,sku?:string,quantity:mixed,unit_price:mixed}>  $items
     * @return array{items: array<int,array<string,mixed>>, parts_total: string, labor_amount: string, onsite_amount: string, shipping_amount: string, extra_amount: string, discount_amount: string, total_amount: string}
     */
    public static function computeQuotation(array $items, mixed $labor, mixed $onsite, mixed $shipping, mixed $extra, mixed $discount): array
    {
        $rows = [];
        $partsCents = 0;
        foreach ($items as $i => $it) {
            $qty = self::num($it['quantity'] ?? 0, "Số lượng dòng ".($i + 1));
            $price = self::num($it['unit_price'] ?? 0, "Đơn giá dòng ".($i + 1));
            $name = trim((string) ($it['name'] ?? ''));
            if ($qty <= 0) {
                throw new WarrantyException('Số lượng linh kiện dòng '.($i + 1).' phải lớn hơn 0.');
            }
            if ($price < 0) {
                throw new WarrantyException('Đơn giá dòng '.($i + 1).' không được âm.');
            }
            if ($name === '' && empty($it['product_id'])) {
                throw new WarrantyException('Dòng linh kiện '.($i + 1).' cần tên hoặc sản phẩm.');
            }
            $lineCents = (int) round($qty * $price * 100);
            $partsCents += $lineCents;
            $rows[] = [
                'product_id' => $it['product_id'] ?? null,
                'name' => $name,
                'sku' => $it['sku'] ?? null,
                'quantity' => $qty,
                'unit_price' => $price,
                'line_total' => round($lineCents / 100, 2),
            ];
        }

        $laborC = (int) round(self::num($labor, 'Công sửa') * 100);
        $onsiteC = (int) round(self::num($onsite, 'Phí onsite') * 100);
        $shipC = (int) round(self::num($shipping, 'Vận chuyển') * 100);
        $extraC = (int) round(self::num($extra, 'Chi phí phát sinh') * 100);
        $discC = (int) round(self::num($discount, 'Giảm giá') * 100);
        foreach (['Công sửa' => $laborC, 'Phí onsite' => $onsiteC, 'Vận chuyển' => $shipC, 'Chi phí phát sinh' => $extraC, 'Giảm giá' => $discC] as $label => $v) {
            if ($v < 0) {
                throw new WarrantyException($label.' không được âm.');
            }
        }
        $subtotal = $partsCents + $laborC + $onsiteC + $shipC + $extraC;
        if ($discC > $subtotal) {
            throw new WarrantyException('Giảm giá không được lớn hơn tổng chi phí trước giảm.');
        }
        $fmt = fn (int $c): string => number_format($c / 100, 2, '.', '');

        return [
            'items' => $rows,
            'parts_total' => $fmt($partsCents),
            'labor_amount' => $fmt($laborC),
            'onsite_amount' => $fmt($onsiteC),
            'shipping_amount' => $fmt($shipC),
            'extra_amount' => $fmt($extraC),
            'discount_amount' => $fmt($discC),
            'total_amount' => $fmt($subtotal - $discC),
        ];
    }

    private static function num(mixed $v, string $label): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (! is_numeric($v)) {
            throw new WarrantyException($label.' phải là số.');
        }

        return (float) $v;
    }

    // ------------------------------------------------------------------ 1-2. tiếp nhận + kiểm tra

    /** @param array<string,mixed> $ctx serial(object), site_id, order_id, customer_id, company_id, warranty_active, warranty_status, assignee_id/name */
    public function create(User $actor, array $data, array $ctx): SolarWarrantyClaim
    {
        if (! SolarMaintenanceAccess::canCreateWarrantyClaim($actor)) {
            throw new WarrantyException('Bạn không có quyền tiếp nhận phiếu sửa chữa.');
        }
        $serial = $ctx['serial'];
        $inWarranty = (bool) $ctx['warranty_active'];
        $scope = trim((string) ($data['out_of_scope_reason'] ?? ''));
        if ($inWarranty && mb_strlen($scope) < (int) config('warranty.min_reason_length', 5)) {
            throw new WarrantyException('Thiết bị còn bảo hành: bắt buộc nhập lý do lỗi KHÔNG thuộc phạm vi bảo hành mới được tính phí.');
        }
        $eligibility = $inWarranty ? 'in_warranty_out_of_scope' : (($ctx['warranty_status'] ?? '') === '' ? 'no_record' : 'out_of_warranty');

        return DB::transaction(function () use ($actor, $data, $ctx, $serial, $eligibility, $scope): SolarWarrantyClaim {
            $this->exchange->guardNoOpenClaim((int) $serial->serial_unit_id);
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
                    'status' => 'diagnosing',
                    'approval_status' => 'not_submitted',
                    'assigned_to' => $ctx['assignee_id'] ?? null,
                    'assigned_name' => $ctx['assignee_name'] ?? null,
                    'received_at' => now()->toDateString(),
                    'issue_description' => trim((string) $data['issue_description']),
                    'is_chargeable' => true,
                    'internal_note' => $data['internal_note'] ?? null,
                    'created_by' => $actor->id,
                ]);
            } catch (QueryException) {
                throw new WarrantyException('Serial này vừa có phiếu khác được tạo. Vui lòng tải lại trang.');
            }
            DB::table('crm_serial_warranty_claims')->where('id', $claim->id)->update([
                'claim_code' => sprintf('SCTP-%s-%06d', now()->format('Y'), $claim->id),
                'open_serial_key' => (int) $serial->serial_unit_id,
                'status_changed_at' => now(),
                'warranty_eligibility' => $eligibility,
                'out_of_scope_reason' => $scope !== '' ? $scope : null,
            ]);
            $claim->refresh();

            WarrantyAudit::log($claim->id, 'received', null, 'received', null, ['serial' => $claim->serial_code, 'site_id' => $claim->site_id, 'order_id' => $claim->order_id], null, $actor->id);
            WarrantyAudit::log($claim->id, 'eligibility_check', 'received', 'diagnosing', null, ['eligibility' => $eligibility], $scope ?: null, $actor->id);

            return $claim;
        });
    }

    // ------------------------------------------------------------------ 3. chẩn đoán

    public function saveDiagnosis(int $claimId, User $actor, array $d): SolarWarrantyClaim
    {
        $need = ['diagnosis' => 'Tình trạng/chẩn đoán', 'diagnosis_cause' => 'Nguyên nhân lỗi', 'proposed_solution' => 'Phương án sửa'];
        foreach ($need as $k => $label) {
            if (trim((string) ($d[$k] ?? '')) === '') {
                throw new WarrantyException($label.' là bắt buộc.');
            }
        }
        $hours = $d['est_repair_hours'] ?? null;
        if ($hours !== null && $hours !== '' && (! is_numeric($hours) || (float) $hours < 0)) {
            throw new WarrantyException('Thời gian dự kiến không hợp lệ.');
        }

        return DB::transaction(function () use ($claimId, $actor, $d, $hours) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['diagnosing']);
            $this->requireAssignedOrLead($c, $actor);
            $fields = [
                'diagnosis' => trim((string) $d['diagnosis']),
                'diagnosis_cause' => trim((string) $d['diagnosis_cause']),
                'proposed_solution' => trim((string) $d['proposed_solution']),
                'parts_needed' => $d['parts_needed'] ?? null,
                'est_repair_hours' => ($hours === null || $hours === '') ? null : (float) $hours,
                'tech_note' => $d['tech_note'] ?? null,
            ];
            $this->transition($c, 'quotation_draft', $actor, 'diagnosis', null, $fields);

            return $c;
        });
    }

    // ------------------------------------------------------------------ 4. báo giá (có version)

    /**
     * Lưu báo giá. Nếu báo giá hiện tại đã gửi/duyệt/từ chối → tạo VERSION MỚI (không sửa ngầm bản đã duyệt).
     *
     * @param array{items:array,labor_amount?:mixed,onsite_amount?:mixed,shipping_amount?:mixed,extra_amount?:mixed,discount_amount?:mixed,note?:?string} $q
     */
    public function saveQuotation(int $claimId, User $actor, array $q): object
    {
        return DB::transaction(function () use ($claimId, $actor, $q) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['quotation_draft', 'waiting_customer_confirmation', 'quotation_rejected', 'approved_for_repair', 'waiting_parts']);
            $this->requireAssignedOrLead($c, $actor);

            $calc = self::computeQuotation((array) ($q['items'] ?? []), $q['labor_amount'] ?? 0, $q['onsite_amount'] ?? 0, $q['shipping_amount'] ?? 0, $q['extra_amount'] ?? 0, $q['discount_amount'] ?? 0);
            $items = $this->hydrateItems($calc['items']);

            $current = $c->current_quotation_id ? DB::table('warranty_repair_quotations')->where('id', $c->current_quotation_id)->lockForUpdate()->first() : null;
            $revise = $current && $current->status !== 'draft';

            if ($revise) {
                $issued = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('qty_issued', '>', 0)->exists();
                if ($issued) {
                    throw new WarrantyException('Đã xuất linh kiện — không thể lập lại báo giá. Hãy hoàn kho hoặc hủy phiếu.');
                }
                DB::table('warranty_repair_quotations')->where('id', $current->id)->update(['status' => 'superseded', 'updated_at' => now()]);
                $this->releaseAllParts($c, $actor, 'Lập lại báo giá (version mới)');
                DB::table('warranty_repair_parts')->where('claim_id', $c->id)->delete();
                if ($c->status !== 'quotation_draft') {
                    $this->transition($c, 'quotation_draft', $actor, 'quotation_revise', 'Lập lại báo giá — khách phải xác nhận lại', ['current_quotation_id' => $c->current_quotation_id]);
                }
            }

            $version = $revise ? ((int) $current->version + 1) : ($current ? (int) $current->version : 1);
            $row = [
                'claim_id' => $c->id, 'version' => $version, 'status' => 'draft',
                'parts_total' => $calc['parts_total'], 'labor_amount' => $calc['labor_amount'], 'onsite_amount' => $calc['onsite_amount'],
                'shipping_amount' => $calc['shipping_amount'], 'extra_amount' => $calc['extra_amount'], 'discount_amount' => $calc['discount_amount'],
                'total_amount' => $calc['total_amount'], 'note' => $q['note'] ?? null, 'created_by' => $actor->id, 'updated_at' => now(),
            ];
            if ($current && ! $revise) {
                $before = (array) $current;
                DB::table('warranty_repair_quotations')->where('id', $current->id)->update($row);
                DB::table('warranty_repair_quotation_items')->where('quotation_id', $current->id)->delete();
                $qid = (int) $current->id;
                $action = 'quotation_update';
            } else {
                $before = $current ? (array) $current : null;
                $qid = (int) DB::table('warranty_repair_quotations')->insertGetId($row + ['created_at' => now()]);
                $action = $revise ? 'quotation_new_version' : 'quotation_create';
            }
            foreach ($items as $it) {
                DB::table('warranty_repair_quotation_items')->insert($it + ['quotation_id' => $qid, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update([
                'current_quotation_id' => $qid, 'estimated_cost' => $calc['total_amount'], 'updated_at' => now(),
            ]);
            WarrantyAudit::log((int) $c->id, $action, $c->status, $c->status, $before, $calc, null, (int) $actor->id, 'quotation', $qid);

            return DB::table('warranty_repair_quotations')->where('id', $qid)->first();
        });
    }

    public function sendQuotation(int $claimId, User $actor): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['quotation_draft']);
            $this->requireAssignedOrLead($c, $actor);
            $q = $this->currentQuotation($c, true);
            if (! $q || $q->status !== 'draft') {
                throw new WarrantyException('Chưa có báo giá nháp để gửi.');
            }
            if ((float) $q->total_amount <= 0 && ! DB::table('warranty_repair_quotation_items')->where('quotation_id', $q->id)->exists()) {
                throw new WarrantyException('Báo giá rỗng: cần ít nhất một khoản chi phí.');
            }
            DB::table('warranty_repair_quotations')->where('id', $q->id)->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
            $this->transition($c, 'waiting_customer_confirmation', $actor, 'quotation_send', null, [], null, 'quotation', (int) $q->id);

            return $c;
        });
    }

    // ------------------------------------------------------------------ 5. khách xác nhận

    public function customerDecision(int $claimId, User $actor, string $decision, string $method, ?string $decidedAt, ?string $note): SolarWarrantyClaim
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new WarrantyException('Quyết định của khách không hợp lệ.');
        }
        if (! array_key_exists($method, WarrantyFlow::REPAIR_DECISION_METHODS)) {
            throw new WarrantyException('Phương thức xác nhận không hợp lệ.');
        }

        return DB::transaction(function () use ($claimId, $actor, $decision, $method, $decidedAt, $note) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['waiting_customer_confirmation']);
            $this->requireAssignedOrLead($c, $actor);
            $q = $this->currentQuotation($c, true);
            if (! $q || $q->status !== 'sent') {
                throw new WarrantyException('Không có báo giá đang chờ khách xác nhận.');
            }
            $at = $this->parseMoment($decidedAt);
            DB::table('warranty_repair_quotations')->where('id', $q->id)->update([
                'status' => $decision, 'decision' => $decision, 'decision_method' => $method, 'decision_at' => $at,
                'decision_by' => $actor->id, 'decision_note' => $note,
                'locked_at' => $decision === 'approved' ? now() : null, 'updated_at' => now(),
            ]);

            if ($decision === 'approved') {
                foreach (DB::table('warranty_repair_quotation_items')->where('quotation_id', $q->id)->whereNotNull('product_id')->get() as $it) {
                    $exists = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('product_id', $it->product_id)->first();
                    if ($exists) {
                        DB::table('warranty_repair_parts')->where('id', $exists->id)->update(['qty_planned' => (float) $exists->qty_planned + (float) $it->quantity, 'updated_at' => now()]);
                    } else {
                        DB::table('warranty_repair_parts')->insert([
                            'claim_id' => $c->id, 'product_id' => $it->product_id, 'name' => $it->name, 'qty_planned' => $it->quantity,
                            'status' => 'planned', 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
                $this->transition($c, 'approved_for_repair', $actor, 'customer_approved', $note, ['approval_status' => 'approved'], null, 'quotation', (int) $q->id);
                if (DB::table('warranty_repair_parts')->where('claim_id', $c->id)->exists()) {
                    $this->exchange->notify($c, 'warehouse', 'parts_needed', 'Việc mới cho Kho: chuẩn bị linh kiện sửa chữa', $c->claim_code);
                }
            } else {
                $this->transition($c, 'quotation_rejected', $actor, 'customer_rejected', $note, [], null, 'quotation', (int) $q->id);
            }

            return $c;
        });
    }

    // ------------------------------------------------------------------ 6. linh kiện (Kho)

    public function reserveParts(int $claimId, User $actor, int $warehouseId): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor, $warehouseId) {
            $c = $this->lock($claimId);
            $this->requireWarehouse($actor);
            $this->requireStatus($c, ['approved_for_repair', 'waiting_parts']);
            if (! DB::table('crm_warehouses')->where('id', $warehouseId)->exists()) {
                throw new WarrantyException('Kho không tồn tại.');
            }
            $parts = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('status', 'planned')->lockForUpdate()->get();
            if ($parts->isEmpty()) {
                throw new WarrantyException('Không có linh kiện cần giữ hàng.');
            }
            foreach ($parts as $p) {
                $avail = $this->ledger->availablePartQty((int) $p->product_id, $warehouseId);
                if ($avail + 0.0001 < (float) $p->qty_planned) {
                    throw new WarrantyException('Tồn khả dụng không đủ cho “'.$p->name.'” (cần '.(float) $p->qty_planned.', khả dụng '.(float) $avail.').');
                }
            }
            foreach ($parts as $p) {
                DB::table('warranty_repair_parts')->where('id', $p->id)->update([
                    'status' => 'reserved', 'warehouse_id' => $warehouseId, 'qty_reserved' => $p->qty_planned,
                    'reserved_by' => $actor->id, 'reserved_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($c->status === 'approved_for_repair') {
                $this->transition($c, 'waiting_parts', $actor, 'parts_reserve', null, []);
            } else {
                WarrantyAudit::log((int) $c->id, 'parts_reserve', $c->status, $c->status, null, ['warehouse_id' => $warehouseId], null, (int) $actor->id);
            }

            return $c;
        });
    }

    public function issueParts(int $claimId, User $actor, ?int $receiverUserId = null): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor, $receiverUserId) {
            $c = $this->lock($claimId);
            $this->requireWarehouse($actor);
            $this->requireStatus($c, ['waiting_parts']);
            $parts = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('status', 'reserved')->lockForUpdate()->get();
            if ($parts->isEmpty()) {
                throw new WarrantyException('Không có linh kiện đã giữ để xuất.');
            }
            $receiver = $receiverUserId ?: ((int) $c->assigned_to ?: null);
            foreach ($parts as $p) {
                [$before, $after] = $this->ledger->adjustProductStock((int) $p->product_id, (int) $p->warehouse_id, (int) $c->company_id ?: null, -(float) $p->qty_reserved, 'Xuất linh kiện sửa chữa '.$c->claim_code, (int) $c->id, (int) $actor->id);
                DB::table('warranty_repair_parts')->where('id', $p->id)->update([
                    'status' => 'issued', 'qty_issued' => $p->qty_reserved, 'qty_reserved' => 0,
                    'issued_by' => $actor->id, 'issued_at' => now(), 'received_by' => $receiver, 'updated_at' => now(),
                ]);
                $mid = (int) DB::table('solar_warranty_stock_movements')->insertGetId([
                    'movement_code' => 'TMP-'.uniqid(), 'company_id' => $c->company_id, 'warranty_claim_id' => $c->id, 'site_id' => $c->site_id,
                    'movement_type' => 'repair_part_out', 'status' => 'completed', 'warehouse_id' => $p->warehouse_id, 'product_id' => $p->product_id,
                    'quantity' => $p->qty_reserved, 'requested_by' => $actor->id, 'approved_by' => $actor->id, 'completed_by' => $actor->id,
                    'requested_at' => now(), 'approved_at' => now(), 'completed_at' => now(), 'part_line_id' => $p->id,
                    'qty_before' => $before, 'qty_after' => $after, 'note' => 'Xuất linh kiện '.$p->name,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('solar_warranty_stock_movements')->where('id', $mid)->update(['movement_code' => sprintf('XKBH-%s-%06d', now()->format('Y'), $mid)]);
            }
            WarrantyAudit::log((int) $c->id, 'parts_issue', $c->status, $c->status, null, ['lines' => $parts->count(), 'receiver' => $receiver], null, (int) $actor->id);

            return $c;
        });
    }

    /** Hoàn kho linh kiện dư (issued − used − returned). */
    public function returnLeftoverParts(int $claimId, User $actor): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor) {
            $c = $this->lock($claimId);
            $this->requireWarehouse($actor);
            $this->requireStatus($c, ['repairing', 'qa_testing', 'qa_failed', 'ready_handover', 'handed_over', 'waiting_parts', 'approved_for_repair']);
            $moved = 0;
            foreach (DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('status', 'issued')->lockForUpdate()->get() as $p) {
                $left = (float) $p->qty_issued - (float) $p->qty_used - (float) $p->qty_returned;
                if ($left <= 0) {
                    DB::table('warranty_repair_parts')->where('id', $p->id)->update(['status' => 'closed', 'updated_at' => now()]);
                    continue;
                }
                $this->ledger->adjustProductStock((int) $p->product_id, (int) $p->warehouse_id, (int) $c->company_id ?: null, $left, 'Hoàn kho linh kiện dư '.$c->claim_code, (int) $c->id, (int) $actor->id);
                DB::table('warranty_repair_parts')->where('id', $p->id)->update([
                    'qty_returned' => (float) $p->qty_returned + $left, 'status' => 'closed', 'updated_at' => now(),
                ]);
                $moved++;
            }
            if (! $moved) {
                throw new WarrantyException('Không có linh kiện dư cần hoàn kho.');
            }
            WarrantyAudit::log((int) $c->id, 'parts_return', $c->status, $c->status, null, ['lines' => $moved], null, (int) $actor->id);

            return $c;
        });
    }

    // ------------------------------------------------------------------ 7. sửa chữa

    public function startRepair(int $claimId, User $actor): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['approved_for_repair', 'waiting_parts', 'qa_failed']);
            $this->requireAssignedOrLead($c, $actor);
            if ($c->status !== 'qa_failed') {
                $pending = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->whereNotIn('status', ['issued', 'closed'])->exists();
                if ($pending) {
                    throw new WarrantyException('Chưa thể sửa: linh kiện chưa được Kho giữ/xuất đầy đủ.');
                }
            }
            $fields = $c->repair_started_at ? [] : ['repair_started_at' => now(), 'repair_started_by' => $actor->id];
            $this->transition($c, 'repairing', $actor, $c->status === 'qa_failed' ? 'repair_restart' : 'repair_start', null, $fields);

            return $c;
        });
    }

    /** @param array<int|string,mixed> $partsUsed product_id => qty_used */
    public function updateRepair(int $claimId, User $actor, array $d, array $partsUsed = []): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor, $d, $partsUsed) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['repairing']);
            $this->requireAssignedOrLead($c, $actor);
            $hours = $d['repair_hours_actual'] ?? null;
            if ($hours !== null && $hours !== '' && (! is_numeric($hours) || (float) $hours < 0)) {
                throw new WarrantyException('Thời gian sửa không hợp lệ.');
            }
            foreach ($partsUsed as $pid => $qty) {
                if (! is_numeric($qty) || (float) $qty < 0) {
                    throw new WarrantyException('Số lượng linh kiện đã dùng không hợp lệ.');
                }
                $p = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('product_id', (int) $pid)->lockForUpdate()->first();
                if (! $p) {
                    throw new WarrantyException('Linh kiện không thuộc phiếu.');
                }
                if ((float) $qty + (float) $p->qty_returned > (float) $p->qty_issued + 0.0001) {
                    throw new WarrantyException('Số lượng dùng của “'.$p->name.'” vượt quá số Kho đã xuất.');
                }
                DB::table('warranty_repair_parts')->where('id', $p->id)->update(['qty_used' => (float) $qty, 'updated_at' => now()]);
            }
            $fields = [
                'repair_work_done' => $d['repair_work_done'] ?? $c->repair_work_done,
                'repair_hours_actual' => ($hours === null || $hours === '') ? $c->repair_hours_actual : (float) $hours,
                'repair_issues' => $d['repair_issues'] ?? $c->repair_issues,
            ];
            $before = array_intersect_key($c->getAttributes(), $fields);
            DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update($fields + ['updated_at' => now()]);
            WarrantyAudit::log((int) $c->id, 'repair_update', $c->status, $c->status, $before, $fields + ['parts_used' => $partsUsed], null, (int) $actor->id);
            $c->refresh();

            return $c;
        });
    }

    // ------------------------------------------------------------------ 8. QA

    public function submitToQa(int $claimId, User $actor): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['repairing']);
            $this->requireAssignedOrLead($c, $actor);
            if (trim((string) $c->repair_work_done) === '') {
                throw new WarrantyException('Cập nhật nội dung đã sửa trước khi chuyển sang kiểm tra.');
            }
            $this->transition($c, 'qa_testing', $actor, 'qa_submit', null, []);

            return $c;
        });
    }

    public function recordQa(int $claimId, User $actor, string $result, ?string $measurements, ?string $note): SolarWarrantyClaim
    {
        if (! in_array($result, ['pass', 'fail'], true)) {
            throw new WarrantyException('Kết quả kiểm tra không hợp lệ.');
        }
        if ($result === 'fail' && mb_strlen(trim((string) $note)) < (int) config('warranty.min_reason_length', 5)) {
            throw new WarrantyException('Kiểm tra KHÔNG đạt: bắt buộc ghi chú lý do.');
        }

        return DB::transaction(function () use ($claimId, $actor, $result, $measurements, $note) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['qa_testing']);
            $this->requireAssignedOrLead($c, $actor);
            $qaId = (int) DB::table('warranty_repair_qa')->insertGetId([
                'claim_id' => $c->id, 'result' => $result, 'measurements' => $measurements, 'note' => $note,
                'tested_by' => $actor->id, 'tested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->transition($c, $result === 'pass' ? 'ready_handover' : 'qa_failed', $actor, $result === 'pass' ? 'qa_pass' : 'qa_fail', $note, [], null, 'qa', $qaId);

            return $c;
        });
    }

    // ------------------------------------------------------------------ 9. bàn giao

    public function handover(int $claimId, User $actor, array $d): SolarWarrantyClaim
    {
        foreach (['handover_receiver_name' => 'Người nhận', 'handover_condition' => 'Tình trạng thiết bị', 'handover_result' => 'Kết quả cuối'] as $k => $label) {
            if (trim((string) ($d[$k] ?? '')) === '') {
                throw new WarrantyException($label.' là bắt buộc.');
            }
        }

        return DB::transaction(function () use ($claimId, $actor, $d) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['ready_handover']);
            $this->requireAssignedOrLead($c, $actor);
            $at = $this->parseMoment($d['handed_over_at'] ?? null);
            $this->transition($c, 'handed_over', $actor, 'handover', $d['handover_note'] ?? null, [
                'handed_over_at' => $at, 'handed_over_by' => $actor->id,
                'handover_receiver_name' => trim((string) $d['handover_receiver_name']),
                'handover_condition' => trim((string) $d['handover_condition']),
                'handover_result' => trim((string) $d['handover_result']),
                'handover_guidance' => $d['handover_guidance'] ?? null,
                'handover_note' => $d['handover_note'] ?? null,
            ]);

            return $c;
        });
    }

    // ------------------------------------------------------------------ 10. hoàn tất / hủy

    /** @return array<int, array{key:string,label:string,ok:bool,required:bool,detail:string}> */
    public function checklist(object $c): array
    {
        $q = $c->current_quotation_id ? DB::table('warranty_repair_quotations')->where('id', $c->current_quotation_id)->first() : null;
        $unsettled = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('status', 'issued')->exists();
        $qaPass = DB::table('warranty_repair_qa')->where('claim_id', $c->id)->orderByDesc('id')->value('result') === 'pass';

        return [
            ['key' => 'quotation', 'label' => 'Báo giá đã được khách đồng ý', 'ok' => $q && $q->status === 'approved', 'required' => true, 'detail' => $q ? 'v'.$q->version : ''],
            ['key' => 'repair', 'label' => 'Đã sửa chữa & ghi nhận nội dung', 'ok' => trim((string) $c->repair_work_done) !== '', 'required' => true, 'detail' => ''],
            ['key' => 'parts', 'label' => 'Linh kiện đã quyết toán (dùng/hoàn kho)', 'ok' => ! $unsettled, 'required' => true, 'detail' => ''],
            ['key' => 'qa', 'label' => 'Kiểm tra sau sửa đạt', 'ok' => $qaPass, 'required' => true, 'detail' => ''],
            ['key' => 'handover', 'label' => 'Đã bàn giao khách hàng', 'ok' => ! empty($c->handed_over_at), 'required' => true, 'detail' => ''],
        ];
    }

    public function complete(int $claimId, User $actor): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor) {
            $c = $this->lock($claimId);
            if (! SolarMaintenanceAccess::isTechnicalLead($actor)) {
                throw new WarrantyException('Chỉ Trưởng phòng/Admin được hoàn tất phiếu.');
            }
            $this->requireStatus($c, ['handed_over']); // completed → completed bị chặn
            foreach ($this->checklist($c) as $it) {
                if ($it['required'] && ! $it['ok']) {
                    throw new WarrantyException('Chưa đủ điều kiện hoàn tất: '.$it['label'].'.');
                }
            }
            $q = DB::table('warranty_repair_quotations')->where('id', $c->current_quotation_id)->lockForUpdate()->first();
            $items = DB::table('warranty_repair_quotation_items')->where('quotation_id', $q->id)->get();
            $parts = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->get(['product_id', 'name', 'qty_issued', 'qty_used', 'qty_returned']);
            $snapshot = [
                'claim_code' => $c->claim_code, 'serial' => $c->serial_code,
                'quotation' => (array) $q, 'quotation_items' => $items->map(fn ($i) => (array) $i)->all(),
                'parts_actual' => $parts->map(fn ($p) => (array) $p)->all(),
                'final_cost' => (string) $q->total_amount, 'closed_by' => $actor->id, 'closed_at' => now()->toDateTimeString(),
            ];
            $updates = [
                'final_cost' => $q->total_amount, 'actual_cost' => $q->total_amount, 'cost' => $q->total_amount,
                'resolved_at' => now()->toDateString(),
                'completion_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            ];
            if (! $c->closed_by) { // không ghi đè người đóng
                $updates['closed_by'] = $actor->id;
                $updates['closed_at'] = now();
            }
            DB::table('warranty_repair_quotations')->where('id', $q->id)->update(['locked_at' => $q->locked_at ?: now(), 'updated_at' => now()]);
            $this->transition($c, 'completed', $actor, 'complete', null, $updates);

            return $c;
        });
    }

    public function cancel(int $claimId, User $actor, string $reason): SolarWarrantyClaim
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < (int) config('warranty.min_reason_length', 5)) {
            throw new WarrantyException('Bắt buộc nhập lý do hủy phiếu.');
        }

        return DB::transaction(function () use ($claimId, $actor, $reason) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['diagnosing', 'quotation_draft', 'waiting_customer_confirmation', 'quotation_rejected', 'approved_for_repair', 'waiting_parts']);
            if (! SolarMaintenanceAccess::isTechnicalLead($actor) && (int) $c->created_by !== (int) $actor->id) {
                throw new WarrantyException('Chỉ Trưởng phòng/Admin (hoặc người tạo) được hủy phiếu.');
            }
            if (DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('status', 'issued')->exists()) {
                throw new WarrantyException('Linh kiện đã xuất kho: Kho phải hoàn kho trước khi hủy phiếu.');
            }
            $this->releaseAllParts($c, $actor, 'Hủy phiếu: '.$reason);
            $this->transition($c, 'cancelled', $actor, 'cancel', $reason, []);

            return $c;
        });
    }

    // ------------------------------------------------------------------ nội bộ

    private function lock(int $id): SolarWarrantyClaim
    {
        $c = SolarWarrantyClaim::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        if ((string) $c->claim_type !== self::T) {
            throw new WarrantyException('Phiếu không thuộc quy trình sửa chữa tính phí.');
        }

        return $c;
    }

    private function requireStatus(SolarWarrantyClaim $c, array $allowed): void
    {
        if (! in_array((string) $c->status, $allowed, true)) {
            throw new WarrantyException('Thao tác không hợp lệ ở trạng thái “'.WarrantyFlow::label(self::T, (string) $c->status).'”.');
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

    private function requireWarehouse(User $actor): void
    {
        if (! SolarMaintenanceAccess::canHandleWarrantyStock($actor)) {
            throw new WarrantyException('Chỉ bộ phận Kho được giữ/xuất/hoàn linh kiện.');
        }
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

    private function currentQuotation(SolarWarrantyClaim $c, bool $lock = false): ?object
    {
        if (! $c->current_quotation_id) {
            return null;
        }
        $q = DB::table('warranty_repair_quotations')->where('id', $c->current_quotation_id);

        return ($lock ? $q->lockForUpdate() : $q)->first();
    }

    /** Gắn tên/SKU từ danh mục sản phẩm khi có product_id. */
    private function hydrateItems(array $items): array
    {
        foreach ($items as &$it) {
            if (! empty($it['product_id'])) {
                $p = DB::table('crm_product_catalog')->where('id', (int) $it['product_id'])->first(['id', 'name', 'sku']);
                if (! $p) {
                    throw new WarrantyException('Sản phẩm/linh kiện không tồn tại trong danh mục.');
                }
                $it['name'] = $it['name'] !== '' ? $it['name'] : (string) $p->name;
                $it['sku'] = $p->sku;
            }
        }
        unset($it);

        return $items;
    }

    private function releaseAllParts(SolarWarrantyClaim $c, User $actor, string $reason): void
    {
        $n = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('status', 'reserved')->update([
            'status' => 'released', 'qty_reserved' => 0, 'updated_at' => now(),
        ]);
        if ($n) {
            WarrantyAudit::log((int) $c->id, 'parts_release', null, null, null, ['lines' => $n], $reason, (int) $actor->id);
        }
    }

    private function transition(SolarWarrantyClaim $c, string $to, User $actor, string $action, ?string $reason, array $fields, ?array $before = null, ?string $relType = null, ?int $relId = null): void
    {
        $from = (string) $c->status;
        WarrantyFlow::assertTransition(self::T, $from, $to);
        $before ??= array_intersect_key($c->getAttributes(), $fields) + ['status' => $from];
        $update = $fields + ['status' => $to, 'status_changed_at' => now(), 'updated_at' => now()];
        if (! WarrantyFlow::isOpen($to)) {
            $update['open_serial_key'] = null;
        }
        DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update($update);
        $c->refresh();
        WarrantyAudit::log((int) $c->id, $action, $from, $to, $before, array_intersect_key($c->getAttributes(), $fields + ['status' => 1]), $reason, (int) $actor->id, $relType, $relId);
    }
}
