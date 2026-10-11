<?php

namespace App\Services\TechnicalKpi;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * Checklist nguồn dưới 6 tiêu chí ở chi tiết KPI từng người.
 *
 * Chỉ trình bày kết quả của KpiStandardEvaluator: trạng thái dòng (`state`), phần điểm (`points_possible`,
 * `points_earned` — chỉ có khi tiêu chí đã có điểm), giai đoạn việc KS01 (`phase`) và trạng thái nhập/xác nhận
 * của nguồn Nghiệm thu (`source_status`). Không tính lại tỷ lệ hay điểm ở đây.
 *
 * Tách 2 lớp: "Thực hiện" (đã làm/chưa làm/còn hạn/chờ xác nhận…) và "KPI" (nhận/mất điểm, chưa tính, loại trừ).
 */
class KpiChecklistPresenter
{
    /** Số dòng hiện sẵn; phần còn lại thu gọn ngay tại chỗ. */
    public const VISIBLE_ROWS = 5;

    private const ACCEPTANCE_STATUS = [
        'confirmed' => ['Đã xác nhận', 'success'],
        'legacy' => ['Đã đánh giá (dạng cũ)', 'success'],
        'submitted' => ['Chờ trưởng phòng xác nhận', 'warning'],
        'returned' => ['Bị trả bổ sung', 'warning'],
        'draft' => ['Đang nhập (nháp)', 'muted'],
        'stale' => ['Xác nhận cũ hết hiệu lực — chờ gửi lại', 'warning'],
        'legacy_incomplete' => ['Dữ liệu cũ chưa đủ', 'warning'],
        'empty' => ['Chưa nhập', 'muted'],
    ];

    /**
     * @param  array<int, array<string, mixed>>  $criteria  kết quả `score()['criteria']` của MỘT người
     * @param  array{viewer_id?: int, can_manage?: bool}  $context
     * @return array<int, array<string, mixed>>
     */
    public function build(array $criteria, array $context = []): array
    {
        return array_map(fn (array $c) => $this->block($c, $context), $criteria);
    }

    /**
     * Gắn `url` vào từng dòng nguồn (dùng chung cho checklist và hộp "Xem cách tính").
     *
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<int, array<string, mixed>>
     */
    public function withSourceUrls(array $criteria, array $context = []): array
    {
        foreach ($criteria as &$c) {
            foreach ($c['evidence'] as &$ev) {
                $ev['url'] = $this->sourceUrl((string) $c['code'], $ev, $context);
            }
            unset($ev);
        }
        unset($c);

        return $criteria;
    }

    /** Link hồ sơ nguồn; quyền vẫn do trang đích kiểm tra. */
    public function sourceUrl(string $code, array $ev, array $context = []): ?string
    {
        $canManage = (bool) ($context['can_manage'] ?? false);
        $viewerId = (int) ($context['viewer_id'] ?? 0);

        if ((int) ($ev['report_id'] ?? 0) > 0 && Route::has('technical.daily-reports.show')) {
            return route('technical.daily-reports.show', ['report' => (int) $ev['report_id']]);
        }
        // Báo cáo nháp chỉ chủ báo cáo mở được; người khác mở dòng việc trong kế hoạch.
        if ((int) ($ev['draft_report_id'] ?? 0) > 0 && $viewerId > 0 && $viewerId === (int) ($ev['user_id'] ?? 0)
            && Route::has('technical.daily-reports.show')) {
            return route('technical.daily-reports.show', ['report' => (int) $ev['draft_report_id']]);
        }
        if ((int) ($ev['plan_item_id'] ?? 0) > 0 && ! empty($ev['plan_date']) && Route::has('technical.week-plan.index')) {
            $week = Carbon::parse($ev['plan_date'])->startOfWeek()->toDateString();

            return $canManage && Route::has('technical.manager.detail')
                ? route('technical.manager.detail', ['member' => (int) $ev['user_id'], 'week' => $week, 'item' => (int) $ev['plan_item_id']]).'#adj-'.(int) $ev['plan_item_id']
                : route('technical.week-plan.index', ['week' => $week]);
        }
        if ($code === 'warranty' && (int) ($ev['schedule_id'] ?? 0) > 0 && Route::has('projects-unified.maintenance.show')) {
            return route('projects-unified.maintenance.show', ['schedule' => (int) $ev['schedule_id']]);
        }
        if ($code !== 'warranty' && (int) ($ev['site_id'] ?? 0) > 0 && Route::has('projects-unified.show')) {
            $step = $code === 'survey' && empty($ev['material_manual']) ? 'survey' : 'acceptance';
            $anchor = in_array($code, ['quality', 'hse', 'evn_app'], true) ? 'acceptance-kpi' : 'workflow';
            if ($code === 'survey' && ! empty($ev['material_manual'])) {
                $anchor = 'material-kpi';
            }

            return route('projects-unified.show', ['site' => (int) $ev['site_id'], 'step' => $step]).'#'.$anchor;
        }

        return null;
    }

