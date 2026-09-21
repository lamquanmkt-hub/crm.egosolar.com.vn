<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SolarMaintenanceSchedule;
use App\Models\SolarWarrantyClaim;
use App\Models\User;
use App\Support\Synced\EgoCompanyScope;
use App\Support\SchemaCache;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SolarWarrantyClaimController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(SolarMaintenanceAccess::canCreateWarrantyClaim($request->user()), 403);

        $data = $request->validateWithBag('warrantyClaim', [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'maintenance_schedule_id' => ['nullable', 'integer', 'exists:solar_maintenance_schedules,id'],
            'serial_code' => ['nullable', 'string', 'max:190'],
            'claim_type' => ['required', Rule::in(array_keys(SolarWarrantyClaim::TYPES))],
            'priority' => ['required', Rule::in(array_keys(SolarWarrantyClaim::PRIORITIES))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'issue_description' => ['required', 'string', 'max:10000'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'is_chargeable' => ['nullable', 'boolean'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], [
            'site_id.required' => 'Vui lòng chọn công trình phát sinh sự cố.',
            'issue_description.required' => 'Vui lòng mô tả hiện tượng/sự cố.',
        ]);

        if (in_array($data['claim_type'], ['replacement', 'paid_repair'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'claim_type' => 'Đổi hàng bảo hành và Sửa chữa tính phí phải tạo tại Kỹ thuật → Bảo hành & Sửa chữa (quy trình riêng).',
            ])->errorBag('warrantyClaim');
        }

        try {
            $site = Site::withoutGlobalScopes()->findOrFail((int) $data['site_id']);
            $companyId = (int) ($site->company_id ?: EgoCompanyScope::currentId());
            $this->assertCompany($companyId);

            if (! empty($data['maintenance_schedule_id'])) {
                $schedule = SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail((int) $data['maintenance_schedule_id']);
                if ((int) $schedule->site_id !== (int) $site->id) {
                    throw ValidationException::withMessages([
                        'maintenance_schedule_id' => 'Lịch bảo trì đã chọn không thuộc công trình này.',
                    ]);
                }
            }

            $serial = $this->resolveSerial(trim((string) ($data['serial_code'] ?? '')));
            $this->assertSerialCompany($serial, $companyId, $request);
            $assignee = ! empty($data['assigned_to']) ? User::find((int) $data['assigned_to']) : null;
            if (SolarMaintenanceAccess::isTechnicianOnly($request->user())) {
                if ($assignee && (int) $assignee->id !== (int) $request->user()->id) {
                    throw ValidationException::withMessages([
                        'assigned_to' => 'Kỹ thuật viên chỉ được tự tiếp nhận phiếu cho chính mình.',
                    ]);
                }
                $assignee = $request->user();
            }
            if ($assignee && ! SolarMaintenanceAccess::isSelectableTechnician($assignee)) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Người phụ trách phải là nhân sự thuộc bộ phận kỹ thuật.',
                ]);
            }
            if ($assignee
                && SchemaCache::hasColumn('users', 'company_id')
                && $companyId > 0
                && (int) ($assignee->company_id ?? 0) > 0
                && (int) $assignee->company_id !== $companyId) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Người phụ trách không thuộc công ty của công trình.',
                ]);
            }

            $claim = DB::transaction(function () use ($data, $site, $companyId, $serial, $assignee, $request) {
                $claim = SolarWarrantyClaim::create([
                    'company_id' => $companyId ?: null,
                    'site_id' => $site->id,
                    'maintenance_schedule_id' => $data['maintenance_schedule_id'] ?? null,
                    'serial_unit_id' => $serial?->serial_unit_id,
                    'serial_code' => $serial?->serial_code ?: ($data['serial_code'] ?? null),
                    'customer_id' => $serial?->customer_id,
                    'order_id' => $serial?->order_id,
                    'claim_type' => $data['claim_type'],
                    'priority' => $data['priority'],
                    'status' => 'received',
                    'approval_status' => 'not_submitted',
                    'assigned_to' => $assignee?->id,
                    'assigned_name' => $assignee?->name,
                    'received_at' => now()->toDateString(),
                    'issue_description' => $data['issue_description'],
                    'internal_note' => $data['internal_note'] ?? null,
                    'is_chargeable' => (bool) ($data['is_chargeable'] ?? false),
                    'estimated_cost' => $data['estimated_cost'] ?? 0,
                    'created_by' => $request->user()->id,
                ]);

                $claim->update(['claim_code' => sprintf('BH-%s-%06d', now()->format('Y'), $claim->id)]);
                $this->logSerialEvent($claim, 'maintenance_claim_received', 'Tiếp nhận phiếu '.$claim->claim_code.': '.$claim->issue_description);

                return $claim;
            });

            return redirect()->route('projects-unified.maintenance.index', ['view' => 'claims'])
                ->with('success', 'Đã tạo phiếu '.$claim->claim_code.' và liên kết với công trình.');
        } catch (ValidationException $exception) {
            $exception->errorBag = 'warrantyClaim';
            throw $exception;
        }
    }

    public function updateStatus(Request $request, SolarWarrantyClaim $claim): RedirectResponse
    {
        $this->assertCanUpdate($request, $claim);

        if (\App\Support\Warranty\WarrantyFlow::isFlowType((string) $claim->claim_type)) {
            throw ValidationException::withMessages([
                'status' => 'Phiếu này thuộc quy trình có nút hành động riêng (duyệt, giữ hàng, xuất kho...). Không thể đổi trạng thái trực tiếp.',
            ]);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SolarWarrantyClaim::STATUSES))],
            'diagnosis' => ['nullable', 'string', 'max:10000'],
            'proposed_solution' => ['nullable', 'string', 'max:10000'],
            'resolution' => ['nullable', 'string', 'max:10000'],
            'approval_note' => ['nullable', 'string', 'max:5000'],
            'actual_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'is_chargeable' => ['nullable', 'boolean'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $oldStatus = (string) $claim->status;
        $newStatus = (string) $data['status'];
        if ($newStatus !== $oldStatus && ! in_array($newStatus, SolarWarrantyClaim::TRANSITIONS[$oldStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'Không thể chuyển từ “'.(SolarWarrantyClaim::STATUSES[$oldStatus] ?? $oldStatus).'” sang “'.(SolarWarrantyClaim::STATUSES[$newStatus] ?? $newStatus).'”.',
            ]);
        }

        if ((string) $claim->claim_type === 'replacement') {
            if (in_array($newStatus, ['replacing', 'waiting_customer', 'completed'], true)
                && ! $claim->replacement_serial_unit_id) {
                throw ValidationException::withMessages([
                    'status' => 'Đề xuất đổi hàng chưa có serial thay thế. Kho phải hoàn tất xuất đổi trước khi chuyển sang bước này.',
                ]);
            }

            if ($newStatus === 'completed' && ! $claim->returned_serial_unit_id) {
                throw ValidationException::withMessages([
                    'status' => 'Chưa ghi nhận thu hồi serial lỗi. Kho phải hoàn tất phiếu thu hồi trước khi đóng đề xuất đổi hàng.',
                ]);
            }
        }

        if (in_array($newStatus, ['approved', 'rejected'], true)
            && ! SolarMaintenanceAccess::canApprove($request->user())) {
            abort(403, 'Chỉ Admin/Trưởng phòng kỹ thuật được duyệt hoặc từ chối phiếu.');
        }
        if (in_array($newStatus, ['approved', 'rejected'], true)
            && (int) $claim->assigned_to === (int) $request->user()->id
            && ! SolarMaintenanceAccess::isAdmin($request->user())) {
            throw ValidationException::withMessages([
                'status' => 'Người trực tiếp xử lý không được tự duyệt hoặc từ chối phiếu của mình.',
            ]);
        }
        if ($newStatus === 'completed' && ! SolarMaintenanceAccess::isManager($request->user())) {
            abort(403, 'Chỉ Admin/Trưởng phòng kỹ thuật được đóng phiếu hoàn thành.');
        }
        if ($newStatus === 'cancelled' && ! SolarMaintenanceAccess::isManager($request->user())) {
            abort(403, 'Chỉ Admin/Trưởng phòng kỹ thuật được hủy phiếu.');
        }
        if (in_array($oldStatus, ['completed', 'rejected', 'cancelled'], true)
            && $newStatus !== $oldStatus
            && ! SolarMaintenanceAccess::isManager($request->user())) {
            abort(403, 'Chỉ Admin/Trưởng phòng kỹ thuật được mở lại phiếu đã đóng, từ chối hoặc hủy.');
        }

        $newAssignee = null;
        if (array_key_exists('assigned_to', $data)) {
            abort_unless(SolarMaintenanceAccess::isManager($request->user()), 403, 'Chỉ quản lý được thay đổi người phụ trách.');
            $newAssignee = ! empty($data['assigned_to']) ? User::find((int) $data['assigned_to']) : null;
            if ($newAssignee && ! SolarMaintenanceAccess::isSelectableTechnician($newAssignee)) {
                throw ValidationException::withMessages(['assigned_to' => 'Người phụ trách phải là nhân sự kỹ thuật.']);
            }
            $claimCompanyId = (int) ($claim->company_id ?: $claim->site?->company_id);
            if ($newAssignee
                && SchemaCache::hasColumn('users', 'company_id')
                && $claimCompanyId > 0
                && (int) ($newAssignee->company_id ?? 0) > 0
                && (int) $newAssignee->company_id !== $claimCompanyId) {
                throw ValidationException::withMessages(['assigned_to' => 'Người phụ trách không thuộc công ty của công trình.']);
            }
        }

        $diagnosis = trim((string) ($data['diagnosis'] ?? $claim->diagnosis ?? ''));
        $solution = trim((string) ($data['proposed_solution'] ?? $claim->proposed_solution ?? ''));
        $resolution = trim((string) ($data['resolution'] ?? $claim->resolution ?? ''));
        if ($newStatus === 'pending_approval' && ($diagnosis === '' || $solution === '')) {
            throw ValidationException::withMessages([
                'proposed_solution' => 'Phải nhập đầy đủ chẩn đoán và phương án trước khi gửi duyệt.',
            ]);
        }
        if ($newStatus === 'completed' && $resolution === '') {
            throw ValidationException::withMessages([
                'resolution' => 'Phải nhập kết quả xử lý trước khi đóng phiếu hoàn thành.',
            ]);
        }

        DB::transaction(function () use ($claim, $data, $newStatus, $newAssignee, $request) {
            $claim->fill([
                'status' => $newStatus,
                'diagnosis' => $data['diagnosis'] ?? $claim->diagnosis,
                'proposed_solution' => $data['proposed_solution'] ?? $claim->proposed_solution,
                'resolution' => $data['resolution'] ?? $claim->resolution,
                'approval_note' => $data['approval_note'] ?? $claim->approval_note,
                'actual_cost' => $data['actual_cost'] ?? $claim->actual_cost,
                'is_chargeable' => array_key_exists('is_chargeable', $data) ? (bool) $data['is_chargeable'] : $claim->is_chargeable,
            ]);

            if (array_key_exists('assigned_to', $data)) {
                $claim->assigned_to = $newAssignee?->id;
                $claim->assigned_name = $newAssignee?->name;
            }

            if ($newStatus === 'pending_approval') {
                $claim->approval_status = 'pending';
                $claim->submitted_at = now();
                $claim->submitted_by = $request->user()->id;
            } elseif ($newStatus === 'approved') {
                $claim->approval_status = 'approved';
                $claim->approved_at = now();
                $claim->approved_by = $request->user()->id;
            } elseif ($newStatus === 'rejected') {
                $claim->approval_status = 'rejected';
                $claim->approved_at = now();
                $claim->approved_by = $request->user()->id;
            } elseif ($newStatus === 'waiting_customer') {
                $claim->customer_confirmed_at = null;
            } elseif ($newStatus === 'completed') {
                $claim->approval_status = $claim->approval_status === 'not_submitted' ? 'approved' : $claim->approval_status;
                $claim->resolved_at = now()->toDateString();
                $claim->customer_confirmed_at = $claim->customer_confirmed_at ?: now();
                $claim->closed_at = now();
                $claim->closed_by = $request->user()->id;
                $claim->cost = $claim->actual_cost ?: $claim->estimated_cost ?: 0;
            }

            if (in_array($newStatus, ['diagnosing', 'received'], true)
                && in_array((string) $claim->getOriginal('status'), ['pending_approval', 'rejected', 'cancelled', 'completed'], true)) {
                $claim->approval_status = 'not_submitted';
                $claim->submitted_at = null;
                $claim->submitted_by = null;
                $claim->approved_at = null;
                $claim->approved_by = null;
                $claim->closed_at = null;
                $claim->closed_by = null;
                $claim->resolved_at = null;
            }
            if ($newStatus === 'replacing' && (string) $claim->getOriginal('status') === 'completed') {
                $claim->closed_at = null;
                $claim->closed_by = null;
                $claim->resolved_at = null;
                $claim->customer_confirmed_at = null;
            }

            $claim->save();
            $this->logSerialEvent($claim, 'maintenance_claim_status', 'Phiếu '.$claim->claim_code.' chuyển trạng thái: '.(SolarWarrantyClaim::STATUSES[$newStatus] ?? $newStatus));
        });

        return back()->with('success', 'Đã cập nhật phiếu '.$claim->claim_code.'.');
    }

    private function assertCanUpdate(Request $request, SolarWarrantyClaim $claim): void
    {
        $this->assertCompany((int) ($claim->company_id ?: $claim->site?->company_id));
        $allowed = SolarMaintenanceAccess::isManager($request->user())
            || (SolarMaintenanceAccess::isTechnician($request->user()) && (int) $claim->assigned_to === (int) $request->user()->id);
        abort_unless($allowed, 403);
    }

    private function assertCompany(int $companyId): void
    {
        $current = EgoCompanyScope::currentId();
        if ($current > 0 && $companyId > 0 && $current !== $companyId && ! SolarMaintenanceAccess::isAdmin(auth()->user())) {
            abort(403, 'Dữ liệu không thuộc công ty đang làm việc.');
        }
    }

    private function resolveSerial(string $code): ?object
    {
        if ($code === '') {
            return null;
        }
        foreach (['crm_serial_units', 'crm_serial_unit_identifiers', 'crm_serial_identifiers'] as $table) {
            if (! SchemaCache::hasTable($table)) {
                throw ValidationException::withMessages(['serial_code' => 'Kho serial chưa sẵn sàng để tra cứu.']);
            }
        }

        $query = DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', function ($join) {
                $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id');

        if (SchemaCache::hasTable('crm_serial_warranties')) {
            $query->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id')
                ->addSelect('wa.customer_id', 'wa.order_id');
        } else {
            $query->addSelect(DB::raw('NULL as customer_id'), DB::raw('NULL as order_id'));
        }

        if (SchemaCache::hasTable('crm_serial_unit_states') && SchemaCache::hasColumn('crm_serial_unit_states', 'company_id')) {
            $query->leftJoin('crm_serial_unit_states as sus', 'sus.serial_unit_id', '=', 'su.id')
                ->addSelect('sus.company_id as state_company_id');
        } else {
            $query->addSelect(DB::raw('NULL as state_company_id'));
        }

        $query = $query->where('si.code', $code)
            ->addSelect('su.id as serial_unit_id', 'su.product_id', 'si.code as serial_code')
            ->first();

        if (! $query) {
            throw ValidationException::withMessages(['serial_code' => 'Không tìm thấy serial “'.$code.'” trong hệ thống.']);
        }

        return $query;
    }

    private function assertSerialCompany(?object $serial, int $companyId, Request $request): void
    {
        if (! $serial || $companyId <= 0 || SolarMaintenanceAccess::isAdmin($request->user())) {
            return;
        }

        $serialCompanyId = (int) ($serial->state_company_id ?? 0);
        if ($serialCompanyId > 0 && $serialCompanyId !== $companyId) {
            throw ValidationException::withMessages([
                'serial_code' => 'Serial này đang thuộc công ty khác và không thể gắn vào công trình đã chọn.',
            ]);
        }
    }

    private function logSerialEvent(SolarWarrantyClaim $claim, string $type, string $note): void
    {
        if (! $claim->serial_unit_id || ! SchemaCache::hasTable('crm_serial_warranty_events')) {
            return;
        }
        DB::table('crm_serial_warranty_events')->insert([
            'serial_unit_id' => $claim->serial_unit_id,
            'serial_code' => $claim->serial_code,
            'event_type' => $type,
            'customer_id' => $claim->customer_id,
            'order_id' => $claim->order_id,
            'created_by' => auth()->id(),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
