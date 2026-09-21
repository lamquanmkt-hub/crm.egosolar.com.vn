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

    /**
     * TIẾP NHẬN SỬA CHỮA — khách hàng + thiết bị + lỗi là dữ liệu chính.
     * KHÔNG yêu cầu Đơn hàng / Công trình / serial tồn tại trong CRM.
     *
     * @param array<string,mixed> $data device_type, device_brand, device_model, serial_code?, device_accessories, received_condition, received_at,
     *                                   delivered_by, issue_description, priority, internal_note, out_of_scope_reason
     * @param array<string,mixed> $ctx  customer{id,name,phone,email,address,company}, serial_info?(SerialLookup), company_id, assignee_id, assignee_name
     */
    public function create(User $actor, array $data, array $ctx): SolarWarrantyClaim
    {
        if (! SolarMaintenanceAccess::canCreateWarrantyClaim($actor)) {
            throw new WarrantyException('Bạn không có quyền tiếp nhận phiếu sửa chữa.');
        }
        $info = $ctx['serial_info'] ?? null;
        $cust = $ctx['customer'];
        $serialText = trim((string) ($data['serial_code'] ?? ''));
        $scope = trim((string) ($data['out_of_scope_reason'] ?? ''));

        if ($info && $info['warranty_active'] && mb_strlen($scope) < (int) config('warranty.min_reason_length', 5)) {
            throw new WarrantyException('Thiết bị còn bảo hành: bắt buộc nhập lý do lỗi KHÔNG thuộc phạm vi bảo hành mới được tính phí.');
        }
        $override = ! empty($data['duplicate_override']);
        $overrideReason = trim((string) ($data['duplicate_override_reason'] ?? ''));
        if ($override) {
            if (! SolarMaintenanceAccess::canOverrideWarranty($actor)) {
                throw new WarrantyException('Chỉ người có quyền override được tạo phiếu sửa chữa trùng serial đang mở.');
            }
            if (mb_strlen($overrideReason) < (int) config('warranty.min_reason_length', 5)) {
                throw new WarrantyException('Tạo phiếu trùng serial (override): bắt buộc nhập lý do.');
            }
        }
        $eligibility = match (true) {
            ! $info => 'external_device',
            (bool) $info['warranty_active'] => 'in_warranty_out_of_scope',
            $info['warranty_status'] === '' => 'no_record',
            default => 'out_of_warranty',
        };

        return DB::transaction(function () use ($actor, $data, $ctx, $info, $cust, $serialText, $scope, $eligibility, $override, $overrideReason): SolarWarrantyClaim {
            $normSource = (string) ($info['serial_code'] ?? $serialText);
            if ($override) {
                // phiếu trùng có override: không giữ khóa mở, lưu lý do + người override (audit)
            } elseif (WarrantyFlow::normalizeSerial($normSource) !== null) {
                $this->exchange->guardNoOpenClaim((int) ($info['serial_unit_id'] ?? 0), null, $normSource);
            }
            try {
                $claim = SolarWarrantyClaim::create([
                    'company_id' => $ctx['company_id'] ?: null,
                    'site_id' => $info['site_id'] ?? null,          // chỉ THAM CHIẾU nếu CRM biết
                    'order_id' => $info['order_id'] ?? null,        // chỉ THAM CHIẾU nếu CRM biết
                    'serial_unit_id' => $info['serial_unit_id'] ?? null,
                    'serial_code' => $info['serial_code'] ?? ($serialText !== '' ? $serialText : null),
                    'customer_id' => $cust['id'] ?? null,
                    'claim_type' => self::T,
                    'priority' => $data['priority'],
                    'status' => 'diagnosing',
                    'approval_status' => 'not_submitted',
                    'assigned_to' => $ctx['assignee_id'] ?? null,
                    'assigned_name' => $ctx['assignee_name'] ?? null,
                    'received_at' => $data['received_at'] ?? now()->toDateString(),
                    'issue_description' => trim((string) $data['issue_description']),
                    'is_chargeable' => true,
                    'internal_note' => $data['internal_note'] ?? null,
                    'created_by' => $actor->id,
                ]);
            } catch (QueryException) {
                throw new WarrantyException('Serial này vừa có phiếu khác được tạo. Vui lòng tải lại.');
            }

            try {
            DB::table('crm_serial_warranty_claims')->where('id', $claim->id)->update([
                'claim_code' => sprintf('SCTP-%s-%06d', now()->format('Y'), $claim->id),
                'open_serial_key' => $override ? null : ($info['serial_unit_id'] ?? null),
                'open_serial_norm' => $override ? null : WarrantyFlow::normalizeSerial($claim->serial_code),
                'duplicate_override_reason' => $override ? $overrideReason : null,
                'duplicate_override_by' => $override ? $actor->id : null,
                'status_changed_at' => now(),
                'warranty_eligibility' => $eligibility,
                'out_of_scope_reason' => $scope !== '' ? $scope : null,
                'customer_name' => $cust['name'], 'customer_phone' => $cust['phone'], 'customer_email' => $cust['email'] ?? null,
                'customer_address' => $cust['address'] ?? null, 'customer_company' => $cust['company'] ?? null,
                'device_type' => $data['device_type'], 'device_brand' => $data['device_brand'] ?? null, 'device_model' => $data['device_model'],
                'device_accessories' => $data['device_accessories'] ?? null, 'received_condition' => $data['received_condition'] ?? null,
                'delivered_by' => $data['delivered_by'] ?? null, 'received_by' => $data['received_by'] ?? $actor->id,
            ]);
            } catch (QueryException) {
                throw new WarrantyException('Serial này vừa có phiếu khác được tạo cùng lúc. Vui lòng tải lại.');
            }
            $claim->refresh();

            if ($override) {
                WarrantyAudit::log($claim->id, 'duplicate_override', null, null, null, ['serial' => $claim->serial_code], $overrideReason, $actor->id);
            }
            WarrantyAudit::log($claim->id, 'received', null, 'diagnosing', null, [
                'customer' => $cust['name'], 'phone' => $cust['phone'], 'device' => trim($data['device_type'].' '.($data['device_brand'] ?? '').' '.$data['device_model']),
                'serial' => $claim->serial_code, 'eligibility' => $eligibility,
            ], $scope ?: null, $actor->id);

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
                'diagnosis_conclusion' => $d['diagnosis_conclusion'] ?? null,
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

    // ------------------------------------------------------------------ 5. khách xác nhận (báo giá gốc HOẶC báo giá phát sinh)

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
            $this->requireStatus($c, ['waiting_customer_confirmation', 'waiting_change_confirmation']);
            $this->requireAssignedOrLead($c, $actor);
            $isChange = $c->status === 'waiting_change_confirmation';
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

            if (! $isChange) {
                if ($decision === 'approved') {
                    $this->syncPartsFromQuotation($c, $q);
                    $this->transition($c, 'approved_for_repair', $actor, 'customer_approved', $note, ['approval_status' => 'approved'], null, 'quotation', (int) $q->id);
                    if ($this->partsPending($c)) {
                        $this->exchange->notify($c, 'warehouse', 'parts_needed', 'Việc mới cho Kho: chuẩn bị linh kiện sửa chữa', $c->claim_code);
                    }
                } else {
                    $this->transition($c, 'quotation_rejected', $actor, 'customer_rejected', $note, [], null, 'quotation', (int) $q->id);
                }

                return $c;
            }

            // ---- báo giá PHÁT SINH khi đang sửa
            $base = DB::table('warranty_repair_quotations')->where('id', $q->base_quotation_id)->lockForUpdate()->first();
            if ($decision === 'approved') {
                if ($base) {
                    DB::table('warranty_repair_quotations')->where('id', $base->id)->update(['status' => 'superseded', 'updated_at' => now()]);
                }
                $this->syncPartsFromQuotation($c, $q);
                $next = $this->partsPending($c) ? 'waiting_parts' : 'repairing';
                $this->transition($c, $next, $actor, 'change_approved', $note, ['approval_status' => 'approved'], null, 'quotation', (int) $q->id);
                if ($next === 'waiting_parts') {
                    $this->exchange->notify($c, 'warehouse', 'parts_needed', 'Phát sinh: Kho xuất bổ sung linh kiện', $c->claim_code);
                }
            } else {
                // khách từ chối: V1 vẫn là báo giá hiệu lực, V2 giữ lại lịch sử
                $this->transition($c, 'change_rejected', $actor, 'change_rejected', $note, ['current_quotation_id' => $base?->id ?? $c->current_quotation_id], null, 'quotation', (int) $q->id);
            }

            return $c;
        });
    }

    /**
     * BÁO GIÁ PHÁT SINH khi đang sửa: tạo VERSION MỚI (V1 đã duyệt giữ nguyên bất biến), lý do bắt buộc,
     * claim chuyển "Chờ khách xác nhận phát sinh". Phần phát sinh KHÔNG được coi là đã duyệt cho tới khi khách xác nhận.
     */
    public function saveChangeQuotation(int $claimId, User $actor, array $q, string $reason): object
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < (int) config('warranty.min_reason_length', 5)) {
            throw new WarrantyException('Bắt buộc nhập lý do phát sinh.');
        }

        return DB::transaction(function () use ($claimId, $actor, $q, $reason) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['repairing']);
            $this->requireAssignedOrLead($c, $actor);
            $base = DB::table('warranty_repair_quotations')->where('claim_id', $c->id)->where('status', 'approved')->orderByDesc('version')->lockForUpdate()->first();
            if (! $base) {
                throw new WarrantyException('Chưa có báo giá đã được khách duyệt để lập phát sinh.');
            }
            $calc = self::computeQuotation((array) ($q['items'] ?? []), $q['labor_amount'] ?? 0, $q['onsite_amount'] ?? 0, $q['shipping_amount'] ?? 0, $q['extra_amount'] ?? 0, $q['discount_amount'] ?? 0);
            $items = $this->hydrateItems($calc['items']);
            $version = (int) DB::table('warranty_repair_quotations')->where('claim_id', $c->id)->max('version') + 1;
            $qid = (int) DB::table('warranty_repair_quotations')->insertGetId([
                'claim_id' => $c->id, 'version' => $version, 'status' => 'sent', 'is_change' => true, 'change_reason' => $reason, 'base_quotation_id' => $base->id,
                'parts_total' => $calc['parts_total'], 'labor_amount' => $calc['labor_amount'], 'onsite_amount' => $calc['onsite_amount'],
                'shipping_amount' => $calc['shipping_amount'], 'extra_amount' => $calc['extra_amount'], 'discount_amount' => $calc['discount_amount'],
                'total_amount' => $calc['total_amount'], 'note' => $q['note'] ?? null, 'created_by' => $actor->id, 'sent_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($items as $it) {
                DB::table('warranty_repair_quotation_items')->insert($it + ['quotation_id' => $qid, 'created_at' => now(), 'updated_at' => now()]);
            }
            $delta = round((float) $calc['total_amount'] - (float) $base->total_amount, 2);
            $this->transition($c, 'waiting_change_confirmation', $actor, 'change_quote_send', $reason, ['current_quotation_id' => $qid], null, 'quotation', $qid);
            WarrantyAudit::log((int) $c->id, 'change_quote_delta', 'repairing', 'waiting_change_confirmation',
                ['version' => (int) $base->version, 'total' => (string) $base->total_amount],
                ['version' => $version, 'total' => $calc['total_amount'], 'delta' => number_format($delta, 2, '.', '')], $reason, (int) $actor->id, 'quotation', $qid);

            return DB::table('warranty_repair_quotations')->where('id', $qid)->first();
        });
    }

    /** Khách từ chối phát sinh → Kỹ thuật/Trưởng phòng quyết định tiếp tục theo báo giá gốc. */
    public function resumeOriginal(int $claimId, User $actor, ?string $note): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor, $note) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['change_rejected']);
            $this->requireAssignedOrLead($c, $actor);
            $this->transition($c, 'repairing', $actor, 'change_resume_original', $note, []);

            return $c;
        });
    }

    // ------------------------------------------------------------------ 6. linh kiện (Kho) — số lượng tích lũy

    /** planned = SL cần (theo báo giá đã duyệt); reserved = đang giữ chưa xuất; issued = đã xuất (cộng dồn); used = đã dùng; returned = đã hoàn. */
    private function partPending(object $p): float
    {
        return max(0.0, (float) $p->qty_planned - (float) $p->qty_issued - (float) $p->qty_reserved);
    }

    private function partLeftover(object $p): float
    {
        return max(0.0, (float) $p->qty_issued - (float) $p->qty_used - (float) $p->qty_returned);
    }

    private function partStatus(object $p): string
    {
        if ((float) $p->qty_reserved > 0.0001) {
            return 'reserved';
        }
        if ($this->partPending($p) > 0.0001) {
            return 'planned';
        }
        if ((float) $p->qty_issued > 0.0001) {
            return $this->partLeftover($p) > 0.0001 ? 'issued' : 'closed';
        }

        return 'released';
    }

    private function refreshPart(int $id): void
    {
        $p = DB::table('warranty_repair_parts')->where('id', $id)->first();
        if ($p) {
            DB::table('warranty_repair_parts')->where('id', $id)->update(['status' => $this->partStatus($p), 'updated_at' => now()]);
        }
    }

    private function partsPending(object $c): bool
    {
        return DB::table('warranty_repair_parts')->where('claim_id', $c->id)->get()
            ->contains(fn ($p) => $this->partPending($p) > 0.0001 || (float) $p->qty_reserved > 0.0001);
    }

    /** Đồng bộ dòng linh kiện theo báo giá ĐÃ DUYỆT (V1 hoặc phiên bản phát sinh): thêm/tăng/giảm SL cần, không đụng SL đã xuất. */
    private function syncPartsFromQuotation(SolarWarrantyClaim $c, object $q): void
    {
        $groups = [];
        foreach (DB::table('warranty_repair_quotation_items')->where('quotation_id', $q->id)->whereNotNull('product_id')->get() as $it) {
            $g = $groups[$it->product_id] ?? ['name' => $it->name, 'qty' => 0.0, 'total' => 0.0];
            $g['qty'] += (float) $it->quantity;
            $g['total'] += (float) $it->line_total;
            $groups[$it->product_id] = $g;
        }
        $existing = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->lockForUpdate()->get()->keyBy('product_id');
        foreach ($groups as $productId => $g) {
            $price = $g['qty'] > 0 ? round($g['total'] / $g['qty'], 2) : 0;
            if ($existing->has($productId)) {
                DB::table('warranty_repair_parts')->where('id', $existing[$productId]->id)->update(['qty_planned' => $g['qty'], 'unit_price' => $price, 'name' => $g['name'], 'updated_at' => now()]);
                $this->refreshPart((int) $existing[$productId]->id);
            } else {
                DB::table('warranty_repair_parts')->insert([
                    'claim_id' => $c->id, 'product_id' => $productId, 'name' => $g['name'], 'qty_planned' => $g['qty'], 'unit_price' => $price,
                    'status' => 'planned', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        foreach ($existing as $productId => $line) {
            if (! isset($groups[$productId])) {
                // dòng bị bỏ khỏi báo giá mới: SL cần = 0, nhả phần đang giữ; phần đã xuất (nếu có) sẽ hoàn kho như dư
                DB::table('warranty_repair_parts')->where('id', $line->id)->update(['qty_planned' => 0, 'qty_reserved' => 0, 'updated_at' => now()]);
                $this->refreshPart((int) $line->id);
            }
        }
    }

    public function reserveParts(int $claimId, User $actor, int $warehouseId): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor, $warehouseId) {
            $c = $this->lock($claimId);
            $this->requireWarehouse($actor);
            $this->requireStatus($c, ['approved_for_repair', 'waiting_parts']);
            if (! DB::table('crm_warehouses')->where('id', $warehouseId)->exists()) {
                throw new WarrantyException('Kho không tồn tại.');
            }
            $this->lockStockRows($c, $warehouseId);
            $parts = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->lockForUpdate()->get()->filter(fn ($p) => $this->partPending($p) > 0.0001);
            if ($parts->isEmpty()) {
                throw new WarrantyException('Không có linh kiện cần giữ hàng.');
            }
            // Khóa dòng tồn (sản phẩm × kho) để 2 phiếu tranh cùng tồn được xử lý TUẦN TỰ — phiếu sau thấy phần đã giữ của phiếu trước.
            foreach ($parts as $p) {
                DB::table('crm_product_stock')->where('product_id', $p->product_id)->where('warehouse_id', $warehouseId)->lockForUpdate()->first();
            }
            foreach ($parts as $p) {
                if ($p->warehouse_id && (int) $p->warehouse_id !== $warehouseId && ((float) $p->qty_issued > 0 || (float) $p->qty_reserved > 0)) {
                    throw new WarrantyException('Linh kiện “'.$p->name.'” đã giữ/xuất ở kho khác — dùng cùng kho.');
                }
                $avail = $this->ledger->availablePartQty((int) $p->product_id, $warehouseId, true);
                $need = $this->partPending($p);
                if ($avail + 0.0001 < $need) {
                    throw new WarrantyException('Tồn khả dụng không đủ cho “'.$p->name.'” (cần '.$need.', khả dụng '.$avail.').');
                }
            }
            foreach ($parts as $p) {
                DB::table('warranty_repair_parts')->where('id', $p->id)->update([
                    'warehouse_id' => $warehouseId, 'qty_reserved' => (float) $p->qty_reserved + $this->partPending($p),
                    'reserved_by' => $actor->id, 'reserved_at' => now(), 'updated_at' => now(),
                ]);
                $this->refreshPart((int) $p->id);
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
            $this->lockStockRows($c);
            $parts = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('qty_reserved', '>', 0)->lockForUpdate()->get();
            if ($parts->isEmpty()) {
                throw new WarrantyException('Không có linh kiện đã giữ để xuất.');
            }
            $receiver = $receiverUserId ?: ((int) $c->assigned_to ?: null);
            $detail = [];
            foreach ($parts as $p) {
                $qty = (float) $p->qty_reserved;
                [$before, $after] = $this->ledger->adjustProductStock((int) $p->product_id, (int) $p->warehouse_id, (int) $c->company_id ?: null, -$qty, 'Xuất linh kiện sửa chữa '.$c->claim_code, (int) $c->id, (int) $actor->id);
                DB::table('warranty_repair_parts')->where('id', $p->id)->update([
                    'qty_issued' => (float) $p->qty_issued + $qty, 'qty_reserved' => 0,
                    'issued_by' => $actor->id, 'issued_at' => now(), 'received_by' => $receiver, 'updated_at' => now(),
                ]);
                $this->refreshPart((int) $p->id);
                $this->partMovement($c, $p, 'repair_part_out', $qty, $before, $after, $actor, 'Xuất linh kiện '.$p->name);
                $detail[] = ['product_id' => (int) $p->product_id, 'qty' => $qty];
            }
            WarrantyAudit::log((int) $c->id, 'parts_issue', $c->status, $c->status, null, ['lines' => $detail, 'receiver' => $receiver], null, (int) $actor->id);

            return $c;
        });
    }

    /** Hoàn kho linh kiện dư = đã xuất − đã dùng − đã hoàn. Chạy trong transaction, không hoàn 2 lần. */
    public function returnLeftoverParts(int $claimId, User $actor): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor) {
            $c = $this->lock($claimId);
            $this->requireWarehouse($actor);
            $this->requireStatus($c, ['repairing', 'qa_testing', 'qa_failed', 'ready_handover', 'handed_over', 'waiting_parts', 'approved_for_repair', 'change_rejected', 'waiting_change_confirmation']);
            if (in_array($c->status, ['repairing', 'qa_testing', 'qa_failed', 'waiting_change_confirmation'], true) && trim((string) $c->repair_work_done) === '') {
                throw new WarrantyException('Kỹ thuật chưa ghi nhận linh kiện đã dùng — chưa thể hoàn kho phần dư.');
            }
            $detail = [];
            $this->lockStockRows($c);
            foreach (DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('qty_issued', '>', 0)->lockForUpdate()->get() as $p) {
                $left = $this->partLeftover($p);
                if ($left <= 0.0001) {
                    continue;
                }
                [$before, $after] = $this->ledger->adjustProductStock((int) $p->product_id, (int) $p->warehouse_id, (int) $c->company_id ?: null, $left, 'Hoàn kho linh kiện dư '.$c->claim_code, (int) $c->id, (int) $actor->id);
                DB::table('warranty_repair_parts')->where('id', $p->id)->update(['qty_returned' => (float) $p->qty_returned + $left, 'updated_at' => now()]);
                $this->refreshPart((int) $p->id);
                $this->partMovement($c, $p, 'repair_part_return', $left, $before, $after, $actor, 'Hoàn kho linh kiện dư '.$p->name);
                $detail[] = ['product_id' => (int) $p->product_id, 'qty' => $left];
            }
            if (! $detail) {
                throw new WarrantyException('Không có linh kiện dư cần hoàn kho.');
            }
            WarrantyAudit::log((int) $c->id, 'parts_return', $c->status, $c->status, null, ['lines' => $detail], null, (int) $actor->id);

            return $c;
        });
    }

    /** Thứ tự khóa THỐNG NHẤT: phiếu → dòng tồn (sản phẩm×kho, sắp xếp) → dòng linh kiện. Tránh deadlock giữa reserve/issue/return của các phiếu khác nhau. */
    private function lockStockRows(SolarWarrantyClaim $c, ?int $warehouseId = null): void
    {
        $keys = [];
        foreach (DB::table('warranty_repair_parts')->where('claim_id', $c->id)->get(['product_id', 'warehouse_id']) as $l) {
            $wh = $warehouseId ?: (int) $l->warehouse_id;
            if ($wh > 0) {
                $keys[$l->product_id.'-'.$wh] = [(int) $l->product_id, $wh];
            }
        }
        ksort($keys);
        foreach ($keys as [$pid, $wh]) {
            DB::table('crm_product_stock')->where('product_id', $pid)->where('warehouse_id', $wh)->lockForUpdate()->first();
        }
    }

    private function partMovement(SolarWarrantyClaim $c, object $p, string $type, float $qty, $before, $after, User $actor, string $note): void
    {
        $mid = (int) DB::table('solar_warranty_stock_movements')->insertGetId([
            'movement_code' => 'TMP-'.uniqid(), 'company_id' => $c->company_id, 'warranty_claim_id' => $c->id, 'site_id' => $c->site_id,
            'movement_type' => $type, 'status' => 'completed', 'warehouse_id' => $p->warehouse_id, 'product_id' => $p->product_id,
            'quantity' => $qty, 'requested_by' => $actor->id, 'approved_by' => $actor->id, 'completed_by' => $actor->id,
            'requested_at' => now(), 'approved_at' => now(), 'completed_at' => now(), 'part_line_id' => $p->id,
            'qty_before' => $before, 'qty_after' => $after, 'note' => $note, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('solar_warranty_stock_movements')->where('id', $mid)->update(['movement_code' => sprintf('XKBH-%s-%06d', now()->format('Y'), $mid)]);
    }
    // ------------------------------------------------------------------ 7. sửa chữa

    public function startRepair(int $claimId, User $actor): SolarWarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $actor) {
            $c = $this->lock($claimId);
            $this->requireStatus($c, ['approved_for_repair', 'waiting_parts', 'qa_failed']);
            $this->requireAssignedOrLead($c, $actor);
            if ($c->status !== 'qa_failed') {
                if ($this->partsPending($c)) {
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
        $unsettled = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->get()->contains(fn ($p) => $this->partLeftover($p) > 0.0001 || (float) $p->qty_reserved > 0.0001);
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
            $this->requireStatus($c, ['diagnosing', 'quotation_draft', 'waiting_customer_confirmation', 'quotation_rejected', 'approved_for_repair', 'waiting_parts', 'change_rejected']);
            if (! SolarMaintenanceAccess::isTechnicalLead($actor) && (int) $c->created_by !== (int) $actor->id) {
                throw new WarrantyException('Chỉ Trưởng phòng/Admin (hoặc người tạo) được hủy phiếu.');
            }
            if (DB::table('warranty_repair_parts')->where('claim_id', $c->id)->get()->contains(fn ($p) => $this->partLeftover($p) > 0.0001 || (float) $p->qty_used > 0)) {
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
        $n = 0;
        foreach (DB::table('warranty_repair_parts')->where('claim_id', $c->id)->where('qty_issued', '<=', 0)->lockForUpdate()->get() as $p) {
            if ((float) $p->qty_reserved > 0 || (float) $p->qty_planned > 0) {
                DB::table('warranty_repair_parts')->where('id', $p->id)->update(['qty_reserved' => 0, 'qty_planned' => 0, 'status' => 'released', 'updated_at' => now()]);
                $n++;
            }
        }
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
            $update['open_serial_norm'] = null;
        }
        DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update($update);
        $c->refresh();
        WarrantyAudit::log((int) $c->id, $action, $from, $to, $before, array_intersect_key($c->getAttributes(), $fields + ['status' => 1]), $reason, (int) $actor->id, $relType, $relId);
    }
}