    private function block(array $c, array $context): array
    {
        $code = (string) $c['code'];
        $scored = $c['score'] !== null;
        $rows = [];
        foreach ($c['evidence'] as $ev) {
            $rows[] = $this->row($c, $ev, $scored, $context);
        }

        $count = fn (string $bucket) => count(array_filter($rows, fn ($r) => $r['bucket'] === $bucket));
        $weightPoints = $c['weight'] !== null ? (float) $c['weight'] * 100 : null;

        return [
            'code' => $code,
            'no' => $c['no'] ?? null,
            'name' => (string) $c['name'],
            'status' => (string) $c['status'],
            'weight_points' => $weightPoints,
            'score_points' => $scored ? (float) $c['score'] * 100 : null,
            'score_text' => $c['status'] === KpiStandardEvaluator::STATUS_PENDING
                ? 'Chờ xác nhận quy tắc'
                : ($scored ? $this->points((float) $c['score'] * 100).'/'.$this->points((float) $weightPoints).' điểm' : 'Chưa tính'),
            'counts' => [
                'earned' => $count('earned'),
                'lost' => $count('lost'),
                'waiting' => $count('waiting'),
                'missing' => $count('missing'),
                'excluded' => $count('excluded'),
                'total' => count($rows),
            ],
            'missing' => array_values((array) ($c['missing'] ?? [])),
            'rows' => $rows,
            'visible' => self::VISIBLE_ROWS,
            'input_hint' => $rows === [] ? $this->inputHint($code, $c, $context) : null,
        ];
    }

    private function row(array $c, array $ev, bool $scored, array $context): array
    {
        $code = (string) $c['code'];
        $state = (string) ($ev['state'] ?? 'info');
        [$work, $workTone, $meta] = $code === 'timeline' && isset($ev['phase'])
            ? $this->assignedWork($ev)
            : $this->genericWork($code, $ev, $state);

        $possible = $scored && isset($ev['points_possible']) ? (float) $ev['points_possible'] : null;
        [$bucket, $kpi, $kpiTone] = match (true) {
            $state === 'excluded' => ['excluded', 'Loại trừ — không tính', 'muted'],
            $state === 'missing' => ['missing', 'Chưa tính — thiếu dữ liệu', 'warning'],
            $state === 'pass' && $possible !== null => ['earned', 'Nhận '.$this->points($possible).' điểm', 'success'],
            $state === 'fail' && $possible !== null => ['lost', 'Mất '.$this->points($possible).' điểm', 'danger'],
            in_array($state, ['pass', 'fail'], true) && $c['status'] === KpiStandardEvaluator::STATUS_PENDING => ['waiting', 'Chưa tính — tiêu chí chờ xác nhận quy tắc', 'muted'],
            in_array($state, ['pass', 'fail'], true) => ['waiting', ($state === 'pass' ? 'Đạt' : 'Không đạt').' — chưa tính vì tiêu chí còn thiếu dữ liệu', 'warning'],
            $state === 'overdue' => ['lost', 'Quá hạn còn mở', 'danger'],
            ($ev['phase'] ?? '') === 'pending' => ['waiting', 'Chưa tính — chưa đến hạn, không mất điểm', 'muted'],
            default => ['waiting', 'Chưa tính', 'muted'],
        };

        return [
            'title' => (string) ($ev['project'] ?? ''),
            'url' => $ev['url'] ?? $this->sourceUrl($code, $ev, $context),
            'month' => (string) ($ev['month'] ?? ''),
            'work' => $work,
            'work_tone' => $workTone,
            'meta' => $meta,
            'kpi' => $kpi,
            'kpi_tone' => $kpiTone,
            'bucket' => $bucket,
            'reason' => (string) ($ev['result'] ?? ''),
            'detail' => (string) ($ev['detail'] ?? ''),
            'state' => $state,
        ];
    }

