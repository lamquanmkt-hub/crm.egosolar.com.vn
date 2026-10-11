<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Contracts\Services\PageAccessServiceInterface;
use App\Services\Hr\Kpi\Sources\AdminDeadlineSource;
use App\Services\Hr\Kpi\Sources\AssetsServiceSource;
use App\Services\Hr\Kpi\Sources\DocumentAccuracySource;
use App\Services\Hr\Kpi\Sources\PayrollAccuracySource;
use App\Services\Hr\Kpi\Sources\ProbationQuitSource;
use App\Services\Hr\Kpi\Sources\RecruitmentCostSource;
use App\Services\Hr\Kpi\Sources\RecruitmentTimeSource;
use App\Services\Hr\OfficeIncidents;
use App\Services\Hr\Payroll\PayrollAccess;
use App\Services\Tasks\TaskPermissions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Checklist nguồn KPI HCNS của một người/kỳ — đọc kết quả bộ đo nguồn, không tự tính công thức riêng.
 *
 * Tiêu chí có nguồn tự động (HC09, HC07): số đo + từng hồ sơ (đã làm chưa, được tính chưa, lý do, link).
 * Điểm chính thức chỉ khi có phiên bản quy tắc được duyệt; bản nháp chỉ hiện "xem trước" theo công thức
 * baseline của tiêu chí (Thực tế ÷ Chỉ tiêu, trần), có nhãn chưa duyệt. Các tiêu chí khác ghi rõ đang chấm tay.
 */
final class HcnsKpiChecklist
{
    /** criterion code => [adapter class, mã tài liệu, trang nguồn] */
    public const SOURCES = [
        AdminDeadlineSource::CODE => [AdminDeadlineSource::class, 'HC09', 'Công việc → Thủ tục hành chính HCNS'],
        DocumentAccuracySource::CODE => [DocumentAccuracySource::class, 'HC07', 'Nhân sự → Giao nhận hồ sơ (theo dõi KPI HC07)'],
        AssetsServiceSource::CODE => [AssetsServiceSource::class, 'HC06', 'Văn phòng phẩm (phiếu theo dõi KPI) · HC & Vận hành → Kiểm kê · Sự cố văn phòng'],
        RecruitmentTimeSource::CODE => [RecruitmentTimeSource::class, 'HC01', 'Tuyển dụng → Y/c tuyển dụng → KPI tuyển dụng → Lượt tuyển'],
        RecruitmentCostSource::CODE => [RecruitmentCostSource::class, 'HC02', 'Tuyển dụng → KPI tuyển dụng → Chi phí (chứng từ ĐNTT/tạm ứng/hoàn tiền)'],
        ProbationQuitSource::CODE => [ProbationQuitSource::class, 'HC03', 'Tuyển dụng → Lượt tuyển → Kết quả thử việc'],
        PayrollAccuracySource::CODE => [PayrollAccuracySource::class, 'HC04', 'Bảng lương tháng → Đối soát độ chính xác (đợt đối soát)'],
    ];

    /** Mã tài liệu cho 7 tiêu chí còn chấm tay (để người đọc đối chiếu SOURCE-MAP). */
    private const DOC_CODES = [
        'recruit_time' => 'HC01', 'recruit_cost' => 'HC02', 'probation_quit' => 'HC03', 'payroll_accuracy' => 'HC04',
        'insurance' => 'HC05', 'assets' => 'HC06', 'satisfaction' => 'HC08',
    ];

    public function __construct(
        private readonly HcnsKpiRules $rules,
        private readonly HrKpiEvaluator $evaluator,
    ) {}

    /** Có ít nhất một nguồn tự động sẵn sàng trên DB này không. */
    public static function available(): bool
    {
        return HcnsAdminTasks::ready() || DocumentAccuracySource::ready() || AssetsServiceSource::ready() || RecruitmentKpi::ready() || PayrollAccuracySource::ready();
    }

    /**
     * Phiên bản quy tắc (đã duyệt) có chọn lấy điểm chính thức của tiêu chí từ nguồn không.
     * HC09/HC07: phiên bản áp dụng = dùng nguồn. HC06: phải chọn cách gộp ba nhóm. HC01–HC03: phải chọn “Lấy điểm
     * chính thức từ nguồn” (mặc định chỉ xem trước vì ngưỡng/cohort/quy đổi chưa chốt). HC04 tương tự (đơn vị/kỳ/lỗi đã
     * sửa chưa chốt, H-D05). Chưa chọn → giữ chấm tay.
     */
    public static function sourceOfficial(string $code, array $rules): bool
    {
        return match ($code) {
            AssetsServiceSource::CODE => AssetsServiceSource::officialEnabled($rules),
            RecruitmentTimeSource::CODE, RecruitmentCostSource::CODE, ProbationQuitSource::CODE,
            PayrollAccuracySource::CODE => ($rules[$code]['adoption'] ?? 'preview_only') === 'source',
            default => isset(self::SOURCES[$code]),
        };
    }

