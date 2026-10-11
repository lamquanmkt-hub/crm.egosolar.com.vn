<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Manager records a reviewed percentage; this is not automatic warehouse reconciliation. */
final class ManualMaterialKpiService
{
    public const KEY = 'kpi_material_manual';
    public const START_DATE = '2026-10-08';

    public function __construct(private readonly ProjectWorkflowV2Service $workflow, private readonly AcceptanceKpiService $acceptance) {}

    public function canManage(User $user): bool
    {
        return $this->workflow->canAssign($user, $this->workflow->validateStepCode('acceptance'));
    }

    public function state(object $row): array
    {
        $data = $this->workflow->decodeData($row->data ?? null);

        return array_replace(['percent' => null, 'note' => '', 'version' => 0, 'status' => 'empty',
            'updated_by' => null, 'updated_at' => null, 'confirmation' => null, 'history' => []],
            (array) ($data[self::KEY] ?? []));
    }

    public function documents(object $row)
    {
        return DB::table('project_workflow_documents')->where('workflow_step_id', $row->id)
            ->where('document_code', 'material_reconciliation')->orderBy('id')->get();
    }

    public function fingerprint(object $row, ?array $state = null): string
    {
        $state ??= $this->state($row);

        return hash('sha256', json_encode([$state['percent'], $state['note'], $state['version'],
            $this->documents($row)->map(fn ($doc) => [$doc->id, $doc->version])->all()], JSON_UNESCAPED_UNICODE));
    }

    public function result(object $row): array
    {
        $state = $this->state($row);
        $confirmation = $state['confirmation'];
        $valid = is_array($confirmation) && $state['status'] === 'confirmed'
            && hash_equals((string) ($confirmation['fingerprint'] ?? ''), $this->fingerprint($row));
        $status = $valid ? 'confirmed' : ($confirmation ? 'stale' : $state['status']);
        $who = $valid ? DB::table('users')->where('id', $confirmation['by'])->value('name') : null;
        $missing = $valid ? '' : match ($status) {
            'draft' => 'Manager mới lưu nháp vật tư, chưa xác nhận kết quả',
            'stale' => 'Bảng đối chiếu vật tư đã thay đổi; manager cần xác nhận lại',
            default => 'Chưa có kết quả đối chiếu vật tư được manager xác nhận',
        };

        return ['percent' => $valid ? (float) $state['percent'] : null, 'status' => $status,
            'missing' => $missing, 'confirmed' => $valid ? 'Nguồn: Manager nhập tay · '.($who ?: 'Người dùng đã xóa')
                .' · '.$confirmation['at'] : 'Nguồn: Manager nhập tay · '.$missing];
    }

    public function requiresConfirmation(object $row): bool
    {
        $data = $this->workflow->decodeData($row->data ?? null);

        // Preserve older source records; new acceptance approvals use the explicit manual workflow.
        return array_key_exists(self::KEY, $data)
            || substr((string) ($row->approved_at ?? ''), 0, 10) >= self::START_DATE;
    }

    public function save(Site $site, User $actor, ?float $percent, string $note, bool $confirm,
        ?int $expectedVersion = null, ?string $expectedFingerprint = null): string
    {
        abort_unless($this->canManage($actor), 403, 'Chỉ Trưởng phòng Kỹ thuật hoặc Admin được nhập và xác nhận vật tư.');

        return DB::transaction(function () use ($site, $actor, $percent, $note, $confirm, $expectedVersion, $expectedFingerprint): string {
            $row = $this->workflow->stepRow($site, 'acceptance', true);
            $this->acceptance->assertSourceUnlocked((int) $site->id);
            $state = $this->state($row);
            abort_if($expectedVersion !== null && $expectedVersion !== (int) $state['version'], 409,
                'Kết quả vật tư đã được người khác sửa. Tải lại trang trước khi lưu.');
            abort_if($expectedFingerprint !== null && ! hash_equals($this->fingerprint($row), $expectedFingerprint), 409,
                'Kết quả hoặc minh chứng vật tư đã thay đổi. Tải lại trang để kiểm tra.');
            abort_if($percent !== null && (! is_finite($percent) || $percent < 0 || $percent > 1000), 422, '% sai lệch vật tư không hợp lệ.');
            $note = trim($note);
            if ($confirm) {
                abort_if($percent === null, 422, 'Cần nhập % sai lệch vật tư trước khi xác nhận.');
                abort_if($note === '', 422, 'Cần ghi cách tính hoặc căn cứ đối chiếu vật tư.');
                abort_if($this->documents($row)->isEmpty(), 422, 'Cần tải Bảng quyết toán vật tư ở hồ sơ phía trên trước khi xác nhận.');
            }
            if (is_array($state['confirmation'])) {
                $state['history'][] = $state['confirmation'] + ['invalidated_at' => now()->toDateTimeString(), 'invalidated_by' => $actor->id];
            }
            $state = array_replace($state, ['percent' => $percent, 'note' => $note, 'version' => (int) $state['version'] + 1,
                'status' => $confirm ? 'confirmed' : 'draft', 'updated_by' => (int) $actor->id,
                'updated_at' => now()->toDateTimeString(), 'confirmation' => null]);
            if ($confirm) {
                $state['confirmation'] = ['fingerprint' => $this->fingerprint($row, $state), 'by' => (int) $actor->id,
                    'at' => now()->toDateTimeString(), 'version' => $state['version'], 'percent' => $percent, 'note' => $note];
            }
            $data = $this->workflow->decodeData($row->data ?? null);
            $data[self::KEY] = $state;
            // Compatibility fields never bypass the explicit draft/confirmation result above.
            $data['kpi_material_deviation_percent'] = $percent;
            $data['kpi_material_updated_by'] = (int) $actor->id;
            $data['kpi_material_updated_at'] = $state['updated_at'];
            DB::table('project_workflow_steps')->where('id', $row->id)->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            $this->workflow->recordEvent($site, $row, $actor, $confirm ? 'material_kpi_confirmed' : 'material_kpi_draft_saved', null,
                $state['status'], $note, ['percent' => $percent, 'version' => $state['version'], 'source' => 'manager_manual']);

            return $confirm ? 'Đã xác nhận kết quả vật tư. KPI chỉ tính khi nghiệm thu được duyệt và hồ sơ khảo sát đầy đủ.'
                : 'Đã lưu nháp vật tư; chưa được tính KPI cho tới khi manager xác nhận.';
        });
    }

    /** File changes invalidate confirmation permanently, even if the old file is restored later. */
    public function invalidateDocuments(Site $site, User $actor): void
    {
        $row = $this->workflow->stepRow($site, 'acceptance', true);
        $data = $this->workflow->decodeData($row->data ?? null);
        if (! isset($data[self::KEY]['confirmation'])) {
            return;
        }
        $state = $this->state($row);
        $state['history'][] = $state['confirmation'] + ['invalidated_at' => now()->toDateTimeString(), 'invalidated_by' => $actor->id,
            'cause' => 'material_reconciliation_uploaded'];
        $state['confirmation'] = null;
        $state['status'] = 'draft';
        $state['version']++;
        $data[self::KEY] = $state;
        DB::table('project_workflow_steps')->where('id', $row->id)->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        $this->workflow->recordEvent($site, $row, $actor, 'material_kpi_confirmation_invalidated', 'confirmed', 'draft', 'Minh chứng vật tư thay đổi; cần xác nhận lại.');
    }
}