    /** KS01: việc trưởng phòng giao — mốc giao, hạn, lần nộp đầu. */
    private function assignedWork(array $ev): array
    {
        $fmt = fn ($at) => $at ? Carbon::parse($at)->format('d/m H:i') : '—';
        $meta = 'Giao '.$fmt($ev['assigned_at'] ?? null).' · Hạn '.$fmt($ev['due_at'] ?? null)
            .' · Nộp lần đầu '.$fmt($ev['submitted_at'] ?? null);
        $sent = ! empty($ev['submitted_at']);
        $draft = (int) ($ev['draft_report_id'] ?? 0) > 0;

        return match ($ev['phase']) {
            'cancelled' => ['Đã hủy', 'muted', $meta],
            'missing_assigned_at' => ['Thiếu giờ giao việc', 'warning', $meta],
            'sent_before_assigned' => ['Giờ nộp trước giờ giao — cần xác minh', 'warning', $meta],
            'not_assigned_yet' => ['Chưa giao tại thời điểm đánh giá', 'muted', $meta],
            'pending' => [$sent
                ? 'Đã nộp · còn hạn đến '.$fmt($ev['due_at'] ?? null)
                : ($draft ? 'Chưa nộp (có bản nháp) · còn hạn đến ' : 'Chưa nộp · còn hạn đến ').$fmt($ev['due_at'] ?? null), 'info', $meta],
            default => match (true) {
                ($ev['state'] ?? '') === 'pass' => ['Đã nộp đúng hạn', 'success', $meta],
                $sent => ['Đã nộp · muộn '.$this->duration((int) ($ev['late_seconds'] ?? 0)), 'danger', $meta],
                $draft => ['Chưa nộp · quá hạn (chỉ có bản nháp)', 'danger', $meta],
                default => ['Chưa nộp · quá hạn', 'danger', $meta],
            },
        };
    }

    private function genericWork(string $code, array $ev, string $state): array
    {
        $meta = (string) ($ev['detail'] ?? '');
        $status = $ev['source_status'] ?? null;
        if ($status !== null && isset(self::ACCEPTANCE_STATUS[$status])) {
            [$label, $tone] = self::ACCEPTANCE_STATUS[$status];

            return [$label, $tone, $meta];
        }

        return match ($code) {
            'warranty' => match ($state) {
                'pass' => ['Hoàn tất đúng SLA', 'success', $meta],
                'fail' => ['Không đạt SLA', 'danger', $meta],
                'excluded' => ['Phiếu đã hủy', 'muted', $meta],
                'missing' => ['Chưa đủ mốc / chưa kiểm tra khiếu nại', 'warning', $meta],
                default => ['Tham khảo', 'muted', $meta],
            },
            default => match ($state) {
                'pass' => ['Có nguồn · đạt', 'success', $meta],
                'fail' => ['Có nguồn · không đạt', 'danger', $meta],
                'excluded' => ['Không áp dụng', 'muted', $meta],
                'missing' => ['Thiếu dữ liệu / chưa xác nhận', 'warning', $meta],
                'overdue' => ['Quá hạn chưa bàn giao', 'danger', $meta],
                default => ['Tham khảo', 'muted', $meta],
            },
        };
    }

    /** Khi tiêu chí chưa có dòng nguồn: nơi nhập/nộp đang có trong hệ thống. */
    private function inputHint(string $code, array $c, array $context): ?array
    {
        $canManage = (bool) ($context['can_manage'] ?? false);
        $link = fn (string $route, string $label, array $params = []) => Route::has($route) ? ['url' => route($route, $params), 'label' => $label] : null;

        return match (true) {
            $code === 'timeline' && ($c['source_mode'] ?? '') === 'assigned_reports' => $canManage
                ? $link('technical.manager.board', 'Trưởng phòng giao việc tại Điều phối kế hoạch')
                : $link('technical.week-plan.index', 'Xem việc được giao trong Kế hoạch tuần'),
            in_array($code, ['quality', 'hse', 'evn_app', 'survey'], true) => $link('projects-unified.index', 'Nhập/xác nhận tại bước Nghiệm thu của công trình'),
            $code === 'warranty' => $link('projects-unified.maintenance.index', 'Xem phiếu Bảo trì/Bảo hành'),
            default => null,
        };
    }

    public function points(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' giây';
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return trim(($hours > 0 ? $hours.' giờ ' : '').($minutes > 0 ? $minutes.' phút' : ''));
    }
}