    /**
     * @param  Collection  $criteria  tiêu chí của bộ HCNS (hr_kpi_criteria)
     * @param  Collection  $scores  điểm nhập tay [criterion_id => row]
     */
    public function forUser(int $userId, string $month, object $template, Collection $criteria, Collection $scores, $viewer, ?Carbon $now = null, ?array $ruleOverride = null): array
    {
        $now ??= now();
        // $ruleOverride: xem trước một bản nháp cụ thể (trang Cài đặt) thay cho quy tắc của kỳ.
        $rule = $ruleOverride ?? $this->rules->forMonth($month);
        $cap = (float) ($template->max_achievement ?? 120);
        $blocks = [];
        // Lớp “chính thức”: đúng kết quả của bộ chấm (nguồn khi quy tắc đã duyệt, ngược lại nhập tay). Bỏ qua khi xem trước bản nháp.
        $officialRows = $ruleOverride === null
            ? collect($this->evaluator->score($template, $criteria, $scores, $this->evaluator->sourceOverrides($template, $criteria, $userId, $month, $scores, $now))['rows'])
                ->keyBy(fn ($row) => (int) $row['criterion']->id)
            : collect();

        foreach ($criteria->values() as $i => $criterion) {
            $code = (string) $criterion->code;
            $manual = $scores->get($criterion->id);
            $block = [
                'no' => $i + 1,
                'code' => $code,
                'doc_code' => self::SOURCES[$code][1] ?? self::DOC_CODES[$code] ?? '',
                'name' => (string) $criterion->name,
                'target_text' => (string) ($criterion->target_text ?? ''),
                'weight' => $criterion->weight !== null ? (float) $criterion->weight : null,
                'manual' => $manual,
                'auto' => false,
                'official_score' => $this->officialScore($officialRows->get((int) $criterion->id)),
            ];

            if (! isset(self::SOURCES[$code]) || ! self::sourceReady($code)) {
                $block['note'] = isset(self::SOURCES[$code]) && $rule['status'] === 'applied' && self::sourceOfficial($code, (array) $rule['rules'])
                    ? 'Phiên bản áp dụng chọn tính từ nguồn nhưng nguồn chưa sẵn sàng trên hệ thống (thiếu bảng/cột) — tiêu chí CHƯA ĐỦ, không quay về chấm tay.'
                    : 'Chưa có nguồn tự động — đang chấm tay ở bảng trên (P5–P6 chưa triển khai).';
                $blocks[] = $block;

                continue;
            }

            $adapter = app(self::SOURCES[$code][0]);
            $rows = array_map(fn ($row) => $row + ['can_open' => $this->canOpen($code, $row, $viewer), 'doc_can_open' => isset($row['doc_id']) && $this->canOpenDocument($row, $viewer)],
                $adapter->rows($userId, $month, $rule['rules'], $now));
            $summary = $adapter->summarize($rows, $month, $now, (array) $rule['rules']);
            $preview = $summary['value'] !== null
                ? $this->evaluator->achievement($criterion, (object) ['achievement' => null, 'actual_value' => $summary['value']], $cap)
                : null;
            // Nguồn chỉ thay chấm tay khi phiên bản đã duyệt có chọn tính từ nguồn (khớp HrKpiEvaluator::sourceOverrides).
            $sourceOfficial = $rule['status'] === 'applied' && self::sourceOfficial($code, (array) $rule['rules']);

            $blocks[] = array_merge($block, [
                'auto' => true,
                'source_label' => self::SOURCES[$code][2],
                'rows' => $rows,
                'summary' => $summary,
                'rule_lines' => $this->rules->describe($code, $rule['rules']),
                'official' => $sourceOfficial,
                // Chính thức chỉ khi quy tắc đã duyệt VÀ nguồn đủ dữ liệu (khớp HrKpiEvaluator::sourceOverrides).
                'official_ready' => $sourceOfficial && $summary['data_status'] === 'complete' && $preview !== null,
                'preview_achievement' => $preview,
                'preview_points' => $preview !== null && $block['weight'] !== null ? round($block['weight'] * $preview / 100, 2) : null,
                'manual_ignored' => $sourceOfficial && $manual !== null,
                'note' => $this->note($summary, $rule, $sourceOfficial),
            ]);
        }

        return ['rule' => $rule, 'blocks' => $blocks, 'month' => $month];
    }

    /** Điểm chính thức của một tiêu chí theo bộ chấm (null khi đang xem trước bản nháp). */
    private function officialScore(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $from = $row['source'] ? 'source' : ($row['score'] ? 'manual' : 'none');
        $text = match (true) {
            $row['not_applicable'] => $from === 'source' ? 'Không phát sinh (đã xác nhận) — không tính' : 'Không phát sinh (nhập tay) — không tính',
            $row['achievement'] !== null => ($from === 'source' ? 'Từ nguồn: ' : 'Nhập tay: ').rtrim(rtrim(number_format((float) $row['achievement'], 2, ',', '.'), '0'), ',').'% đạt'
                .($row['points'] !== null ? ' · '.rtrim(rtrim(number_format((float) $row['points'], 2, ',', '.'), '0'), ',').' điểm' : ''),
            $from === 'source' => 'Chưa đủ — '.($row['source']['reason'] ?? 'nguồn chưa đủ'),
            default => 'Chưa có — chưa nhập ở bảng trên',
        };

        return ['from' => $from, 'achievement' => $row['achievement'], 'points' => $row['points'], 'not_applicable' => (bool) $row['not_applicable'], 'text' => $text];
    }

