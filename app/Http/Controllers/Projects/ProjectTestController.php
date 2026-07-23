<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\ProjectTest\Acceptance;
use App\Models\ProjectTest\Assignment;
use App\Models\ProjectTest\DailyLog;
use App\Models\ProjectTest\History;
use App\Models\ProjectTest\MaterialRequest;
use App\Models\ProjectTest\Project;
use App\Models\ProjectTest\Proposal;
use App\Models\ProjectTest\Survey;
use App\Models\ProjectTest\Warranty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectTestController extends Controller
{
    public const STATUSES = [
        'survey_pending' => ['label' => 'Chờ Kỹ thuật duyệt lịch khảo sát', 'group' => 'Khảo sát', 'progress' => 8, 'owner' => 'technical'],
        'survey_reschedule' => ['label' => 'Kỹ thuật đề nghị đổi lịch khảo sát', 'group' => 'Khảo sát', 'progress' => 10, 'owner' => 'sales'],
        'survey_confirmed' => ['label' => 'Đã xác nhận lịch khảo sát', 'group' => 'Khảo sát', 'progress' => 15, 'owner' => 'technical'],
        'survey_in_progress' => ['label' => 'Đang khảo sát', 'group' => 'Khảo sát', 'progress' => 20, 'owner' => 'technical'],
        'sales_review' => ['label' => 'Sales đang chốt phương án với khách', 'group' => 'Phương án', 'progress' => 35, 'owner' => 'sales'],
        'proposal_revision' => ['label' => 'Khách yêu cầu chỉnh phương án', 'group' => 'Phương án', 'progress' => 32, 'owner' => 'technical'],
        'installation_pending' => ['label' => 'Chờ Kỹ thuật duyệt lịch thi công', 'group' => 'Chuẩn bị', 'progress' => 45, 'owner' => 'technical'],
        'installation_reschedule' => ['label' => 'Kỹ thuật đề nghị đổi lịch thi công', 'group' => 'Chuẩn bị', 'progress' => 43, 'owner' => 'sales'],
        'materials_pending' => ['label' => 'Chờ Kỹ thuật đề xuất vật tư', 'group' => 'Vật tư', 'progress' => 50, 'owner' => 'technical'],
        'materials_admin_review' => ['label' => 'Chờ Admin duyệt vật tư', 'group' => 'Vật tư', 'progress' => 55, 'owner' => 'admin'],
        'materials_revision' => ['label' => 'Vật tư cần điều chỉnh', 'group' => 'Vật tư', 'progress' => 52, 'owner' => 'technical'],
        'warehouse_preparing' => ['label' => 'Kho đang chuẩn bị hàng', 'group' => 'Vật tư', 'progress' => 62, 'owner' => 'warehouse'],
        'warehouse_issued' => ['label' => 'Đã xuất kho công trình', 'group' => 'Điều phối', 'progress' => 68, 'owner' => 'technical_manager'],
        'assignment_pending' => ['label' => 'Chờ Kỹ thuật trưởng phân công', 'group' => 'Điều phối', 'progress' => 70, 'owner' => 'technical_manager'],
        'ready_install' => ['label' => 'Sẵn sàng thi công', 'group' => 'Thi công', 'progress' => 75, 'owner' => 'technical'],
        'installing' => ['label' => 'Đang thi công', 'group' => 'Thi công', 'progress' => 82, 'owner' => 'technical'],
        'acceptance_pending' => ['label' => 'Chờ nghiệm thu', 'group' => 'Nghiệm thu', 'progress' => 92, 'owner' => 'technical_manager'],
        'warranty_active' => ['label' => 'Đã nghiệm thu · Đang bảo hành', 'group' => 'Bảo hành', 'progress' => 100, 'owner' => 'technical'],
        'completed' => ['label' => 'Đã kết thúc', 'group' => 'Hoàn tất', 'progress' => 100, 'owner' => 'none'],
        'cancelled' => ['label' => 'Đã hủy', 'group' => 'Dừng', 'progress' => 0, 'owner' => 'none'],
    ];

    public function index(Request $request)
    {
        $this->authorizePermission('project-test.access');

        $user = $request->user();
        if ($user->hasAnyRole(['warehouse', 'kho']) && ! $user->hasAnyRole(['admin', 'management', 'technical_manager', 'sales_manager', 'sales', 'ky_thuat'])) {
            return redirect()->route('project-test.warehouse.index');
        }

        $query = Project::query()
            ->visibleTo($user)
            ->with([
                'salesUser:id,name',
                'leadTechnician:id,name',
                'latestMaterialRequest' => function ($query): void {
                    $query->select([
                        'project_test_material_requests.id',
                        'project_test_material_requests.project_id',
                        'project_test_material_requests.status',
                        'project_test_material_requests.code',
                    ]);
                },
            ])
            ->latest('id');

        if ($keyword = trim((string) $request->query('q'))) {
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%")
                    ->orWhere('contact_name', 'like', "%{$keyword}%")
                    ->orWhere('contact_phone', 'like', "%{$keyword}%");
            });
        }

        if ($status = $request->query('status')) {
            if (isset(self::STATUSES[$status])) {
                $query->where('status', $status);
            }
        }

        $projects = $query->paginate(18)->withQueryString();
        $visible = Project::query()->visibleTo($user);
        $kpis = [
            'total' => (clone $visible)->count(),
            'mine' => (clone $visible)->where(function (Builder $q) use ($user) {
                $q->where('sales_user_id', $user->id)
                    ->orWhere('lead_technician_id', $user->id)
                    ->orWhereHas('assignments', fn (Builder $a) => $a->where('user_id', $user->id));
            })->count(),
            'waiting' => (clone $visible)->whereIn('status', ['survey_pending', 'installation_pending', 'materials_admin_review', 'assignment_pending'])->count(),
            'installing' => (clone $visible)->whereIn('status', ['ready_install', 'installing', 'acceptance_pending'])->count(),
            'warranty' => (clone $visible)->where('status', 'warranty_active')->count(),
        ];

        return view('project-test.index', [
            'projects' => $projects,
            'kpis' => $kpis,
            'statuses' => self::STATUSES,
            'roleLabel' => $this->roleLabel($user),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizePermission('project-test.create');

        return view('project-test.create', array_merge($this->formOptions(), [
            'defaultSalesId' => $request->user()->id,
            'activeCompanyId' => (int) session('active_company_id', 0),
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('project-test.create');

        $data = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'sales_user_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:700'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'customer_need' => ['required', 'string'],
            'system_type' => ['nullable', 'string', 'max:100'],
            'estimated_kwp' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'proposed_survey_at' => ['required', 'date', 'after_or_equal:now'],
            'target_completion_at' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string'],
        ]);

        if (! empty($data['sales_user_id'])) {
            $this->assertUserHasRole((int) $data['sales_user_id'], ['sales', 'sales_manager'], 'Sales phụ trách');
        }

        $project = DB::transaction(function () use ($request, $data) {
            $project = Project::create(array_merge($data, [
                'code' => $this->nextProjectCode(),
                'created_by' => $request->user()->id,
                'sales_user_id' => ($data['sales_user_id'] ?? null) ?: $request->user()->id,
                'status' => 'survey_pending',
                'current_owner_role' => 'technical',
                'progress' => self::STATUSES['survey_pending']['progress'],
            ]));

            Survey::create([
                'project_id' => $project->id,
                'schedule_status' => 'pending',
                'scheduled_at' => $project->proposed_survey_at,
            ]);

            $this->history($project, 'Sales tạo hồ sơ và đề xuất lịch khảo sát', null, 'survey_pending');

            return $project;
        });

        return redirect()->route('project-test.show', $project)->with('success', 'Đã tạo hồ sơ công trình Test mới và chuyển Kỹ thuật duyệt lịch khảo sát.');
    }

    public function show(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);

        $project->load([
            'creator:id,name', 'salesUser:id,name', 'technicalManager:id,name', 'leadTechnician:id,name',
            'survey.surveyor:id,name', 'proposal', 'materialRequests.items.allocations.product:id,name,sku,unit,is_serialized', 'materialRequests.items.allocations.warehouse:id,name', 'assignments.user:id,name',
            'dailyLogs.author:id,name', 'acceptance', 'warranty', 'histories.user:id,name',
        ]);

        return view('project-test.show', array_merge($this->formOptions(), [
            'project' => $project,
            'statuses' => self::STATUSES,
            'statusInfo' => self::STATUSES[$project->status] ?? ['label' => $project->status, 'group' => 'Khác', 'progress' => $project->progress],
            'can' => $this->actionAbilities($request->user()),
        ]));
    }

    public function reviewSurveySchedule(Request $request, Project $project)
    {
        $this->authorizeTechnicalManagerRole();
        $this->assertStatus($project, ['survey_pending', 'survey_reschedule']);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['confirm', 'reschedule'])],
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'technical_manager_id' => ['required', 'integer'],
            'surveyor_id' => ['nullable', 'required_if:decision,confirm', 'integer'],
            'reason' => ['nullable', 'string'],
        ]);

        $this->assertUserHasRole((int) $data['technical_manager_id'], ['technical_manager'], 'Trưởng phòng Kỹ thuật');
        if (! empty($data['surveyor_id'])) {
            $this->assertUserHasRole((int) $data['surveyor_id'], ['ky_thuat'], 'Người khảo sát');
        }

        DB::transaction(function () use ($request, $project, $data) {
            $survey = $project->survey ?: new Survey(['project_id' => $project->id]);
            $survey->fill([
                'reviewed_by' => $request->user()->id,
                'surveyed_by' => $data['surveyor_id'] ?? $survey->surveyed_by,
                'schedule_status' => $data['decision'] === 'confirm' ? 'confirmed' : 'reschedule',
                'scheduled_at' => $data['scheduled_at'],
                'reschedule_reason' => $data['reason'] ?? null,
            ])->save();

            $project->technical_manager_id = (int) $data['technical_manager_id'];
            if (! empty($data['surveyor_id']) && ! $project->lead_technician_id) {
                $project->lead_technician_id = (int) $data['surveyor_id'];
            }
            $project->proposed_survey_at = $data['scheduled_at'];

            if ($data['decision'] === 'confirm') {
                $project->survey_confirmed_at = $data['scheduled_at'];
                $this->transition($project, 'survey_confirmed', 'Trưởng phòng Kỹ thuật xác nhận lịch và phân công người khảo sát');
            } else {
                $this->transition($project, 'survey_reschedule', 'Trưởng phòng Kỹ thuật đề nghị Sales hẹn lịch khảo sát khác: '.($data['reason'] ?? 'Không ghi lý do'));
            }
        });

        return back()->with('success', $data['decision'] === 'confirm' ? 'Đã xác nhận lịch và phân công người khảo sát.' : 'Đã gửi Sales yêu cầu đổi lịch khảo sát.');
    }

    public function salesRescheduleSurvey(Request $request, Project $project)
    {
        $this->authorizePermission('project-test.sales');
        $this->assertStatus($project, ['survey_reschedule']);
        $data = $request->validate(['proposed_survey_at' => ['required', 'date', 'after_or_equal:now'], 'note' => ['nullable', 'string']]);

        $project->proposed_survey_at = $data['proposed_survey_at'];
        $project->survey()->updateOrCreate(['project_id' => $project->id], ['schedule_status' => 'pending', 'scheduled_at' => $data['proposed_survey_at'], 'reschedule_reason' => $data['note'] ?? null]);
        $this->transition($project, 'survey_pending', 'Sales đề xuất lại lịch khảo sát'.(!empty($data['note']) ? ': '.$data['note'] : ''));

        return back()->with('success', 'Đã gửi lại lịch khảo sát cho Kỹ thuật duyệt.');
    }

    public function submitSurvey(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->assertStatus($project, ['survey_confirmed', 'survey_in_progress', 'proposal_revision']);

        $data = $request->validate([
            'site_condition' => ['required', 'string'],
            'technical_notes' => ['nullable', 'string'],
            'proposed_kwp' => ['required', 'numeric', 'min:0.01'],
            'solution_summary' => ['required', 'string'],
            'preliminary_materials' => ['required', 'string'],
            'design_3d_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar,dwg,dxf,skp', 'max:30720'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar,doc,docx,xls,xlsx', 'max:30720'],
        ]);

        DB::transaction(function () use ($request, $project, $data) {
            $survey = $project->survey ?: new Survey(['project_id' => $project->id]);
            $survey->surveyed_by = $survey->surveyed_by ?: $request->user()->id;
            $survey->schedule_status = 'completed';
            $survey->completed_at = now();
            $survey->site_condition = $data['site_condition'];
            $survey->technical_notes = $data['technical_notes'] ?? null;
            if ($request->hasFile('design_3d_file')) {
                $this->deleteFile($survey->design_3d_file);
                $survey->design_3d_file = $request->file('design_3d_file')->store('project-test/designs', 'public');
            }
            if ($request->hasFile('attachment_file')) {
                $this->deleteFile($survey->attachment_file);
                $survey->attachment_file = $request->file('attachment_file')->store('project-test/surveys', 'public');
            }
            $survey->save();

            Proposal::updateOrCreate(['project_id' => $project->id], [
                'created_by' => $request->user()->id,
                'proposed_kwp' => $data['proposed_kwp'],
                'solution_summary' => $data['solution_summary'],
                'preliminary_materials_json' => $this->linesToArray($data['preliminary_materials']),
                'sales_decision' => 'pending',
                'sales_feedback' => null,
                'sales_confirmed_at' => null,
            ]);

            $project->estimated_kwp = $data['proposed_kwp'];
            $this->transition($project, 'sales_review', 'Kỹ thuật hoàn tất khảo sát, gửi mô phỏng 3D và phương án cho Sales');
        });

        return back()->with('success', 'Đã gửi kết quả khảo sát, mô phỏng 3D và phương án cho Sales.');
    }

    public function salesReviewProposal(Request $request, Project $project)
    {
        $this->authorizePermission('project-test.sales');
        $this->assertStatus($project, ['sales_review']);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'revision'])],
            'feedback' => ['nullable', 'string'],
            'proposed_installation_at' => ['nullable', 'required_if:decision,accept', 'date', 'after_or_equal:today'],
        ]);

        DB::transaction(function () use ($project, $data) {
            $project->proposal()->update([
                'sales_decision' => $data['decision'],
                'sales_feedback' => $data['feedback'] ?? null,
                'sales_confirmed_at' => now(),
            ]);

            if ($data['decision'] === 'revision') {
                $this->transition($project, 'proposal_revision', 'Sales/Khách yêu cầu chỉnh phương án: '.($data['feedback'] ?? 'Không ghi nội dung'));
                return;
            }

            $project->proposed_installation_at = $data['proposed_installation_at'];
            $this->transition($project, 'installation_pending', 'Sales đã chốt khách và đề xuất lịch thi công');
        });

        return back()->with('success', $data['decision'] === 'accept' ? 'Đã chốt phương án và gửi lịch thi công cho Kỹ thuật.' : 'Đã trả phương án về Kỹ thuật điều chỉnh.');
    }

    public function reviewInstallationSchedule(Request $request, Project $project)
    {
        $this->authorizeTechnicalManagerRole();
        $this->assertStatus($project, ['installation_pending', 'installation_reschedule']);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['confirm', 'reschedule'])],
            'installation_at' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string'],
        ]);

        $project->proposed_installation_at = $data['installation_at'];
        if ($data['decision'] === 'confirm') {
            $project->installation_confirmed_at = $data['installation_at'];
            $this->transition($project, 'materials_pending', 'Kỹ thuật xác nhận lịch thi công; chuyển lập vật tư chính thức');
        } else {
            $this->transition($project, 'installation_reschedule', 'Kỹ thuật đề nghị Sales đổi lịch thi công: '.($data['reason'] ?? 'Không ghi lý do'));
        }

        return back()->with('success', $data['decision'] === 'confirm' ? 'Đã xác nhận lịch thi công.' : 'Đã yêu cầu Sales đổi lịch thi công.');
    }

    public function salesRescheduleInstallation(Request $request, Project $project)
    {
        $this->authorizePermission('project-test.sales');
        $this->assertStatus($project, ['installation_reschedule']);
        $data = $request->validate(['proposed_installation_at' => ['required', 'date', 'after_or_equal:today'], 'note' => ['nullable', 'string']]);
        $project->proposed_installation_at = $data['proposed_installation_at'];
        $this->transition($project, 'installation_pending', 'Sales đề xuất lại lịch thi công'.(!empty($data['note']) ? ': '.$data['note'] : ''));
        return back()->with('success', 'Đã gửi lại lịch thi công cho Kỹ thuật duyệt.');
    }

    public function submitMaterials(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->assertStatus($project, ['materials_pending', 'materials_revision']);

        $data = $request->validate([
            'needed_at' => ['required', 'date', 'after_or_equal:today'],
            'request_note' => ['nullable', 'string'],
            'item_name' => ['required', 'array', 'min:1'],
            'item_name.*' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'array'],
            'quantity.*' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', 'array'],
            'unit.*' => ['required', 'string', 'max:40'],
            'item_note' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($request, $project, $data) {
            $materialRequest = MaterialRequest::create([
                'project_id' => $project->id,
                'code' => $this->nextMaterialCode(),
                'requested_by' => $request->user()->id,
                'needed_at' => $data['needed_at'],
                'status' => 'pending_admin',
                'request_note' => $data['request_note'] ?? null,
            ]);

            foreach ($data['item_name'] as $index => $name) {
                $materialRequest->items()->create([
                    'product_id' => null,
                    'item_name' => $name,
                    'quantity' => Arr::get($data, "quantity.{$index}", 1),
                    'unit' => Arr::get($data, "unit.{$index}", 'cái'),
                    'note' => Arr::get($data, "item_note.{$index}"),
                ]);
            }

            $this->transition($project, 'materials_admin_review', 'Kỹ thuật gửi yêu cầu vật tư '.$materialRequest->code.' cho Admin duyệt');
        });

        return back()->with('success', 'Đã gửi yêu cầu vật tư chính thức cho Admin duyệt.');
    }

    public function reviewMaterials(Request $request, Project $project, MaterialRequest $materialRequest)
    {
        $this->authorizePermission('project-test.admin');
        abort_unless($materialRequest->project_id === $project->id, 404);
        abort_unless($materialRequest->status === 'pending_admin', 422, 'Phiếu không còn ở trạng thái chờ Admin.');

        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'review_note' => ['nullable', 'string']]);

        $materialRequest->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'revision',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);

        if ($data['decision'] === 'approve') {
            $this->transition($project, 'warehouse_preparing', 'Admin duyệt vật tư '.$materialRequest->code.'; chuyển sang Kho chuẩn bị');
        } else {
            $this->transition($project, 'materials_revision', 'Admin trả lại vật tư '.$materialRequest->code.': '.($data['review_note'] ?? 'Không ghi lý do'));
        }

        return back()->with('success', $data['decision'] === 'approve' ? 'Đã duyệt và chuyển phiếu sang Kho.' : 'Đã trả phiếu về Kỹ thuật điều chỉnh.');
    }

    public function assignTeam(Request $request, Project $project)
    {
        $this->authorizeTechnicalManagerRole();
        $this->assertStatus($project, ['warehouse_issued', 'assignment_pending']);

        $data = $this->validateTeamAssignment($request);
        $this->persistTeamAssignment($request, $project, $data);
        $this->transition($project, 'ready_install', 'Trưởng phòng Kỹ thuật đã phân công đội thi công');

        return back()->with('success', 'Đã phân công đội thi công.');
    }

    public function startInstallation(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->assertStatus($project, ['ready_install']);
        $this->transition($project, 'installing', 'Đội kỹ thuật bắt đầu thi công');
        return back()->with('success', 'Đã bắt đầu thi công.');
    }

    public function storeDailyLog(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->assertStatus($project, ['installing']);

        $data = $request->validate([
            'log_date' => ['required', 'date'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['working', 'blocked', 'waiting_customer', 'waiting_material', 'done'])],
            'content' => ['required', 'string'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar', 'max:30720'],
        ]);

        $path = $request->hasFile('attachment_file') ? $request->file('attachment_file')->store('project-test/logs', 'public') : null;
        DailyLog::create(array_merge($data, ['project_id' => $project->id, 'created_by' => $request->user()->id, 'attachment_file' => $path]));
        $project->progress = max($project->progress, min(90, (int) $data['progress']));
        if ($data['status'] === 'done' || (int) $data['progress'] >= 100) {
            $this->transition($project, 'acceptance_pending', 'Kỹ thuật hoàn tất thi công và gửi nghiệm thu');
        } else {
            $project->save();
            $this->history($project, 'Cập nhật nhật ký thi công: '.$data['content'], $project->status, $project->status, ['progress' => $data['progress']]);
        }

        return back()->with('success', 'Đã cập nhật nhật ký thi công.');
    }

    public function accept(Request $request, Project $project)
    {
        $this->authorizeAcceptanceRole();
        $this->assertStatus($project, ['acceptance_pending']);

        $data = $request->validate([
            'accepted_at' => ['required', 'date'],
            'device_serials' => ['required', 'string'],
            'monitoring_link' => ['nullable', 'url', 'max:700'],
            'monitoring_account' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'warranty_months' => ['required', 'integer', 'min:1', 'max:240'],
            'report_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:30720'],
            'checklist' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($request, $project, $data) {
            $path = $request->hasFile('report_file') ? $request->file('report_file')->store('project-test/acceptances', 'public') : null;
            Acceptance::updateOrCreate(['project_id' => $project->id], [
                'submitted_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'accepted_at' => $data['accepted_at'],
                'checklist_json' => $data['checklist'] ?? [],
                'device_serials' => $data['device_serials'],
                'monitoring_link' => $data['monitoring_link'] ?? null,
                'monitoring_account' => $data['monitoring_account'] ?? null,
                'report_file' => $path,
                'note' => $data['note'] ?? null,
                'status' => 'approved',
            ]);

            $starts = Carbon::parse($data['accepted_at']);
            Warranty::updateOrCreate(['project_id' => $project->id], [
                'starts_at' => $starts->toDateString(),
                'ends_at' => $starts->copy()->addMonths((int) $data['warranty_months'])->toDateString(),
                'next_maintenance_at' => $starts->copy()->addMonths(6)->toDateString(),
                'assigned_to' => $project->lead_technician_id,
                'status' => 'active',
                'note' => 'Tự động tạo sau nghiệm thu trong module Công Trình Test new.',
            ]);

            $this->transition($project, 'warranty_active', 'Đã nghiệm thu; hệ thống tự động tạo hồ sơ bảo hành');
        });

        return back()->with('success', 'Đã nghiệm thu và tự động mở hồ sơ bảo hành.');
    }

    public function updateBasic(Request $request, Project $project)
    {
        $this->authorizeSalesRole();
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'sales_user_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:700'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'customer_need' => ['required', 'string'],
            'system_type' => ['nullable', 'string', 'max:100'],
            'estimated_kwp' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'proposed_survey_at' => ['nullable', 'date'],
            'proposed_installation_at' => ['nullable', 'date'],
            'target_completion_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $this->assertUserHasRole((int) $data['sales_user_id'], ['sales', 'sales_manager'], 'Sales phụ trách');

        DB::transaction(function () use ($project, $data) {
            $project->fill($data)->save();

            if (! empty($data['proposed_survey_at'])) {
                $project->survey()->updateOrCreate(
                    ['project_id' => $project->id],
                    ['scheduled_at' => $data['proposed_survey_at']]
                );
            }

            $this->history(
                $project,
                'Sales cập nhật hồ sơ bàn giao và lịch dự kiến',
                $project->status,
                $project->status,
                ['edited_section' => 'basic']
            );
        });

        return back()->with('success', 'Đã cập nhật thông tin hồ sơ công trình.');
    }

    public function updatePeople(Request $request, Project $project)
    {
        $this->authorizeTechnicalManagerRole();
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'technical_manager_id' => ['required', 'integer'],
            'surveyor_id' => ['nullable', 'integer'],
            'survey_at' => ['nullable', 'date'],
            'lead_technician_id' => ['nullable', 'integer'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer'],
            'work_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'update_team' => ['nullable', 'boolean'],
        ]);

        $this->assertUserHasRole((int) $data['technical_manager_id'], ['technical_manager'], 'Trưởng phòng Kỹ thuật');
        if (! empty($data['surveyor_id'])) {
            $this->assertUserHasRole((int) $data['surveyor_id'], ['ky_thuat'], 'Người khảo sát');
        }

        DB::transaction(function () use ($request, $project, $data) {
            $project->technical_manager_id = (int) $data['technical_manager_id'];

            $survey = $project->survey ?: new Survey(['project_id' => $project->id]);
            if (array_key_exists('surveyor_id', $data)) {
                $survey->surveyed_by = $data['surveyor_id'] ?: null;
            }
            if (! empty($data['survey_at'])) {
                $survey->scheduled_at = $data['survey_at'];
                $project->proposed_survey_at = $data['survey_at'];
                if ($project->survey_confirmed_at) {
                    $project->survey_confirmed_at = $data['survey_at'];
                }
            }
            $survey->save();

            if ($request->boolean('update_team')) {
                $teamData = [
                    'lead_technician_id' => $data['lead_technician_id'] ?? null,
                    'member_ids' => $data['member_ids'] ?? [],
                    'work_date' => $data['work_date'] ?? null,
                    'note' => $data['note'] ?? null,
                ];
                $this->persistTeamAssignment($request, $project, $teamData, true);
            } elseif (! empty($data['surveyor_id']) && ! $project->lead_technician_id) {
                $project->lead_technician_id = (int) $data['surveyor_id'];
            }

            $project->save();
            $this->history(
                $project,
                'Trưởng phòng Kỹ thuật cập nhật người phụ trách và phân công',
                $project->status,
                $project->status,
                ['edited_section' => 'people']
            );
        });

        return back()->with('success', 'Đã cập nhật người khảo sát, trưởng phòng và đội thi công.');
    }

    public function updateSurveyData(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'site_condition' => ['required', 'string'],
            'technical_notes' => ['nullable', 'string'],
            'proposed_kwp' => ['required', 'numeric', 'min:0.01'],
            'solution_summary' => ['required', 'string'],
            'preliminary_materials' => ['required', 'string'],
            'completed_at' => ['nullable', 'date'],
            'design_3d_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar,dwg,dxf,skp', 'max:30720'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar,doc,docx,xls,xlsx', 'max:30720'],
        ]);

        DB::transaction(function () use ($request, $project, $data) {
            $survey = $project->survey ?: new Survey(['project_id' => $project->id]);
            $survey->surveyed_by = $survey->surveyed_by ?: $request->user()->id;
            $survey->schedule_status = 'completed';
            $survey->completed_at = $data['completed_at'] ?? $survey->completed_at ?? now();
            $survey->site_condition = $data['site_condition'];
            $survey->technical_notes = $data['technical_notes'] ?? null;

            if ($request->hasFile('design_3d_file')) {
                $this->deleteFile($survey->design_3d_file);
                $survey->design_3d_file = $request->file('design_3d_file')->store('project-test/designs', 'public');
            }
            if ($request->hasFile('attachment_file')) {
                $this->deleteFile($survey->attachment_file);
                $survey->attachment_file = $request->file('attachment_file')->store('project-test/surveys', 'public');
            }
            $survey->save();

            $proposal = $project->proposal ?: new Proposal(['project_id' => $project->id]);
            $proposal->created_by = $proposal->created_by ?: $request->user()->id;
            $proposal->proposed_kwp = $data['proposed_kwp'];
            $proposal->solution_summary = $data['solution_summary'];
            $proposal->preliminary_materials_json = $this->linesToArray($data['preliminary_materials']);
            $proposal->save();

            $project->estimated_kwp = $data['proposed_kwp'];
            $project->save();
            $this->history(
                $project,
                'Kỹ thuật chỉnh sửa kết quả khảo sát, mô phỏng 3D và phương án',
                $project->status,
                $project->status,
                ['edited_section' => 'survey_proposal']
            );
        });

        return back()->with('success', 'Đã cập nhật kết quả khảo sát và phương án kỹ thuật.');
    }

    public function updateMaterialRequest(Request $request, Project $project, MaterialRequest $materialRequest)
    {
        $this->authorizeTechnicalRole();
        $this->authorizeProject($request, $project);
        abort_unless((int) $materialRequest->project_id === (int) $project->id, 404);
        abort_if(in_array($materialRequest->status, ['issued'], true), 422, 'Phiếu đã xuất kho nên không thể sửa trực tiếp.');

        $data = $request->validate([
            'needed_at' => ['required', 'date'],
            'request_note' => ['nullable', 'string'],
            'item_name' => ['required', 'array', 'min:1'],
            'item_name.*' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'array'],
            'quantity.*' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', 'array'],
            'unit.*' => ['required', 'string', 'max:40'],
            'item_note' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($project, $materialRequest, $data) {
            $materialRequest->update([
                'needed_at' => $data['needed_at'],
                'request_note' => $data['request_note'] ?? null,
                'status' => 'pending_admin',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);
            $materialRequest->items()->delete();
            foreach ($data['item_name'] as $index => $name) {
                $materialRequest->items()->create([
                    'product_id' => null,
                    'item_name' => $name,
                    'quantity' => Arr::get($data, "quantity.{$index}", 1),
                    'unit' => Arr::get($data, "unit.{$index}", 'cái'),
                    'note' => Arr::get($data, "item_note.{$index}"),
                ]);
            }

            if (! in_array($project->status, ['warehouse_preparing', 'warehouse_issued', 'ready_install', 'installing', 'acceptance_pending', 'warranty_active'], true)) {
                $project->status = 'materials_admin_review';
                $project->current_owner_role = 'admin';
                $project->progress = max((int) $project->progress, 55);
                $project->save();
            }

            $this->history(
                $project,
                'Kỹ thuật chỉnh sửa phiếu vật tư '.$materialRequest->code.'; yêu cầu Admin duyệt lại',
                $project->status,
                $project->status,
                ['material_request_id' => $materialRequest->id]
            );
        });

        return back()->with('success', 'Đã cập nhật phiếu vật tư và chuyển Admin duyệt lại.');
    }

    public function updateDailyLog(Request $request, Project $project, DailyLog $dailyLog)
    {
        $this->authorizeTechnicalRole();
        $this->authorizeProject($request, $project);
        abort_unless((int) $dailyLog->project_id === (int) $project->id, 404);

        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(['admin', 'technical_manager']) || (int) $dailyLog->created_by === (int) $user->id,
            403
        );

        $data = $request->validate([
            'log_date' => ['required', 'date'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['working', 'blocked', 'waiting_customer', 'waiting_material', 'done'])],
            'content' => ['required', 'string'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar', 'max:30720'],
        ]);

        if ($request->hasFile('attachment_file')) {
            $this->deleteFile($dailyLog->attachment_file);
            $data['attachment_file'] = $request->file('attachment_file')->store('project-test/logs', 'public');
        }
        $dailyLog->update($data);
        $this->history(
            $project,
            'Chỉnh sửa nhật ký thi công ngày '.$dailyLog->log_date->format('d/m/Y'),
            $project->status,
            $project->status,
            ['daily_log_id' => $dailyLog->id]
        );

        return back()->with('success', 'Đã cập nhật nhật ký thi công.');
    }

    public function updateAcceptanceData(Request $request, Project $project)
    {
        $this->authorizeAcceptanceRole();
        $this->authorizeProject($request, $project);
        abort_unless($project->acceptance, 422, 'Công trình chưa có hồ sơ nghiệm thu để chỉnh sửa.');

        $data = $request->validate([
            'accepted_at' => ['required', 'date'],
            'device_serials' => ['required', 'string'],
            'monitoring_link' => ['nullable', 'url', 'max:700'],
            'monitoring_account' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'warranty_months' => ['required', 'integer', 'min:1', 'max:240'],
            'next_maintenance_at' => ['nullable', 'date'],
            'report_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:30720'],
            'checklist' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($request, $project, $data) {
            $acceptance = $project->acceptance;
            $acceptanceData = [
                'accepted_at' => $data['accepted_at'],
                'checklist_json' => $data['checklist'] ?? $acceptance->checklist_json ?? [],
                'device_serials' => $data['device_serials'],
                'monitoring_link' => $data['monitoring_link'] ?? null,
                'monitoring_account' => $data['monitoring_account'] ?? null,
                'note' => $data['note'] ?? null,
            ];
            if ($request->hasFile('report_file')) {
                $this->deleteFile($acceptance->report_file);
                $acceptanceData['report_file'] = $request->file('report_file')->store('project-test/acceptances', 'public');
            }
            $acceptance->update($acceptanceData);

            $starts = Carbon::parse($data['accepted_at']);
            Warranty::updateOrCreate(['project_id' => $project->id], [
                'starts_at' => $starts->toDateString(),
                'ends_at' => $starts->copy()->addMonths((int) $data['warranty_months'])->toDateString(),
                'next_maintenance_at' => $data['next_maintenance_at'] ?? $starts->copy()->addMonths(6)->toDateString(),
                'assigned_to' => $project->lead_technician_id,
                'status' => 'active',
                'note' => 'Đã cập nhật từ hồ sơ nghiệm thu Công Trình Test new.',
            ]);

            $this->history(
                $project,
                'Cập nhật hồ sơ nghiệm thu và lịch bảo hành',
                $project->status,
                $project->status,
                ['edited_section' => 'acceptance_warranty']
            );
        });

        return back()->with('success', 'Đã cập nhật nghiệm thu và bảo hành.');
    }

    public function download(Project $project, string $kind)
    {
        $this->authorizeProject(request(), $project);
        $project->loadMissing(['survey', 'acceptance']);
        $path = match ($kind) {
            'design-3d' => $project->survey?->design_3d_file,
            'survey' => $project->survey?->attachment_file,
            'acceptance' => $project->acceptance?->report_file,
            default => null,
        };
        abort_unless($path && Storage::disk('public')->exists($path), 404);
        return Storage::disk('public')->download($path);
    }

    private function formOptions(): array
    {
        $activeCompanyId = (int) session('active_company_id', 0);
        $companies = collect();
        $customers = collect();
        $products = collect();
        $warehouses = collect();

        if (Schema::hasTable('companies')) {
            $companies = DB::table('companies')->select('id', 'name')->orderBy('name')->get();
        }
        if (Schema::hasTable('crm_customers')) {
            $customersQuery = DB::table('crm_customers')->select('id', 'name', 'phone', 'address', 'company_id')->orderByDesc('id')->limit(1000);
            if ($activeCompanyId && Schema::hasColumn('crm_customers', 'company_id')) {
                $customersQuery->where(function ($q) use ($activeCompanyId) {
                    $q->whereNull('company_id')->orWhere('company_id', $activeCompanyId);
                });
            }
            $customers = $customersQuery->get();
        }
        if (Schema::hasTable('crm_product_catalog')) {
            $products = DB::table('crm_product_catalog')->select('id', 'name', 'sku')->where('is_active', 1)->orderBy('name')->limit(1500)->get();
        }
        if (Schema::hasTable('crm_warehouses')) {
            $warehouses = DB::table('crm_warehouses')->select('id', 'name')->orderBy('name')->get();
        }

        $activeUsers = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
        $salesUsers = $this->usersByRoles(['sales', 'sales_manager']);
        $technicalManagers = $this->usersByRoles(['technical_manager']);
        $technicians = $this->usersByRoles(['ky_thuat']);

        return compact(
            'companies',
            'customers',
            'products',
            'warehouses',
            'activeUsers',
            'salesUsers',
            'technicalManagers',
            'technicians'
        );
    }

    private function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasRole('admin') || $user->can($permission)), 403);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        $this->authorizePermission('project-test.access');
        $visible = Project::query()->visibleTo($request->user())->whereKey($project->getKey())->exists();
        abort_unless($visible, 403);
    }

    private function actionAbilities(User $user): array
    {
        $isAdmin = $user->hasRole('admin');
        $isSales = $user->hasAnyRole(['sales', 'sales_manager']);
        $isTechnicalManager = $user->hasRole('technical_manager');
        $isTechnical = $user->hasRole('ky_thuat');

        return [
            'sales' => $isAdmin || $isSales,
            'technical' => $isAdmin || $isTechnicalManager || $isTechnical,
            'technicalManager' => $isAdmin || $isTechnicalManager,
            'admin' => $isAdmin || $user->can('project-test.admin'),
            'warehouse' => $isAdmin || $user->hasAnyRole(['warehouse', 'kho']),
            'acceptance' => $isAdmin || $isTechnicalManager || $user->can('project-test.acceptance'),
            'editBasic' => $isAdmin || $isSales,
            'editPeople' => $isAdmin || $isTechnicalManager,
            'editSurvey' => $isAdmin || $isTechnicalManager || $isTechnical,
            'editMaterials' => $isAdmin || $isTechnicalManager || $isTechnical,
            'editAcceptance' => $isAdmin || $isTechnicalManager || $user->can('project-test.acceptance'),
        ];
    }

    private function usersByRoles(array $roles)
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query) use ($roles): void {
                $query->whereIn('name', $roles);
            })
            ->select('users.id', 'users.name', 'users.email')
            ->distinct()
            ->orderBy('users.name')
            ->get();
    }

    private function authorizeSalesRole(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'sales', 'sales_manager']), 403);
    }

    private function authorizeTechnicalRole(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'technical_manager', 'ky_thuat']), 403);
    }

    private function authorizeTechnicalManagerRole(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'technical_manager']), 403);
    }

    private function authorizeAcceptanceRole(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasAnyRole(['admin', 'technical_manager']) || $user->can('project-test.acceptance')),
            403
        );
    }

    private function assertUserHasRole(int $userId, array $roles, string $label): User
    {
        $user = User::query()
            ->whereKey($userId)
            ->where('is_active', true)
            ->whereHas('roles', function ($query) use ($roles): void {
                $query->whereIn('name', $roles);
            })
            ->first();

        abort_unless($user, 422, $label.' không đúng vai trò hoặc tài khoản đã ngừng hoạt động.');

        return $user;
    }

    private function validateTeamAssignment(Request $request, bool $allowPastDate = false): array
    {
        $dateRules = ['required', 'date'];
        if (! $allowPastDate) {
            $dateRules[] = 'after_or_equal:today';
        }

        $data = $request->validate([
            'lead_technician_id' => ['required', 'integer'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer'],
            'work_date' => $dateRules,
            'note' => ['nullable', 'string'],
        ]);

        $this->assertUserHasRole((int) $data['lead_technician_id'], ['ky_thuat'], 'Đội trưởng thi công');
        foreach (collect($data['member_ids'] ?? [])->unique() as $memberId) {
            $this->assertUserHasRole((int) $memberId, ['ky_thuat'], 'Thành viên kỹ thuật');
        }

        return $data;
    }

    private function persistTeamAssignment(Request $request, Project $project, array $data, bool $allowEmpty = false): void
    {
        $leadId = (int) ($data['lead_technician_id'] ?? 0);
        $memberIds = collect($data['member_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if (! $leadId && $allowEmpty) {
            $project->assignments()->delete();
            $project->lead_technician_id = $project->survey?->surveyed_by;
            $project->save();
            return;
        }

        abort_unless($leadId, 422, 'Vui lòng chọn đội trưởng thi công.');
        $this->assertUserHasRole($leadId, ['ky_thuat'], 'Đội trưởng thi công');
        foreach ($memberIds as $memberId) {
            $this->assertUserHasRole($memberId, ['ky_thuat'], 'Thành viên kỹ thuật');
        }

        $project->assignments()->delete();
        $ids = $memberIds->push($leadId)->unique()->values();
        foreach ($ids as $id) {
            Assignment::create([
                'project_id' => $project->id,
                'user_id' => $id,
                'assigned_by' => $request->user()->id,
                'assignment_role' => (int) $id === $leadId ? 'leader' : 'member',
                'work_date' => $data['work_date'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
        }

        $project->lead_technician_id = $leadId;
        $project->technical_manager_id = $project->technical_manager_id ?: $request->user()->id;
        $project->save();
    }

    private function roleLabel(User $user): string
    {
        return $user->getRoleNames()->map(fn ($role) => str_replace('_', ' ', mb_strtoupper($role)))->implode(' · ') ?: 'Tài khoản';
    }

    private function transition(Project $project, string $toStatus, string $note): void
    {
        $from = $project->status;
        $info = self::STATUSES[$toStatus] ?? null;
        abort_unless($info, 500, 'Trạng thái workflow không hợp lệ.');
        $project->status = $toStatus;
        $project->current_owner_role = $info['owner'];
        $project->progress = max((int) $project->progress, (int) $info['progress']);
        if (in_array($toStatus, ['cancelled'], true)) {
            $project->progress = 0;
        }
        $project->save();
        $this->history($project, $note, $from, $toStatus);
    }

    private function history(Project $project, string $action, ?string $from, ?string $to, array $meta = []): void
    {
        History::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $action,
            'meta' => $meta ?: null,
        ]);
    }

    private function assertStatus(Project $project, array $allowed): void
    {
        abort_unless(in_array($project->status, $allowed, true), 422, 'Công trình không còn ở bước phù hợp để thực hiện thao tác này.');
    }

    private function nextProjectCode(): string
    {
        $prefix = 'CTT-'.now()->format('Ym').'-';
        $last = Project::withTrashed()->where('code', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('code');
        $number = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    private function nextMaterialCode(): string
    {
        $prefix = 'VTTEST-'.now()->format('Ym').'-';
        $last = MaterialRequest::where('code', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('code');
        $number = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    private function linesToArray(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
