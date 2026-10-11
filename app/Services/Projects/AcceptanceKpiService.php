<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\Site;
use App\Models\TechnicalKpiConfig;
use App\Models\User;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use App\Services\TechnicalKpi\ProjectKpiLinkService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kết quả nghiệm thu tính KPI kỹ thuật (KS02 chất lượng, KS04 HSE, KS05 EVN/App) tại bước "Nghiệm thu và bàn giao".
 *
 * Luồng: người được giao lưu nháp → gửi kiểm tra → Trưởng phòng KT/Admin (không thuộc nhóm thực hiện) xác nhận
 * từng nhóm hoặc trả bổ sung. Xác nhận KPI KHÔNG duyệt bước nghiệm thu (track duyệt cuối của Admin giữ nguyên).
 *
 * Dữ liệu lưu trong `project_workflow_steps.data['kpi_acceptance']` của bước acceptance:
 *   values         bản khai báo hiện tại; mỗi mục: null = chưa ghi nhận, 'pass'/'fail' (HSE, EVN), 'none'/'has' (phản ánh)
 *   status         draft | submitted | returned
 *   version        tăng mỗi lần khai báo thay đổi
 *   submitted      {version, by, at, fingerprints{nhóm}} của lần gửi gần nhất
 *   returned       {by, at, reason, groups} của lần trả bổ sung gần nhất
 *   confirmations  {nhóm: {fingerprint, result, version, by, at, admin_exception}}
 *
 * "Vân tay" (fingerprint) của mỗi nhóm = nội dung khai báo của nhóm + minh chứng liên quan (id/phiên bản file)
 * + số lần bị trả lại ở Thi công/Nghiệm thu (nhóm chất lượng) + quyết định cần đấu nối (nhóm EVN).
 * Xác nhận chỉ còn hiệu lực khi vân tay hiện tại trùng vân tay lúc xác nhận: sửa nội dung, thay/thêm file minh chứng
 * hoặc bị trả lại đều làm xác nhận cũ không áp dụng cho bản mới. Người/giờ xác nhận do server ghi.
 */
final class AcceptanceKpiService
{
    public const KEY = 'kpi_acceptance';

    public const GROUPS = [
        'quality' => 'Chất lượng & phản ánh khách hàng',
        'hse' => 'An toàn HSE & vệ sinh',
        'evn' => 'EVN & App giám sát',
    ];

    /** Mã tiêu chí KPI tương ứng từng nhóm. */
    public const CRITERION = ['quality' => 'quality', 'hse' => 'hse', 'evn' => 'evn_app'];

    /**
     * Minh chứng của từng nhóm = loại hồ sơ ĐÃ CÓ trong cấu hình bước nghiệm thu (không thêm loại/số file mới).
     * Nhóm chỉ được xác nhận khi có ít nhất một file thuộc một trong các loại này.
     */
    public const EVIDENCE = [
        'quality' => ['customer_acceptance', 'completion_photos'],
        'hse' => ['completion_photos'],
        'evn' => ['test_certificates', 'handover_minutes'],
    ];

    public const STATUS_LABELS = [
        'empty' => 'Chưa ghi nhận',
        'draft' => 'Đã nhập (nháp)',
        'submitted' => 'Chờ kiểm tra',
        'returned' => 'Cần bổ sung',
        'confirmed' => 'Đã xác nhận',
    ];

    public function __construct(private readonly ProjectWorkflowV2Service $workflow) {}

    /* ------------------------------------------------------------------ Dữ liệu */

    /** @return array<string, mixed> */
    public function emptyValues(): array
    {
        return [
            'complaint' => null,
            'complaint_note' => '',
            'hse' => array_fill_keys(array_keys(ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS), null),
            'hse_note' => '',
            'grid_tested' => null,
            'app_handover' => null,
            'evn_note' => '',
        ];
    }

    /** Trạng thái lưu trong dữ liệu bước; null nếu bước chưa từng dùng luồng mới. */
    public function stored(object $row): ?array
    {
        $data = $this->workflow->decodeData($row->data ?? null);
        $state = $data[self::KEY] ?? null;

        return is_array($state) ? $state : null;
    }

