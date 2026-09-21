<?php

declare(strict_types=1);

namespace App\Support\Warranty;

/**
 * Định nghĩa trạng thái + whitelist chuyển trạng thái cho 2 quy trình:
 *  A. Đổi hàng bảo hành  (claim_type = replacement)
 *  B. Sửa chữa tính phí  (claim_type = paid_repair)
 *
 * Mọi transition đi qua WarrantyFlow::assertTransition(); KHÔNG nhận status tuỳ ý từ request.
 */
final class WarrantyFlow
{
    public const TYPE_EXCHANGE = 'replacement';
    public const TYPE_REPAIR = 'paid_repair';

    public const EXCHANGE_STATUSES = [
        'received' => 'Tiếp nhận lỗi',
        'eligibility_check' => 'Kiểm tra serial & bảo hành',
        'diagnosing' => 'Đang chẩn đoán',
        'solution_proposed' => 'Đã đề xuất phương án',
        'pending_approval' => 'Chờ duyệt',
        'needs_more_information' => 'Yêu cầu bổ sung',
        'rejected' => 'Đã từ chối',
        'approved' => 'Đã duyệt',
        'waiting_stock' => 'Chờ Kho xử lý',
        'reserved' => 'Kho đã giữ hàng',
        'issued' => 'Đã xuất kho',
        'technician_received' => 'Kỹ thuật đã nhận hàng',
        'replacing' => 'Đang thay thiết bị',
        'waiting_faulty_return' => 'Chờ thu hồi thiết bị lỗi',
        'faulty_returned' => 'Đã thu hồi thiết bị lỗi',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã hủy',
    ];

