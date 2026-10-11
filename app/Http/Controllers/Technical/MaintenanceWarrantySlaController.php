<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use App\Support\EgoCompanyLock;
use App\Support\SchemaCache;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ghi mốc SLA bảo hành cho KPI kỹ thuật KS06 ngay trên phiếu bảo trì/bảo hành đang có.
 *
 *  - reported:  thời điểm khách báo/tiếp nhận thông tin (người tiếp nhận nhập; sửa lại chỉ Trưởng phòng).
 *  - responded: phản hồi khách lần đầu — ghi đúng thời điểm bấm, bởi người được phân công hoặc Trưởng phòng.
 *  - verify:    Trưởng phòng/Admin (không phải người thực hiện) xác nhận đã kiểm tra khiếu nại.
 * Sau khi xác nhận, các mốc bị khóa để kết quả KPI không đổi âm thầm.
 */
class MaintenanceWarrantySlaController extends Controller
{
    public const COMPLAINT_STATUSES = [
        'none' => 'Đã kiểm tra — không có khiếu nại',
        'has_complaint' => 'Có khiếu nại',
    ];

    public function store(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && SolarMaintenanceAccess::canViewAny($user) && self::inKpiScope($schedule), 403, 'Phiếu nằm ngoài phạm vi KPI được phép.');
        abort_unless(SchemaCache::hasColumn('solar_maintenance_schedules', 'first_responded_at'), 503, 'Chưa chạy migration mốc SLA bảo hành.');
        abort_unless(self::isWarrantyType((string) $schedule->type), 422, 'Phiếu không thuộc loại bảo hành tính KPI.');

        $data = $request->validate([
            'action' => ['required', Rule::in(['reported', 'responded', 'verify'])],
            'customer_reported_at' => ['required_if:action,reported', 'nullable', 'date', 'before_or_equal:now'],
            'first_responded_at' => ['nullable', 'date', 'before_or_equal:now'],
            'response_recording_reason' => ['required_with:first_responded_at', 'nullable', 'string', 'max:1000'],
            'complaint_status' => ['required_if:action,verify', 'nullable', Rule::in(array_keys(self::COMPLAINT_STATUSES))],
            'complaint_note' => ['required_if:complaint_status,has_complaint', 'nullable', 'string', 'max:5000'],
        ], [], [
            'customer_reported_at' => 'thời điểm khách báo',
            'complaint_status' => 'kết quả kiểm tra khiếu nại',
            'complaint_note' => 'nội dung khiếu nại',
        ]);