    /** @return array<string, mixed> */
    public function state(object $row): array
    {
        $state = $this->stored($row) ?? [];
        $values = array_replace_recursive($this->emptyValues(), (array) ($state['values'] ?? []));

        return [
            'values' => $values,
            'status' => (string) ($state['status'] ?? 'draft'),
            'version' => (int) ($state['version'] ?? 0),
            'updated_by' => $state['updated_by'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
            'submitted' => $state['submitted'] ?? null,
            'returned' => $state['returned'] ?? null,
            'confirmations' => (array) ($state['confirmations'] ?? []),
            // Xác nhận đã hết hiệu lực (sửa nội dung/minh chứng, trả lại…): chỉ để tra cứu, không bao giờ tính KPI.
            'confirmation_history' => (array) ($state['confirmation_history'] ?? []),
            'exists' => $state !== [],
        ];
    }

    /**
     * Quyết định "công trình cần đấu nối điện lực" ghi ở bước Hồ sơ pháp lý.
     * Chỉ nhận khi có dấu ghi nhận quyết định (người/giờ do server ghi khi chọn Có/Không), hoặc giá trị TRUE của
     * dữ liệu cũ (chỉ có được bằng cách tick thật). FALSE không có dấu ghi nhận có thể do form cũ tự ép → chưa xác định.
     *
     * @return array{required: ?bool, by: ?int, at: ?string, label: string}
     */
    public function gridDecision(int $siteId): array
    {
        $legal = DB::table('project_workflow_steps')->where('site_id', $siteId)->where('step_code', 'legal')->first();
        $data = $legal ? $this->workflow->decodeData($legal->data ?? null) : [];
        $value = $data['requires_grid_connection'] ?? null;
        $decided = ! empty($data['requires_grid_connection_decided_at']);

        if ($decided && $value !== null) {
            return ['required' => (bool) $value, 'by' => (int) ($data['requires_grid_connection_decided_by'] ?? 0) ?: null,
                'at' => (string) $data['requires_grid_connection_decided_at'], 'label' => (bool) $value ? 'Có — cần đấu nối điện lực' : 'Không cần đấu nối điện lực'];
        }
        if ($value === true) {
            return ['required' => true, 'by' => null, 'at' => null, 'label' => 'Có — cần đấu nối điện lực (ghi nhận ở dữ liệu cũ)'];
        }

        return ['required' => null, 'by' => null, 'at' => null, 'label' => 'Chưa xác định — cần ghi ở bước Hồ sơ pháp lý'];
    }

    /** Số lần bị "Trả lại" ở bước Thi công / Nghiệm thu (căn cứ "đạt lần đầu"). */
    public function revisionCount(int $siteId): int
    {
        if (! Schema::hasTable('project_workflow_events')) {
            return 0;
        }
        $stepIds = DB::table('project_workflow_steps')->where('site_id', $siteId)
            ->whereIn('step_code', ['construction', 'acceptance'])->pluck('id');

        return $stepIds->isEmpty() ? 0 : (int) DB::table('project_workflow_events')
            ->whereIn('workflow_step_id', $stepIds)->where('action', 'step_revision_requested')->count();
    }

    /** File minh chứng hiện có theo nhóm (bản mới nhất từng file, kèm phiên bản). */
    public function evidenceDocuments(int $stepId, string $group): Collection
    {
        return DB::table('project_workflow_documents')
            ->where('workflow_step_id', $stepId)
            ->whereIn('document_code', self::EVIDENCE[$group] ?? [])
            ->orderBy('id')
            ->get(['id', 'document_code', 'version', 'original_name', 'title', 'path', 'created_at']);
    }

    public function fingerprint(string $group, array $values, object $row): string
    {
        $siteId = (int) $row->site_id;
        $docs = $this->evidenceDocuments((int) $row->id, $group)->map(fn ($d) => $d->id.':'.$d->version)->all();
        $payload = match ($group) {
            'quality' => [$values['complaint'], trim((string) $values['complaint_note']), $this->revisionCount($siteId)],
            'hse' => [$values['hse'], trim((string) $values['hse_note'])],
            'evn' => [$values['grid_tested'], $values['app_handover'], trim((string) $values['evn_note']), $this->gridDecision($siteId)['required']],
            default => [],
        };

        return sha1(json_encode([$group, $payload, $docs], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Lý do nhóm chưa đủ để xác nhận (rỗng = đủ). Không tự coi mục bỏ trống là đạt hay không đạt.
     *
     * @return array<int, string>
     */
    public function groupMissing(string $group, array $values, object $row): array
    {
        $missing = [];
        if ($group === 'quality') {
            if ($values['complaint'] === null) {
                $missing[] = 'Chưa ghi nhận khách hàng có phản ánh hay không';
            } elseif ($values['complaint'] === 'has' && trim((string) $values['complaint_note']) === '') {
                $missing[] = 'Có phản ánh nhưng chưa ghi nội dung';
            }
        }
        if ($group === 'hse') {
            foreach (ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS as $key => $label) {
                if (($values['hse'][$key] ?? null) === null) {
                    $missing[] = 'Chưa ghi nhận: '.$label;
                }
            }
        }
        if ($group === 'evn') {
            $grid = $this->gridDecision((int) $row->site_id)['required'];
            if ($grid === null) {
                $missing[] = 'Chưa xác định công trình có cần đấu nối điện lực (bước Hồ sơ pháp lý)';
            } elseif ($grid && $values['grid_tested'] === null) {
                $missing[] = 'Chưa ghi nhận kết quả đóng điện thử / hòa lưới EVN';
            }
            if ($values['app_handover'] === null) {
                $missing[] = 'Chưa ghi nhận kết quả cài đặt và bàn giao App giám sát';
            }
        }
        if ($this->evidenceDocuments((int) $row->id, $group)->isEmpty()) {
            $labels = collect(self::EVIDENCE[$group] ?? [])
                ->map(fn ($code) => (string) (config('project_workflow_v2.steps.acceptance.documents.'.$code.'.label') ?? $code))
                ->implode(' hoặc ');
            $missing[] = 'Chưa có file minh chứng ('.$labels.')';
        }

        return $missing;
    }

    /** Kết quả của nhóm theo khai báo đầy đủ: 'pass' | 'fail' | null (chưa đủ). */
    public function groupResult(string $group, array $values, object $row): ?string
    {
        if ($this->groupMissing($group, $values, $row) !== []) {
            return null;
        }

        return match ($group) {
            'quality' => ($values['complaint'] === 'none' && $this->revisionCount((int) $row->site_id) === 0) ? 'pass' : 'fail',
            'hse' => collect($values['hse'])->every(fn ($v) => $v === 'pass') ? 'pass' : 'fail',
            'evn' => ($values['app_handover'] === 'pass'
                && (! $this->gridDecision((int) $row->site_id)['required'] || $values['grid_tested'] === 'pass')) ? 'pass' : 'fail',
            default => null,
        };
    }

    /** Xác nhận còn hiệu lực của nhóm (vân tay hiện tại trùng lúc xác nhận); null nếu chưa có/không còn hiệu lực. */
    public function validConfirmation(string $group, array $state, object $row): ?array
    {
        $c = $state['confirmations'][$group] ?? null;
        if (! is_array($c) || empty($c['fingerprint'])) {
            return null;
        }

        return hash_equals((string) $c['fingerprint'], $this->fingerprint($group, $state['values'], $row)) ? $c : null;
    }

    /** Nhóm đang ở lần "Trả bổ sung" gần nhất và chưa được gửi lại. */
    public function isReturned(string $group, array $state): bool
    {
        return $state['status'] === 'returned'
            && (empty($state['returned']['groups']) || in_array($group, (array) $state['returned']['groups'], true));
    }

    /**
     * Nhóm đang chờ kiểm tra: nội dung + minh chứng hiện tại đúng bằng bản đã gửi và nhóm không bị trả bổ sung.
     * Tính theo từng nhóm: sửa/trả một nhóm không làm các nhóm khác mất trạng thái chờ kiểm tra.
     */
    public function awaitingReview(string $group, array $state, object $row, ?string $fingerprint = null): bool
    {
        $submittedFp = (string) ($state['submitted']['fingerprints'][$group] ?? '');
        if ($submittedFp === '' || $this->isReturned($group, $state)) {
            return false;
        }

        return hash_equals($submittedFp, $fingerprint ?? $this->fingerprint($group, $state['values'], $row));
    }

    /**
     * Trạng thái hiển thị/tính KPI của từng nhóm.
     *
     * @return array<string, array<string, mixed>>
     */
    public function summary(object $row): array
    {
        $state = $this->state($row);
        $out = [];
        foreach (array_keys(self::GROUPS) as $group) {
            $confirmation = $this->validConfirmation($group, $state, $row);
            $stale = ! $confirmation && (! empty($state['confirmations'][$group]) || ! empty($state['confirmation_history'][$group]));
            $fingerprint = $this->fingerprint($group, $state['values'], $row);

            $status = match (true) {
                $confirmation !== null => 'confirmed',
                $this->isReturned($group, $state) => 'returned',
                $this->awaitingReview($group, $state, $row, $fingerprint) => 'submitted',
                $this->hasAnyValue($group, $state['values']) => 'draft',
                default => 'empty',
            };

            $out[$group] = [
                'label' => self::GROUPS[$group],
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status],
                'missing' => $this->groupMissing($group, $state['values'], $row),
                'result' => $confirmation['result'] ?? null,
                'declared_result' => $this->groupResult($group, $state['values'], $row),
                'confirmation' => $confirmation,
                'stale' => $stale,
                'fingerprint' => $fingerprint,
            ];
        }

        return $out;
    }

    private function hasAnyValue(string $group, array $values): bool
    {
        return match ($group) {
            'quality' => $values['complaint'] !== null || trim((string) $values['complaint_note']) !== '',
            'hse' => collect($values['hse'])->contains(fn ($v) => $v !== null) || trim((string) $values['hse_note']) !== '',
            'evn' => $values['grid_tested'] !== null || $values['app_handover'] !== null || trim((string) $values['evn_note']) !== '',
            default => false,
        };
    }

    /* ------------------------------------------------------------------ Quyền */

    public function isActiveAssignee(User $user, object $row): bool
    {
        return DB::table('project_workflow_assignments')
            ->where('workflow_step_id', $row->id)->where('user_id', $user->id)->where('is_active', 1)->exists();
    }

    /** Người được giao (còn hiệu lực) nhập/gửi; Admin được làm thay nhưng ghi dấu ngoại lệ. */
    public function canDeclare(User $user, object $row): bool
    {
        return $this->isActiveAssignee($user, $row) || $this->workflow->isAdmin($user);
    }

    /** Bước đã duyệt nghiệm thu: khóa sửa khai báo/minh chứng KPI với người không phải Admin. */
    public function isLocked(object $row): bool
    {
        return (string) $row->status === 'approved';
    }

    /**
     * Kỳ KPI đã chốt (khác khóa bước): nghiệm thu đã duyệt trong tháng M và người chịu KPI của công trình (được giao
     * Khảo sát/Thi công/Nghiệm thu hoặc kỹ sư chính) đã có phiếu KPI kỹ thuật tháng M được duyệt, hoặc dòng lương HR
     * tháng M thuộc kỳ đã duyệt dùng bộ KPI kỹ thuật. Kỳ HR nhập từ Excel không đọc nguồn kỹ thuật nên không khóa.
     * Khóa kỳ áp dụng cho MỌI người, kể cả Admin.
     *
     * @return array{month: string, reasons: array<int, string>}|null
     */
    public function kpiSourceLock(int $siteId): ?array
    {
        $row = DB::table('project_workflow_steps')->where('site_id', $siteId)->where('step_code', 'acceptance')->first();
        if (! $row || ! $this->isLocked($row) || empty($row->approved_at)) {
            return null;
        }
        $month = Carbon::parse($row->approved_at)->format('Y-m');
        $userIds = DB::table('project_workflow_assignments as a')
            ->join('project_workflow_steps as w', 'w.id', '=', 'a.workflow_step_id')
            ->where('w.site_id', $siteId)->whereIn('w.step_code', ['survey', 'construction', 'acceptance'])
            ->pluck('a.user_id')
            ->push((int) (DB::table('sites')->where('id', $siteId)->value('lead_engineer_id') ?? 0))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($userIds->isEmpty()) {
            return null;
        }

        $reasons = [];
        if (Schema::hasTable('technical_kpi_payrolls') && DB::table('technical_kpi_payrolls')
            ->whereIn('user_id', $userIds)->where('payroll_month', $month)->where('status', 'approved')->exists()) {
            $reasons[] = 'phiếu lương KPI kỹ thuật tháng '.$month.' đã duyệt';
        }
        if (Schema::hasTable('hr_payroll_periods') && Schema::hasColumn('hr_payroll_lines', 'kpi_template')) {
            $periods = DB::table('hr_payroll_periods')->where('payroll_month', $month)->where('status', 'approved')->get();
            foreach ($periods as $period) {
                if (\App\Services\Hr\Payroll\PayrollGenerator::isExcelSnapshot($period)) {
                    continue;
                }
                if (DB::table('hr_payroll_lines')->where('period_id', $period->id)->whereIn('user_id', $userIds)
                    ->where('kpi_template', \App\Services\Hr\Kpi\HrKpiEvaluator::TECHNICAL)->exists()) {
                    $reasons[] = 'bảng lương nhân sự tháng '.$month.' đã duyệt có dòng dùng KPI kỹ thuật';
                }
            }
        }

        return $reasons === [] ? null : ['month' => $month, 'reasons' => $reasons];
    }

    /** Giữ tên cũ: kỳ KPI của bước nghiệm thu này đã chốt. */
    public function periodLocked(object $row): bool
    {
        return $this->kpiSourceLock((int) $row->site_id) !== null;
    }

    /** Chặn TRƯỚC khi ghi bất kỳ dữ liệu/file nào có thể đổi nguồn KPI đã chốt (không có ngoại lệ Admin). */
    public function assertSourceUnlocked(int $siteId): void
    {
        $lock = $this->kpiSourceLock($siteId);
        abort_if($lock !== null, 422, 'Kỳ KPI tháng '.Carbon::createFromFormat('Y-m', $lock['month'] ?? date('Y-m'))->format('m/Y')
            .' đã chốt ('.implode('; ', (array) ($lock['reasons'] ?? [])).'). Không sửa nguồn KPI của công trình này — '
            .'muốn điều chỉnh phải mở lại kỳ theo quy trình duyệt lương hiện có.');
    }

    /**
     * Vô hiệu BỀN VỮNG theo nhóm: nhóm nào có nội dung/minh chứng/lịch sử trả lại/quyết định đấu nối khác với lúc
     * xác nhận thì chuyển xác nhận sang lịch sử; khác với lúc gửi thì hủy lượt gửi của nhóm đó. Đổi về giá trị cũ
     * sau đó không làm xác nhận/lượt gửi sống lại — phải gửi và xác nhận lại. Nhóm không đổi giữ nguyên.
     *
     * @return array<string, array<int, string>> nhóm => [confirmation|submission]
     */
    public function syncConfirmations(int $siteId, ?User $actor, string $cause): array
    {
        $row = DB::table('project_workflow_steps')->where('site_id', $siteId)->where('step_code', 'acceptance')->lockForUpdate()->first();
        if (! $row) {
            return [];
        }
        $data = $this->workflow->decodeData($row->data ?? null);
        $stored = $data[self::KEY] ?? null;
        if (! is_array($stored)) {
            return [];
        }
        $values = $this->state($row)['values'];
        $at = now()->toDateTimeString();
        $changed = [];
        foreach (array_keys(self::GROUPS) as $group) {
            $current = $this->fingerprint($group, $values, $row);
            $confirmation = $stored['confirmations'][$group] ?? null;
            if (is_array($confirmation) && ! hash_equals((string) ($confirmation['fingerprint'] ?? ''), $current)) {
                $stored['confirmation_history'][$group][] = $confirmation + [
                    'invalidated_at' => $at, 'invalidated_by' => $actor?->id, 'invalidated_cause' => $cause,
                ];
                unset($stored['confirmations'][$group]);
                $changed[$group][] = 'confirmation';
            }
            $submitted = $stored['submitted']['fingerprints'][$group] ?? null;
            if ($submitted !== null && ! hash_equals((string) $submitted, $current)) {
                unset($stored['submitted']['fingerprints'][$group]);
                $stored['submitted']['voided'][$group] = ['at' => $at, 'cause' => $cause];
                $changed[$group][] = 'submission';
            }
        }
        if ($changed === []) {
            return [];
        }

        $data[self::KEY] = $stored;
        DB::table('project_workflow_steps')->where('id', $row->id)->update([
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
        $site = Site::query()->find($siteId);
        if ($site) {
            $this->workflow->recordEvent($site, $row, $actor, 'acceptance_kpi_confirmation_invalidated', null, null,
                'Nguồn KPI nghiệm thu thay đổi ('.$cause.'): '.collect(array_keys($changed))->map(fn ($g) => self::GROUPS[$g])->implode(', ')
                .' cần gửi và xác nhận lại.', ['cause' => $cause, 'groups' => $changed]);
        }

        return $changed;
    }

    /**
     * Người kiểm tra: Trưởng phòng KT/Admin theo cấu hình bước, KHÔNG thuộc nhóm thực hiện và không phải người
     * gửi/nhập bản đang kiểm tra. Admin giữ ngoại lệ sẵn có (được phép nhưng ghi dấu).
     *
     * @return array{0: bool, 1: bool, 2: ?string} [được phép, là ngoại lệ Admin, lý do chặn]
     */
    public function reviewAccess(User $user, object $row, array $state): array
    {
        $definition = $this->workflow->validateStepCode('acceptance');
        if (! $this->workflow->canAssign($user, $definition)) {
            return [false, false, 'Chỉ Trưởng phòng Kỹ thuật hoặc Admin được kiểm tra kết quả KPI nghiệm thu.'];
        }
        $involved = $this->isActiveAssignee($user, $row)
            || (int) ($state['submitted']['by'] ?? 0) === (int) $user->id
            || (int) ($state['updated_by'] ?? 0) === (int) $user->id;
        if ($involved) {
            return $this->workflow->isAdmin($user)
                ? [true, true, null]
                : [false, false, 'Người thực hiện/nhập kết quả không được tự xác nhận kết quả của mình.'];
        }

        return [true, false, null];
    }

    /* ------------------------------------------------------------------ Ghi */

    /**
     * Lưu nháp hoặc gửi kiểm tra. $input theo cấu trúc emptyValues(); khóa không gửi lên = giữ nguyên giá trị cũ,
     * giá trị rỗng = xóa về "chưa ghi nhận" (lưu null). Không nhận người/giờ xác nhận từ client.
     */
    public function saveDeclaration(Site $site, User $user, array $input, bool $submit): string
    {
        return DB::transaction(function () use ($site, $user, $input, $submit): string {
            $row = $this->workflow->stepRow($site, 'acceptance', true);
            abort_unless($this->canDeclare($user, $row), 403, 'Chỉ người được giao bước Nghiệm thu được nhập kết quả KPI.');
            $isAdmin = $this->workflow->isAdmin($user);
            abort_if($this->isLocked($row) && ! $isAdmin, 422, 'Bước nghiệm thu đã được duyệt; kết quả KPI đã khóa.');
            // Khóa kỳ: kể cả Admin, kiểm tra trước khi ghi.
            $this->assertSourceUnlocked((int) $site->id);

            $data = $this->workflow->decodeData($row->data ?? null);
            $state = $this->state($row);
            $before = $state['values'];
            $values = $this->mergeInput($before, $input);
            $changed = $values !== $before;

            $stored = (array) ($data[self::KEY] ?? []);
            $stored['values'] = $values;
            if ($changed || ! $state['exists']) {
                $stored['version'] = $state['version'] + 1;
                $stored['updated_by'] = (int) $user->id;
                $stored['updated_at'] = now()->toDateTimeString();
                $stored['status'] = 'draft';
            }
            $stored['status'] ??= 'draft';
            $stored['confirmations'] = (array) ($stored['confirmations'] ?? []);

            if ($submit) {
                $fingerprints = [];
                foreach (array_keys(self::GROUPS) as $group) {
                    $fingerprints[$group] = $this->fingerprint($group, $values, $row);
                }
                $stored['status'] = 'submitted';
                $stored['submitted'] = [
                    'version' => (int) ($stored['version'] ?? 1),
                    'by' => (int) $user->id,
                    'at' => now()->toDateTimeString(),
                    'fingerprints' => $fingerprints,
                ];
            }

            $data[self::KEY] = $stored;
            DB::table('project_workflow_steps')->where('id', $row->id)->update([
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
            // R1: nhóm đổi nội dung → xác nhận/lượt gửi cũ của nhóm đó hết hiệu lực vĩnh viễn (nhóm khác giữ nguyên).
            $this->syncConfirmations((int) $site->id, $user, $submit ? 'declaration_submitted' : 'declaration_saved');

            $missing = [];
            foreach (array_keys(self::GROUPS) as $group) {
                foreach ($this->groupMissing($group, $values, $row) as $reason) {
                    $missing[] = self::GROUPS[$group].': '.$reason;
                }
            }
            $this->workflow->recordEvent($site, $row, $user, $submit ? 'acceptance_kpi_submitted' : 'acceptance_kpi_draft_saved',
                (string) $state['status'], (string) $stored['status'],
                $submit ? 'Gửi kết quả nghiệm thu KPI để Trưởng phòng kiểm tra.' : 'Lưu nháp kết quả nghiệm thu KPI.',
                ['version' => (int) ($stored['version'] ?? 0), 'values' => $values, 'missing' => $missing,
                    'admin_exception' => $isAdmin && ! $this->isActiveAssignee($user, $row)]);

            if ($submit) {
                return $missing === []
                    ? 'Đã gửi kết quả nghiệm thu để Trưởng phòng kiểm tra.'
                    : 'Đã gửi kiểm tra. Còn thiếu: '.implode('; ', $missing).'. Mục thiếu chưa được xác nhận và chưa tính KPI.';
            }

            return 'Đã lưu nháp kết quả nghiệm thu. Nháp chưa được tính KPI cho tới khi gửi và được xác nhận.';
        });
    }

    /** @param  array<string, string>  $expected  vân tay từng nhóm mà người kiểm tra đã xem trên màn hình */
    public function confirm(Site $site, User $user, array $groups, array $expected, ?string $note): string
    {
        return DB::transaction(function () use ($site, $user, $groups, $expected, $note): string {
            $row = $this->workflow->stepRow($site, 'acceptance', true);
            $state = $this->state($row);
            [$allowed, $adminException, $reason] = $this->reviewAccess($user, $row, $state);
            abort_unless($allowed, 403, (string) $reason);
            $this->assertSourceUnlocked((int) $site->id);

            $data = $this->workflow->decodeData($row->data ?? null);
            $stored = (array) ($data[self::KEY] ?? []);
            $done = [];
            foreach ($groups as $group) {
                abort_unless(isset(self::GROUPS[$group]), 422, 'Nhóm kết quả không hợp lệ.');
                $current = $this->fingerprint($group, $state['values'], $row);
                $submittedFp = (string) ($state['submitted']['fingerprints'][$group] ?? '');
                abort_if($submittedFp === '', 422, self::GROUPS[$group].': chưa được gửi kiểm tra.');
                abort_if($this->isReturned($group, $state), 422, self::GROUPS[$group].': đang trả bổ sung — chờ người thực hiện cập nhật và gửi lại.');
                abort_unless(hash_equals($submittedFp, $current), 409,
                    self::GROUPS[$group].': nội dung hoặc minh chứng đã thay đổi sau khi gửi — cần người thực hiện gửi lại.');
                abort_unless(hash_equals((string) ($expected[$group] ?? ''), $current), 409,
                    self::GROUPS[$group].': nội dung vừa thay đổi trong lúc bạn kiểm tra. Tải lại trang để xem bản mới.');
                $missing = $this->groupMissing($group, $state['values'], $row);
                abort_unless($missing === [], 422, self::GROUPS[$group].' chưa đủ để xác nhận: '.implode('; ', $missing).'.');

                $stored['confirmations'][$group] = [
                    'fingerprint' => $current,
                    'result' => $this->groupResult($group, $state['values'], $row),
                    'version' => (int) ($state['submitted']['version'] ?? $state['version']),
                    'by' => (int) $user->id,
                    'at' => now()->toDateTimeString(),
                    'admin_exception' => $adminException,
                    'note' => $note ? trim($note) : null,
                ];
                $done[] = $group;
            }
            abort_if($done === [], 422, 'Chọn ít nhất một nhóm kết quả để xác nhận.');

            $data[self::KEY] = $stored;
            DB::table('project_workflow_steps')->where('id', $row->id)->update([
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
            $this->workflow->recordEvent($site, $row, $user, 'acceptance_kpi_confirmed', 'submitted', 'confirmed',
                $note ?: 'Xác nhận kết quả KPI nghiệm thu: '.collect($done)->map(fn ($g) => self::GROUPS[$g])->implode(', ').'.',
                ['groups' => $done, 'results' => collect($done)->mapWithKeys(fn ($g) => [$g => $stored['confirmations'][$g]['result']])->all(),
                    'version' => (int) ($state['submitted']['version'] ?? 0), 'values' => $state['values'], 'admin_exception' => $adminException]);

            return 'Đã xác nhận '.count($done).' nhóm kết quả KPI. Xác nhận này không thay cho bước duyệt nghiệm thu.';
        });
    }

    public function returnForRevision(Site $site, User $user, string $reason, array $groups): string
    {
        return DB::transaction(function () use ($site, $user, $reason, $groups): string {
            $row = $this->workflow->stepRow($site, 'acceptance', true);
            $state = $this->state($row);
            [$allowed, $adminException, $deny] = $this->reviewAccess($user, $row, $state);
            abort_unless($allowed, 403, (string) $deny);
            $this->assertSourceUnlocked((int) $site->id);
            abort_if($this->isLocked($row), 422, 'Bước nghiệm thu đã được duyệt; không trả bổ sung kết quả KPI được.');
            $groups = array_values(array_intersect(array_keys(self::GROUPS), $groups));
            $target = $groups === [] ? array_keys(self::GROUPS) : $groups;
            abort_unless(collect($target)->contains(fn ($g) => $this->awaitingReview($g, $state, $row)), 422,
                'Chỉ trả bổ sung khi nhóm được chọn đang chờ kiểm tra.');

            $data = $this->workflow->decodeData($row->data ?? null);
            $stored = (array) ($data[self::KEY] ?? []);
            foreach ($target as $group) {
                // Giữ lịch sử: xác nhận của nhóm bị trả chuyển sang lịch sử thay vì xóa.
                if (! empty($stored['confirmations'][$group])) {
                    $stored['confirmation_history'][$group][] = $stored['confirmations'][$group] + [
                        'invalidated_at' => now()->toDateTimeString(), 'invalidated_by' => (int) $user->id, 'invalidated_cause' => 'returned',
                    ];
                }
                unset($stored['confirmations'][$group]);
            }
            $stored['status'] = 'returned';
            $stored['returned'] = ['by' => (int) $user->id, 'at' => now()->toDateTimeString(), 'reason' => trim($reason), 'groups' => $groups];
            $data[self::KEY] = $stored;
            DB::table('project_workflow_steps')->where('id', $row->id)->update([
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
            $this->workflow->recordEvent($site, $row, $user, 'acceptance_kpi_returned', 'submitted', 'returned', trim($reason),
                ['groups' => $groups, 'version' => (int) ($state['submitted']['version'] ?? 0), 'values' => $state['values'], 'admin_exception' => $adminException]);

            return 'Đã trả bổ sung kết quả KPI nghiệm thu cho người thực hiện.';
        });
    }

    /**
     * Gộp input form vào giá trị hiện tại. Chỉ khóa có trong input mới đổi; '' = xóa lựa chọn (null).
     *
     * @return array<string, mixed>
     */
    private function mergeInput(array $current, array $input): array
    {
        $result = $current;
        $choice = fn ($value, array $allowed) => in_array((string) $value, $allowed, true) ? (string) $value : null;

        if (array_key_exists('complaint', $input)) {
            $result['complaint'] = $choice($input['complaint'], ['none', 'has']);
        }
        if (array_key_exists('complaint_note', $input)) {
            $result['complaint_note'] = trim((string) $input['complaint_note']);
        }
        if (array_key_exists('hse', $input) && is_array($input['hse'])) {
            foreach (array_keys(ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS) as $key) {
                if (array_key_exists($key, $input['hse'])) {
                    $result['hse'][$key] = $choice($input['hse'][$key], ['pass', 'fail']);
                }
            }
        }
        foreach (['hse_note', 'evn_note'] as $key) {
            if (array_key_exists($key, $input)) {
                $result[$key] = trim((string) $input[$key]);
            }
        }
        foreach (['grid_tested', 'app_handover'] as $key) {
            if (array_key_exists($key, $input)) {
                $result[$key] = $choice($input[$key], ['pass', 'fail']);
            }
        }
        if ($result['complaint'] !== 'has') {
            // Không có phản ánh thì không giữ nội dung phản ánh cũ.
            $result['complaint_note'] = $result['complaint'] === 'none' ? '' : $result['complaint_note'];
        }

        return $result;
    }

    /* ------------------------------------------------------------------ KPI */

    /**
     * Kết quả đã xác nhận của từng nhóm cho bộ chấm KPI.
     *
     * @return array<string, array{result: ?string, by: ?int, at: ?string, reason: ?string, admin_exception: bool}>
     */
    public function confirmedForKpi(object $row): array
    {
        $out = [];
        foreach ($this->summary($row) as $group => $s) {
            $c = $s['confirmation'];
            $reason = null;
            if (! $c) {
                $reason = match ($s['status']) {
                    'empty' => 'Chưa nhập kết quả '.mb_strtolower(self::GROUPS[$group]).' tại Nghiệm thu',
                    'draft' => $s['stale'] ? 'Nội dung/minh chứng đã đổi sau khi xác nhận — chờ gửi lại và kiểm tra' : 'Đã nhập nháp, chưa gửi kiểm tra',
                    'submitted' => 'Đã gửi, chờ Trưởng phòng kiểm tra',
                    'returned' => 'Bị trả bổ sung, chờ người thực hiện cập nhật',
                    default => 'Chưa xác nhận',
                };
            }
            $out[$group] = [
                'result' => $c['result'] ?? null,
                'by' => isset($c['by']) ? (int) $c['by'] : null,
                'at' => $c['at'] ?? null,
                'reason' => $reason,
                'admin_exception' => (bool) ($c['admin_exception'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Ghi chú điểm tại màn hình Nghiệm thu: trọng số lấy từ bộ chấm đúng kỳ (tháng duyệt nghiệm thu, hoặc tháng hiện
     * tại khi chưa duyệt). Không tự tính điểm riêng: chỉ nói tiêu chí nào đã/chưa có căn cứ và trọng số của kỳ.
     *
     * @return array{month: string, weights: array<string, ?float>, names: array<string, string>, approved: bool}
     */
    public function kpiPeriod(object $row): array
    {
        $approved = (string) $row->status === 'approved' && ! empty($row->approved_at);
        $month = $approved ? Carbon::parse($row->approved_at)->format('Y-m') : now()->format('Y-m');
        $evaluator = app(KpiStandardEvaluator::class)->useConfig(TechnicalKpiConfig::getActiveForDate($month.'-01'), $month);
        $byCode = collect($evaluator->criteria())->keyBy('code');
        $weights = [];
        $names = [];
        foreach (self::CRITERION as $group => $code) {
            $w = $byCode->get($code)['weight'] ?? null;
            $weights[$group] = $w === null ? null : (float) $w;
            $names[$group] = (string) ($byCode->get($code)['name'] ?? self::GROUPS[$group]);
        }

        return ['month' => $month, 'weights' => $weights, 'names' => $names, 'approved' => $approved];
    }
}