    public const EXCHANGE_TRANSITIONS = [
        'pending_approval' => ['approved', 'needs_more_information', 'rejected', 'cancelled'],
        'needs_more_information' => ['pending_approval', 'cancelled'],
        'rejected' => ['needs_more_information'],           // mở lại (có lý do)
        'approved' => ['waiting_stock', 'reserved', 'cancelled'], // 'approved' còn tồn tại ở phiếu cũ (trước v2)
        'waiting_stock' => ['reserved', 'cancelled'],
        'reserved' => ['waiting_stock', 'issued', 'cancelled'],
        'issued' => ['technician_received'],
        'technician_received' => ['replacing', 'waiting_faulty_return', 'faulty_returned'],
        'replacing' => ['waiting_faulty_return', 'faulty_returned'],
        'waiting_faulty_return' => ['faulty_returned', 'completed'], // completed chỉ khi có hoãn thu hồi được duyệt
        'faulty_returned' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public const REPAIR_STATUSES = [
        'received' => 'Tiếp nhận lỗi',
        'eligibility_check' => 'Kiểm tra serial & tình trạng',
        'diagnosing' => 'Đang chẩn đoán',
        'quotation_draft' => 'Đang lập báo giá',
        'waiting_customer_confirmation' => 'Chờ khách xác nhận',
        'quotation_rejected' => 'Khách từ chối báo giá',
        'approved_for_repair' => 'Khách đồng ý — chờ sửa',
        'waiting_parts' => 'Chờ Kho xuất linh kiện',
        'repairing' => 'Đang sửa chữa',
        'waiting_change_confirmation' => 'Chờ khách xác nhận phát sinh',
        'change_rejected' => 'Khách từ chối phát sinh',
        'qa_testing' => 'Kiểm tra sau sửa',
        'qa_failed' => 'Kiểm tra không đạt',
        'ready_handover' => 'Sẵn sàng bàn giao',
        'handed_over' => 'Đã bàn giao',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã hủy',
    ];

    public const REPAIR_TRANSITIONS = [
        'diagnosing' => ['quotation_draft', 'cancelled'],
        'quotation_draft' => ['waiting_customer_confirmation', 'cancelled'],
        'waiting_customer_confirmation' => ['approved_for_repair', 'quotation_rejected', 'quotation_draft'],
        'quotation_rejected' => ['quotation_draft', 'cancelled'],
        'approved_for_repair' => ['waiting_parts', 'repairing', 'quotation_draft', 'cancelled'],
        'waiting_parts' => ['repairing', 'quotation_draft', 'cancelled'],
        'repairing' => ['qa_testing', 'waiting_change_confirmation'],
        'waiting_change_confirmation' => ['repairing', 'waiting_parts', 'change_rejected'],
        'change_rejected' => ['repairing', 'cancelled'],
        'qa_testing' => ['ready_handover', 'qa_failed'],
        'qa_failed' => ['repairing'],
        'ready_handover' => ['handed_over'],
        'handed_over' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public const REPAIR_DECISION_METHODS = [
        'phone' => 'Điện thoại',
        'zalo' => 'Zalo',
        'email' => 'Email',
        'in_person' => 'Trực tiếp',
        'other' => 'Khác',
    ];

    public const FAULTY_CONDITIONS = [
        'damaged' => 'Hỏng / cháy',
        'physical' => 'Hư hỏng vật lý',
        'incomplete' => 'Thiếu phụ kiện',
        'normal' => 'Nguyên trạng',
        'other' => 'Khác',
    ];

    /** Trạng thái kết thúc — phiếu không còn "mở". */
    public const TERMINAL = ['completed', 'cancelled', 'rejected'];

    /** Chuẩn hóa serial để so sánh chống trùng: trim + gộp khoảng trắng + IN HOA. Rỗng → null. Serial gốc vẫn được lưu nguyên để hiển thị. */
    public static function normalizeSerial(?string $serial): ?string
    {
        $v = trim((string) $serial);
        if ($v === '') {
            return null;
        }

        return mb_strtoupper((string) preg_replace('/\s+/u', ' ', $v), 'UTF-8');
    }

    public static function isFlowType(?string $type): bool
    {
        return in_array($type, [self::TYPE_EXCHANGE, self::TYPE_REPAIR], true);
    }

    public static function statuses(string $type): array
    {
        return $type === self::TYPE_REPAIR ? self::REPAIR_STATUSES : self::EXCHANGE_STATUSES;
    }

    public static function label(string $type, ?string $status): string
    {
        return self::statuses($type)[(string) $status] ?? (string) $status;
    }

    public static function transitions(string $type): array
    {
        return $type === self::TYPE_REPAIR ? self::REPAIR_TRANSITIONS : self::EXCHANGE_TRANSITIONS;
    }

    public static function canTransition(string $type, string $from, string $to): bool
    {
        return in_array($to, self::transitions($type)[$from] ?? [], true);
    }

    public static function assertTransition(string $type, string $from, string $to): void
    {
        if (! self::canTransition($type, $from, $to)) {
            throw new WarrantyException(sprintf(
                'Không thể chuyển từ “%s” sang “%s”.',
                self::label($type, $from),
                self::label($type, $to)
            ));
        }
    }

    /**
     * Có phiếu "mở" không: khoá chống trùng serial chỉ giữ khi phiếu mở.
     * Rejected có thể được mở lại nên vẫn coi là kết thúc (khoá được cấp lại lúc mở lại).
     */
    public static function isOpen(?string $status): bool
    {
        return ! in_array((string) $status, self::TERMINAL, true);
    }

    /** Dòng thời gian 11 bước (đổi hàng) — trả về mảng [key,label,state,detail]. */
    public static function exchangeTimeline(object $c, bool $hasReplacementLink): array
    {
        $status = (string) $c->status;
        $steps = [
            ['received', 'Tiếp nhận lỗi', true, self::dt($c->received_at ?? null)],
            ['eligibility', 'Kiểm tra serial & bảo hành', true, ! empty($c->warranty_exception) ? 'Ngoại lệ bảo hành' : 'Đủ điều kiện'],
            ['proposal', 'Tạo đề xuất đổi hàng', true, self::dt($c->submitted_at ?? null)],
            ['approval', 'Trưởng phòng duyệt', ! empty($c->approved_at), $status === 'needs_more_information' ? 'Yêu cầu bổ sung' : ($status === 'rejected' ? 'Đã từ chối' : self::dt($c->approved_at ?? null))],
            ['to_warehouse', 'Chuyển Kho xử lý', ! empty($c->approved_at), ''],
            ['reserve', 'Kho chọn serial & giữ hàng', ! empty($c->reserved_serial_unit_id) || ! empty($c->replacement_serial_unit_id), (string) ($c->replacement_serial_code ?? $c->reserved_serial_code ?? '')],
            ['issue', 'Kho xuất thiết bị thay thế', ! empty($c->issued_at), self::dt($c->issued_at ?? null)],
            ['replace', 'Kỹ thuật nhận & thay thiết bị', ! empty($c->replaced_at), self::dt($c->replaced_at ?? $c->tech_received_at ?? null)],
            ['faulty_return', 'Thu hồi thiết bị lỗi', ($c->faulty_return_status ?? null) === 'returned', (string) ($c->faulty_return_status ?? '') === 'deferred' ? 'Chờ thu hồi (đã hoãn)' : self::dt($c->returned_at ?? null)],
            ['pair', 'Lưu cặp serial cũ ↔ mới', $hasReplacementLink, ''],
            ['complete', 'Hoàn tất', $status === 'completed', self::dt($c->closed_at ?? null)],
        ];

        return self::mark($steps, $status);
    }

    /** Dòng thời gian 10 bước (sửa chữa tính phí). */
    public static function repairTimeline(object $c, array $flags): array
    {
        $status = (string) $c->status;
        $steps = [
            ['received', 'Tiếp nhận sản phẩm', true, self::dt($c->received_at ?? null)],
            ['diagnosis', 'Kỹ thuật chẩn đoán', ! empty($c->diagnosis) && ! empty($c->diagnosis_cause), ''],
            ['quotation', 'Lập báo giá sửa chữa', (bool) ($flags['quotation_sent'] ?? false), ''],
            ['customer', 'Khách xác nhận', (bool) ($flags['quotation_approved'] ?? false), ''],
            ['parts', 'Xuất linh kiện / chuẩn bị', (bool) ($flags['parts_ready'] ?? false), ''],
            ['repair', 'Tiến hành sửa chữa', ! empty($c->repair_started_at) && in_array($status, ['qa_testing', 'qa_failed', 'ready_handover', 'handed_over', 'completed'], true),
                $status === 'waiting_change_confirmation' ? 'Chờ khách xác nhận phát sinh' : ($status === 'change_rejected' ? 'Khách từ chối phát sinh' : self::dt($c->repair_started_at ?? null))],
            ['qa', 'Kiểm tra sau sửa (QA)', (bool) ($flags['qa_passed'] ?? false), ''],
            ['handover', 'Bàn giao khách hàng', ! empty($c->handed_over_at), self::dt($c->handed_over_at ?? null)],
            ['complete', 'Hoàn tất', $status === 'completed', self::dt($c->closed_at ?? null)],
        ];

        return self::mark($steps, $status);
    }

    private static function mark(array $steps, string $status): array
    {
        $out = [];
        $currentFound = in_array($status, ['completed'], true) || $status === 'cancelled' || $status === 'rejected';
        foreach ($steps as $i => [$key, $label, $done, $detail]) {
            if ($done) {
                $state = 'done';
            } elseif (! $currentFound) {
                $state = 'current';
                $currentFound = true;
            } else {
                $state = 'pending';
            }
            $out[] = ['no' => $i + 1, 'key' => $key, 'label' => $label, 'state' => $state, 'detail' => $detail];
        }

        return $out;
    }

    private static function dt($v): string
    {
        if (! $v) {
            return '';
        }
        try {
            return \Illuminate\Support\Carbon::parse($v)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $v;
        }
    }
}