        return DB::transaction(function () use ($schedule, $user, $data, $request): RedirectResponse {
            $row = DB::table('solar_maintenance_schedules')->where('id', $schedule->id)->lockForUpdate()->first();
            abort_if(! $row || $row->deleted_at, 404);
            $schedule->setRawAttributes((array) $row, true);
            abort_unless(self::inKpiScope($schedule), 403, 'Phiếu nằm ngoài phạm vi KPI được phép.');
            abort_unless(self::isWarrantyType((string) $row->type), 422, 'Phiếu không thuộc loại bảo hành tính KPI.');
            abort_if($row->status === 'cancelled', 422, 'Phiếu đã hủy không được ghi mốc KPI.');
            abort_if(! empty($row->complaint_checked_at), 422, 'Phiếu đã được xác nhận KPI bảo hành; các mốc đã khóa.');

            $isManager = SolarMaintenanceAccess::isManager($user);
            $isAssignee = self::isAssignee($schedule, $user);
            $update = [];
            $message = '';

            switch ($data['action']) {
                case 'reported':
                    abort_unless($isManager || $isAssignee || (int) $row->created_by === (int) $user->id, 403);
                    abort_if(! empty($row->customer_reported_at) && ! $isManager, 422, 'Đã có thời điểm khách báo; chỉ Trưởng phòng được sửa.');
                    $reportedAt = Carbon::parse($data['customer_reported_at']);
                    foreach (['first_responded_at', 'execution_finished_at'] as $field) {
                        abort_if(! empty($row->{$field}) && $reportedAt->greaterThan(Carbon::parse($row->{$field})), 422, 'Giờ khách báo phải trước giờ phản hồi và hoàn tất.');
                    }
                    $update = [
                        'customer_reported_at' => $reportedAt->toDateTimeString(),
                        'customer_reported_by' => (int) $user->id,
                    ];
                    $message = 'Đã ghi thời điểm khách báo.';
                    break;

                case 'responded':
                    abort_unless($isManager || $isAssignee, 403);
                    abort_if(empty($row->customer_reported_at), 422, 'Cần ghi thời điểm khách báo trước khi ghi phản hồi.');
                    abort_if(! empty($row->first_responded_at), 422, 'Đã ghi phản hồi lần đầu.');
                    // Ghi giờ thực tế đã phản hồi qua điện thoại: chỉ quản lý được nhập lại, kèm lý do trong audit.
                    abort_if(! empty($data['first_responded_at']) && ! $isManager, 403, 'Chỉ Trưởng phòng được ghi giờ phản hồi thực tế kèm lý do.');
                    $respondedAt = ! empty($data['first_responded_at']) ? Carbon::parse($data['first_responded_at']) : now();
                    abort_if($respondedAt->lessThan(Carbon::parse($row->customer_reported_at)), 422, 'Giờ phản hồi phải từ thời điểm khách báo trở đi.');
                    abort_if(! empty($row->execution_finished_at) && $respondedAt->greaterThan(Carbon::parse($row->execution_finished_at)), 422, 'Giờ phản hồi phải trước giờ hoàn tất; quản lý cần ghi giờ phản hồi thực tế.');
                    $update = ['first_responded_at' => $respondedAt->toDateTimeString(), 'first_responded_by' => (int) $user->id];
                    $message = 'Đã ghi nhận phản hồi khách hàng lần đầu.';
                    break;

                case 'verify':
                    // Người thực hiện không tự xác nhận kết quả của mình (Admin được phép).
                    abort_unless($isManager && (! $isAssignee || SolarMaintenanceAccess::isAdmin($user)), 403, 'Chỉ Trưởng phòng Kỹ thuật/Admin không thuộc nhóm thực hiện được xác nhận.');
                    abort_unless($row->status === 'completed' && ! empty($row->execution_finished_at), 422, 'Chỉ xác nhận khiếu nại sau khi phiếu đã hoàn tất thực hiện.');
                    abort_if(empty($row->customer_reported_at) || empty($row->first_responded_at), 422, 'Cần đủ mốc khách báo và phản hồi trước khi xác nhận KPI.');
                    $reported = Carbon::parse($row->customer_reported_at);
                    $responded = Carbon::parse($row->first_responded_at);
                    $finished = Carbon::parse($row->execution_finished_at);
                    abort_if($responded->lessThan($reported) || $finished->lessThan($responded) || $finished->isFuture(), 422, 'Các mốc bảo hành chưa theo đúng thứ tự thực tế.');
                    $update = [
                        'complaint_status' => $data['complaint_status'],
                        'complaint_note' => $data['complaint_status'] === 'has_complaint' ? trim((string) $data['complaint_note']) : (trim((string) ($data['complaint_note'] ?? '')) ?: null),
                        'complaint_checked_by' => (int) $user->id,
                        'complaint_checked_at' => now()->toDateTimeString(),
                    ];
                    $message = 'Đã xác nhận kiểm tra khiếu nại cho KPI bảo hành.';
                    break;
            }

            DB::table('solar_maintenance_schedules')->where('id', $schedule->id)->update($update + ['updated_at' => now()]);
            if (SchemaCache::hasTable('solar_maintenance_audit_logs')) {
                DB::table('solar_maintenance_audit_logs')->insert([
                    'maintenance_schedule_id' => $schedule->id,
                    'action' => 'warranty_sla_'.$data['action'],
                    'old_values' => json_encode(array_intersect_key((array) $row, $update), JSON_UNESCAPED_UNICODE),
                    'new_values' => json_encode($update + (! empty($data['first_responded_at']) && $data['action'] === 'responded'
                        ? ['response_recording_reason' => trim((string) $data['response_recording_reason'])] : []), JSON_UNESCAPED_UNICODE),
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                    'created_at' => now(),
                ]);
            }

            return back()->with('success', $message);
        });
    }

    public static function inKpiScope(SolarMaintenanceSchedule $schedule): bool
    {
        $companyId = (int) $schedule->company_id;
        if (! $companyId && $schedule->site_id) {
            $companyId = (int) DB::table('sites')->where('id', $schedule->site_id)->value('company_id');
        }

        return $companyId === EgoCompanyLock::id();
    }

    public static function isAssignee(SolarMaintenanceSchedule $schedule, User $user): bool
    {
        return (int) $schedule->assigned_to === (int) $user->id || DB::table('solar_maintenance_assignees')
            ->where('maintenance_schedule_id', $schedule->id)->where('user_id', $user->id)->exists();
    }

    public static function isWarrantyType(string $type): bool
    {
        return in_array($type, (array) config('technical_kpi_standard.warranty.types', []), true);
    }
}