    /** Checklist đọc từ ảnh chụp kỳ đã chốt: tính lại quyền mở link theo người đang xem. */
    public function forViewer(array $checklist, $viewer): array
    {
        foreach ($checklist['blocks'] ?? [] as $i => $block) {
            foreach ($block['rows'] ?? [] as $j => $row) {
                $checklist['blocks'][$i]['rows'][$j]['can_open'] = $this->canOpen((string) $block['code'], $row, $viewer);
                $checklist['blocks'][$i]['rows'][$j]['doc_can_open'] = isset($row['doc_id']) && $this->canOpenDocument($row, $viewer);
            }
        }
        $checklist['from_snapshot'] = true;

        return $checklist;
    }

    /** Nguồn tự động của tiêu chí đã có đủ bảng/cột trên DB này chưa. */
    public static function sourceReady(string $code): bool
    {
        return match ($code) {
            AdminDeadlineSource::CODE => HcnsAdminTasks::ready(),
            DocumentAccuracySource::CODE => DocumentAccuracySource::ready(),
            AssetsServiceSource::CODE => AssetsServiceSource::ready(),
            RecruitmentTimeSource::CODE, ProbationQuitSource::CODE => RecruitmentKpi::ready(),
            RecruitmentCostSource::CODE => RecruitmentKpi::costsReady(),
            PayrollAccuracySource::CODE => PayrollAccuracySource::ready(),
            default => false,
        };
    }

    private function note(array $summary, array $rule, bool $sourceOfficial): string
    {
        $status = match ($summary['data_status']) {
            'no_items' => 'Không có hồ sơ nguồn trong kỳ — chưa có xác nhận “không phát sinh”, không tự thành 100%.',
            'incomplete' => 'Còn hồ sơ chờ xác nhận/thiếu dữ liệu — số đo chưa đủ.',
            'open' => 'Kỳ chưa kết thúc — số đo còn thay đổi.',
            default => 'Đủ dữ liệu theo quy tắc đang xem.',
        };

        return $status.' '.match (true) {
            $sourceOfficial => 'Theo '.$rule['label'].'.',
            ! empty($summary['combined_reason']) => $summary['combined_reason'].' Điểm chính thức vẫn chấm tay.',
            $rule['status'] === 'applied' => 'Theo '.$rule['label'].' — tiêu chí này chưa lấy điểm từ nguồn.',
            default => 'Quy tắc chưa chốt (bản nháp) — chỉ xem trước, chưa phải điểm chính thức.',
        };
    }

    private function canOpenHr($viewer): bool
    {
        return app(PageAccessServiceInterface::class)->isAdmin($viewer)
            || app(PageAccessServiceInterface::class)->canAccess($viewer, 'page.hr');
    }

    /** Link nguồn chỉ hiện khi người xem mở được đúng hồ sơ đó. */
    private function canOpen(string $code, array $row, $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        return match ($code) {
            AdminDeadlineSource::CODE => TaskPermissions::canView($viewer, (object) [
                'assignee_id' => $row['owner_id'], 'requester_id' => $row['requester_id'] ?? null, 'approver_id' => $row['reviewer_id'] ?? null,
            ]),
            DocumentAccuracySource::CODE => $this->canOpenHr($viewer),
            // HC06: phiếu VPP/đợt kiểm kê nằm trong trang Nhân sự; sự cố mở theo quyền xem sự cố (người báo/HCNS/Ban Giám đốc).
            AssetsServiceSource::CODE => ($row['group'] ?? '') === 'incident'
                ? OfficeIncidents::canView($viewer, (object) ['reporter_id' => $row['reporter_id'] ?? 0])
                : $this->canOpenHr($viewer),
            // Lượt tuyển / khoản chi nằm trong trang Tuyển dụng (quyền trang Nhân sự).
            RecruitmentTimeSource::CODE, RecruitmentCostSource::CODE, ProbationQuitSource::CODE => $this->canOpenHr($viewer),
            // Đợt đối soát nằm trong trang Bảng lương (quyền quản lý lương sẵn có: HR/Kế toán/Ban Giám đốc/Admin).
            PayrollAccuracySource::CODE => PayrollAccess::canManage($viewer),
            default => false,
        };
    }

    /** Link chứng từ ĐNTT: theo đúng quyền xem của module đề nghị thanh toán (Admin/Kế toán/HCNS hoặc người lập). */
    private function canOpenDocument(array $row, $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        return (method_exists($viewer, 'hasAnyRole') && $viewer->hasAnyRole(['admin', 'accounting', 'ketoan', 'ke_toan', 'hr']))
            || (int) ($row['doc_created_by'] ?? 0) === (int) $viewer->id;
    }
}
