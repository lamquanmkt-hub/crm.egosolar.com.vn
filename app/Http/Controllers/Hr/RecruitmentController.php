<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\RecruitmentAttachmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller quản lý toàn bộ quy trình tuyển dụng: yêu cầu, sàng lọc, phỏng vấn, thư mời và tiếp nhận.
 */
class RecruitmentController extends Controller
{
    /**
     * Trang mặc định của module tuyển dụng (tab báo cáo).
     */
    public function index()
    {
        return $this->page('reports');
    }

    /**
     * Hiển thị tab yêu cầu tuyển dụng.
     */
    public function requests()
    {
        return $this->page('requests');
    }

    /**
     * Hiển thị tab sàng lọc ứng viên.
     */
    public function screening()
    {
        return $this->page('screening');
    }

    /**
     * Hiển thị tab ứng viên (dùng chung tab sàng lọc).
     */
    public function candidates()
    {
        return $this->page('screening');
    }

    /**
     * Hiển thị tab lịch phỏng vấn.
     */
    public function interviews()
    {
        return $this->page('interviews');
    }

    /**
     * Hiển thị tab đánh giá phỏng vấn.
     */
    public function evaluations()
    {
        return $this->page('evaluations');
    }

    /**
     * Hiển thị tab thư mời nhận việc.
     */
    public function offers()
    {
        return $this->page('offers');
    }

    /**
     * Hiển thị tab tiếp nhận nhân sự mới.
     */
    public function onboarding()
    {
        return $this->page('onboarding');
    }

    /**
     * Hiển thị tab lưu trữ hồ sơ ứng viên.
     */
    public function archives()
    {
        return $this->page('archives');
    }

    /**
     * Hiển thị tab báo cáo tuyển dụng.
     */
    public function reports()
    {
        return $this->page('reports');
    }

    /**
     * Dựng dữ liệu chung cho trang tuyển dụng (yêu cầu, ứng viên, phỏng vấn, offer, thống kê) theo tab đang chọn.
     *
     * @param  string  $active  Tab đang hiển thị
     */
    private function page($active)
    {
        $this->ensureTablesReady();

        $requests = DB::table('hr_recruitment_requests')
            ->orderByDesc('id')
            ->get();

        $candidates = DB::table('hr_recruitment_candidates as c')
            ->leftJoin('hr_recruitment_requests as r', 'r.id', '=', 'c.recruitment_request_id')
            ->select('c.*', 'r.position as request_position', 'r.department as request_department')
            ->orderByDesc('c.id')
            ->get();

        $interviews = DB::table('hr_recruitment_interviews as i')
            ->leftJoin('hr_recruitment_candidates as c', 'c.id', '=', 'i.candidate_id')
            ->select('i.*', 'c.full_name as candidate_name', 'c.position as candidate_position')
            ->orderByDesc('i.interview_at')
            ->orderByDesc('i.id')
            ->get();

        $offers = DB::table('hr_recruitment_offers as o')
            ->leftJoin('hr_recruitment_candidates as c', 'c.id', '=', 'o.candidate_id')
            ->select('o.*', 'c.full_name as candidate_name', 'c.position as candidate_position')
            ->orderByDesc('o.id')
            ->get();

        $recruitmentFiles = Schema::hasTable('hr_recruitment_attachments')
            ? DB::table('hr_recruitment_attachments')->orderBy('id')->get()->groupBy(fn ($file) => $file->entity.':'.$file->record_id)
            : collect();

        $candidateStatuses = $this->candidateStatuses();
        $approvalStatuses = $this->approvalStatuses();
        $sources = $this->sources();
        $contactChannels = $this->contactChannels();
        $suitabilityLevels = $this->suitabilityLevels();
        $interviewStatuses = $this->interviewStatuses();
        $evaluationResults = $this->evaluationResults();
        $offerStatuses = $this->offerStatuses();
        $onboardingStatuses = $this->onboardingStatuses();

        $statusStats = DB::table('hr_recruitment_candidates')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $sourceStats = DB::table('hr_recruitment_candidates')
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source');

        $stats = [
            'requests' => DB::table('hr_recruitment_requests')->count(),
            'approved_requests' => DB::table('hr_recruitment_requests')->where('approval_status', 'approved')->count(),
            'candidates' => DB::table('hr_recruitment_candidates')->count(),
            'screening' => DB::table('hr_recruitment_candidates')->whereIn('status', ['new', 'screening'])->count(),
            'contacted' => DB::table('hr_recruitment_candidates')->where('status', 'contacted')->count(),
            'interviews' => DB::table('hr_recruitment_interviews')->count(),
            'evaluated' => DB::table('hr_recruitment_interviews')->whereNotNull('result')->count(),
            'offers' => DB::table('hr_recruitment_offers')->count(),
            'hired' => DB::table('hr_recruitment_candidates')->whereIn('status', ['hired', 'onboarding'])->count(),
            'onboarding' => DB::table('hr_recruitment_candidates')->where('status', 'onboarding')->count(),
            'archived' => DB::table('hr_recruitment_candidates')->where('status', 'archived')->count(),
            'rejected' => DB::table('hr_recruitment_candidates')->whereIn('status', ['rejected', 'not_fit', 'interview_failed'])->count(),
        ];

        // KPI HC01/HC03: lượt tuyển theo ứng viên để mở nhanh từ bước Mời nhận việc / Tiếp nhận.
        $kpiHireByCandidate = \App\Services\Hr\Kpi\RecruitmentKpi::ready()
            ? DB::table('hr_recruitment_hires')->whereNotNull('candidate_id')->orderBy('id')->get(['id', 'candidate_id', 'request_id', 'status'])->keyBy('candidate_id')
            : collect();
        $candidateRequest = $candidates->pluck('recruitment_request_id', 'id');

        return view('hr.recruitment.index', compact(
            'kpiHireByCandidate',
            'candidateRequest',
            'active',
            'recruitmentFiles',
            'requests',
            'candidates',
            'interviews',
            'offers',
            'candidateStatuses',
            'approvalStatuses',
            'sources',
            'contactChannels',
            'suitabilityLevels',
            'interviewStatuses',
            'evaluationResults',
            'offerStatuses',
            'onboardingStatuses',
            'statusStats',
            'sourceStats',
            'stats'
        ));
    }

    /**
     * Tạo yêu cầu tuyển dụng mới.
     */
    public function storeRequest(Request $request)
    {
        app(RecruitmentAttachmentService::class)->validate($request);
        $data = $request->validate([
            'department' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
            'requirements' => ['nullable', 'string'],
            'needed_date' => ['nullable', 'date'],
            'job_description' => ['nullable', 'string'],
            'expected_salary' => ['nullable', 'string', 'max:255'],
            'approval_status' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string'],
        ]);

        $data['approval_status'] = $data['approval_status'] ?? 'pending';
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $data += $this->approvalStamp(null, $data['approval_status'], $request);

        app(RecruitmentAttachmentService::class)->save($request, 'requests', null, $data);

        return back()->with('success', 'Đã tạo yêu cầu tuyển dụng.');
    }

    /**
     * Cập nhật yêu cầu tuyển dụng theo ID.
     *
     * @param  int|string  $id  ID yêu cầu
     */
    public function updateRequest(Request $request, $id)
    {
        app(RecruitmentAttachmentService::class)->validate($request);
        $data = $request->validate([
            'department' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
            'requirements' => ['nullable', 'string'],
            'needed_date' => ['nullable', 'date'],
            'job_description' => ['nullable', 'string'],
            'expected_salary' => ['nullable', 'string', 'max:255'],
            'approval_status' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string'],
        ]);

        $data['updated_at'] = now();
        $current = DB::table('hr_recruitment_requests')->where('id', (int) $id)->first();
        $data += $this->approvalStamp($current, $data['approval_status'] ?? ($current->approval_status ?? 'pending'), $request);
        // KPI HC01: yêu cầu đã có lượt tuyển thì số lượng không giảm dưới số suất đang dùng.
        if ($current && \App\Services\Hr\Kpi\RecruitmentKpi::ready()) {
            $active = DB::table('hr_recruitment_hires')->where('request_id', $current->id)->whereIn('status', ['pending', 'started'])->count();
            if ((int) $data['quantity'] < $active) {
                return back()->withInput()->withErrors(['quantity' => 'Yêu cầu đang có '.$active.' lượt tuyển theo dõi KPI — số lượng không nhỏ hơn số này.']);
            }
        }

        app(RecruitmentAttachmentService::class)->save($request, 'requests', (int) $id, $data);

        return back()->with('success', 'Đã cập nhật yêu cầu tuyển dụng.');
    }

    /**
     * Ghi người/giờ duyệt khi yêu cầu chuyển sang “Đã duyệt” (server ghi, không nhận từ form); bỏ duyệt thì xoá dấu.
     */
    private function approvalStamp(?object $current, string $status, Request $request): array
    {
        if (! Schema::hasColumn('hr_recruitment_requests', 'approved_at')) {
            return [];
        }
        $wasApproved = $current && in_array((string) $current->approval_status, ['approved', 'closed'], true);
        $isApproved = in_array($status, ['approved', 'closed'], true);

        return match (true) {
            $isApproved && ! $wasApproved => ['approved_by' => $request->user()?->id, 'approved_at' => now()],
            ! $isApproved && $wasApproved => ['approved_by' => null, 'approved_at' => null],
            default => [],
        };
    }

    /** Bản ghi đã gắn KPI tuyển dụng (lượt tuyển/chi phí) thì không xoá — dùng Hủy để giữ lịch sử. */
    private function kpiLinked(string $entity, int $id): ?string
    {
        if (! \App\Services\Hr\Kpi\RecruitmentKpi::ready()) {
            return null;
        }
        $linked = match ($entity) {
            'requests' => DB::table('hr_recruitment_hires')->where('request_id', $id)->exists() || DB::table('hr_recruitment_costs')->where('request_id', $id)->exists(),
            'candidates' => DB::table('hr_recruitment_hires')->where('candidate_id', $id)->exists(),
            'offers' => DB::table('hr_recruitment_hires')->where('offer_id', $id)->exists(),
            default => false,
        };

        return $linked ? 'Bản ghi đã gắn lượt tuyển/chi phí theo dõi KPI — không xoá được. Dùng “Hủy” ở trang KPI tuyển dụng để giữ lịch sử.' : null;
    }

    /**
     * Xoá yêu cầu tuyển dụng theo ID.
     *
     * @param  int|string  $id  ID yêu cầu
     */
    public function destroyRequest($id)
    {
        if ($blocked = $this->kpiLinked('requests', (int) $id)) {
            return back()->withErrors(['kpi' => $blocked]);
        }
        app(RecruitmentAttachmentService::class)->delete('requests', (int) $id);

        return back()->with('success', 'Đã xoá yêu cầu tuyển dụng.');
    }

    /**
     * Thêm ứng viên mới vào kho sàng lọc.
     */
    public function storeCandidate(Request $request)
    {
        app(RecruitmentAttachmentService::class)->validate($request);
        $data = $request->validate([
            'recruitment_request_id' => ['nullable', 'integer'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'max:255'],
            'zalo' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'cv_link' => ['nullable', 'string', 'max:1000'],
            'contact_channel' => ['nullable', 'string', 'max:100'],
            'contacted_at' => ['nullable', 'date'],
            'suitability' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'reject_reason' => ['nullable', 'string'],
            'archive_note' => ['nullable', 'string'],
            'archive_until' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $this->normalizeDateTimes($data, ['contacted_at']);

        $data['status'] = $data['status'] ?? 'new';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        app(RecruitmentAttachmentService::class)->save($request, 'candidates', null, $this->onlyExistingColumns('hr_recruitment_candidates', $data));

        return back()->with('success', 'Đã thêm ứng viên vào kho sàng lọc.');
    }

    /**
     * Cập nhật thông tin ứng viên theo ID.
     *
     * @param  int|string  $id  ID ứng viên
     */
    public function updateCandidate(Request $request, $id)
    {
        app(RecruitmentAttachmentService::class)->validate($request);
        $data = $request->validate([
            'recruitment_request_id' => ['nullable', 'integer'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'max:255'],
            'zalo' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'cv_link' => ['nullable', 'string', 'max:1000'],
            'contact_channel' => ['nullable', 'string', 'max:100'],
            'contacted_at' => ['nullable', 'date'],
            'suitability' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'reject_reason' => ['nullable', 'string'],
            'archive_note' => ['nullable', 'string'],
            'archive_until' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $this->normalizeDateTimes($data, ['contacted_at']);

        $data['updated_at'] = now();
        // KPI HC01: ứng viên đã có lượt tuyển thì không chuyển sang yêu cầu khác (mất liên kết yêu cầu → ứng viên).
        $current = DB::table('hr_recruitment_candidates')->where('id', (int) $id)->first();
        if ($current && $this->kpiLinked('candidates', (int) $id) && (int) ($data['recruitment_request_id'] ?? 0) !== (int) $current->recruitment_request_id) {
            return back()->withInput()->withErrors(['recruitment_request_id' => 'Ứng viên đã có lượt tuyển theo dõi KPI — không đổi yêu cầu tuyển.']);
        }

        app(RecruitmentAttachmentService::class)->save($request, 'candidates', (int) $id, $this->onlyExistingColumns('hr_recruitment_candidates', $data));

        return back()->with('success', 'Đã cập nhật ứng viên.');
    }

    /**
     * Xoá ứng viên theo ID.
     *
     * @param  int|string  $id  ID ứng viên
     */
    public function destroyCandidate($id)
    {
        if ($blocked = $this->kpiLinked('candidates', (int) $id)) {
            return back()->withErrors(['kpi' => $blocked]);
        }
        app(RecruitmentAttachmentService::class)->delete('candidates', (int) $id);

        return back()->with('success', 'Đã xoá ứng viên.');
    }

    /**
     * Tạo lịch phỏng vấn mới và đồng bộ trạng thái ứng viên.
     */
    public function storeInterview(Request $request)
    {
        app(RecruitmentAttachmentService::class)->validate($request);
        $data = $request->validate([
            'candidate_id' => ['required', 'integer'],
            'interview_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'interview_form' => ['nullable', 'string', 'max:100'],
            'interviewer' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'result' => ['nullable', 'string', 'max:255'],
            'evaluation_result' => ['nullable', 'string', 'max:100'],
            'expected_start_date' => ['nullable', 'date'],
            'cancel_reason' => ['nullable', 'string'],
            'evaluation' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $this->normalizeDateTimes($data, ['interview_at']);

        $data['status'] = $data['status'] ?? 'scheduled';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        app(RecruitmentAttachmentService::class)->save($request, 'interviews', null, $this->onlyExistingColumns('hr_recruitment_interviews', $data));

        $this->syncCandidateFromInterview((int) $data['candidate_id'], $data);

        return back()->with('success', 'Đã đặt lịch phỏng vấn.');
    }

    /**
     * Cập nhật lịch / đánh giá phỏng vấn và đồng bộ trạng thái ứng viên.
     *
     * @param  int|string  $id  ID lịch phỏng vấn
     */
    public function updateInterview(Request $request, $id)
    {
        app(RecruitmentAttachmentService::class)->validate($request);
        $data = $request->validate([
            'candidate_id' => ['required', 'integer'],
            'interview_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'interview_form' => ['nullable', 'string', 'max:100'],
            'interviewer' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'result' => ['nullable', 'string', 'max:255'],
            'evaluation_result' => ['nullable', 'string', 'max:100'],
            'expected_start_date' => ['nullable', 'date'],
            'cancel_reason' => ['nullable', 'string'],
            'evaluation' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $this->normalizeDateTimes($data, ['interview_at']);

        $data['updated_at'] = now();

        app(RecruitmentAttachmentService::class)->save($request, 'interviews', (int) $id, $this->onlyExistingColumns('hr_recruitment_interviews', $data));

        $this->syncCandidateFromInterview((int) $data['candidate_id'], $data);

        return back()->with('success', 'Đã cập nhật lịch / đánh giá phỏng vấn.');
    }

    /**
     * Xoá lịch phỏng vấn theo ID.
     *
     * @param  int|string  $id  ID lịch phỏng vấn
     */
    public function destroyInterview($id)
    {
        app(RecruitmentAttachmentService::class)->delete('interviews', (int) $id);

        return back()->with('success', 'Đã xoá lịch phỏng vấn.');
    }

    /**
     * Tạo thư mời / đề nghị nhận việc và đồng bộ trạng thái ứng viên.
     */
    public function storeOffer(Request $request)
    {
        app(RecruitmentAttachmentService::class)->validate($request);
        $data = $request->validate([
            'candidate_id' => ['required', 'integer'],
            'offer_date' => ['nullable', 'date'],
            'salary_offer' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:100'],
            'response_note' => ['nullable', 'string'],
            'onboarding_status' => ['nullable', 'string', 'max:100'],
            'onboarding_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $data['status'] = $data['status'] ?? 'draft';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        app(RecruitmentAttachmentService::class)->save($request, 'offers', null, $this->onlyExistingColumns('hr_recruitment_offers', $data));

        $this->syncCandidateFromOffer((int) $data['candidate_id'], $data);

        return back()->with('success', 'Đã tạo thư mời / đề nghị nhận việc.');
    }

    /**
     * Cập nhật thư mời / tiếp nhận nhân sự và đồng bộ trạng thái ứng viên.
     *
     * @param  int|string  $id  ID thư mời
     */
    public function updateOffer(Request $request, $id)
    {
        app(RecruitmentAttachmentService::class)->validate($request);
        $data = $request->validate([
            'candidate_id' => ['required', 'integer'],
            'offer_date' => ['nullable', 'date'],
            'salary_offer' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:100'],
            'response_note' => ['nullable', 'string'],
            'onboarding_status' => ['nullable', 'string', 'max:100'],
            'onboarding_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $data['updated_at'] = now();

        app(RecruitmentAttachmentService::class)->save($request, 'offers', (int) $id, $this->onlyExistingColumns('hr_recruitment_offers', $data));

        $this->syncCandidateFromOffer((int) $data['candidate_id'], $data);

        return back()->with('success', 'Đã cập nhật thư mời / tiếp nhận nhân sự.');
    }

    /**
     * Xoá đề nghị nhận việc theo ID.
     *
     * @param  int|string  $id  ID thư mời
     */
    public function destroyOffer($id)
    {
        if ($blocked = $this->kpiLinked('offers', (int) $id)) {
            return back()->withErrors(['kpi' => $blocked]);
        }
        app(RecruitmentAttachmentService::class)->delete('offers', (int) $id);

        return back()->with('success', 'Đã xoá đề nghị nhận việc.');
    }

    /**
     * Suy ra và cập nhật trạng thái ứng viên dựa trên trạng thái / kết quả phỏng vấn.
     */
    private function syncCandidateFromInterview(int $candidateId, array $data): void
    {
        $status = $data['status'] ?? 'scheduled';
        $evaluationResult = $data['evaluation_result'] ?? null;
        $candidateStatus = 'interview_scheduled';

        if ($status === 'interviewing') {
            $candidateStatus = 'interviewing';
        }

        if ($status === 'done') {
            $candidateStatus = match ($evaluationResult) {
                'passed' => 'interview_passed',
                'failed' => 'interview_failed',
                'second_round' => 'interview_scheduled',
                default => 'interviewing',
            };
        }

        if (in_array($status, ['cancelled', 'no_show'], true)) {
            $candidateStatus = 'archived';
        }

        DB::table('hr_recruitment_candidates')
            ->where('id', $candidateId)
            ->update($this->onlyExistingColumns('hr_recruitment_candidates', [
                'status' => $candidateStatus,
                'reject_reason' => $data['cancel_reason'] ?? null,
                'updated_at' => now(),
            ]));
    }

    /**
     * Suy ra và cập nhật trạng thái ứng viên dựa trên trạng thái thư mời.
     */
    private function syncCandidateFromOffer(int $candidateId, array $data): void
    {
        $offerStatus = $data['status'] ?? 'draft';
        $candidateStatus = match ($offerStatus) {
            'accepted' => 'hired',
            'onboarded' => 'onboarding',
            'rejected' => 'rejected',
            'archived' => 'archived',
            default => 'offer',
        };

        DB::table('hr_recruitment_candidates')
            ->where('id', $candidateId)
            ->update($this->onlyExistingColumns('hr_recruitment_candidates', [
                'status' => $candidateStatus,
                'reject_reason' => $offerStatus === 'rejected' ? ($data['response_note'] ?? null) : null,
                'updated_at' => now(),
            ]));
    }

    /**
     * Danh sách trạng thái ứng viên (key => nhãn tiếng Việt).
     */
    private function candidateStatuses()
    {
        return [
            'new' => 'Ứng viên mới',
            'screening' => 'Sàng lọc CV',
            'contacted' => 'Đã liên hệ',
            'not_fit' => 'Chưa phù hợp',
            'interview_scheduled' => 'Đặt lịch PV',
            'interviewing' => 'Đang PV',
            'interview_passed' => 'Đạt PV',
            'interview_failed' => 'Không đạt PV',
            'offer' => 'Mời nhận việc',
            'hired' => 'Đã nhận việc',
            'onboarding' => 'Tiếp nhận NS mới',
            'archived' => 'Lưu hồ sơ',
            'rejected' => 'Từ chối',
        ];
    }

    /**
     * Danh sách trạng thái duyệt yêu cầu tuyển dụng.
     */
    private function approvalStatuses()
    {
        return [
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối duyệt',
            'closed' => 'Hoàn tất',
        ];
    }

    /**
     * Danh sách nguồn ứng viên.
     */
    private function sources()
    {
        return [
            'TopCV',
            'Facebook',
            'LinkedIn',
            'Vietnamworks',
            'Zalo',
            'Website',
            'Giới thiệu nội bộ',
            'Khác',
        ];
    }

    /**
     * Danh sách kênh liên hệ ứng viên.
     */
    private function contactChannels()
    {
        return [
            'Phone' => 'Phone',
            'Mail' => 'Mail',
            'Zalo' => 'Zalo',
            'Facebook' => 'Facebook',
            'Khác' => 'Khác',
        ];
    }

    /**
     * Danh sách mức độ phù hợp của ứng viên.
     */
    private function suitabilityLevels()
    {
        return [
            'good' => 'Phù hợp',
            'consider' => 'Cần cân nhắc',
            'not_fit' => 'Chưa phù hợp',
        ];
    }

    /**
     * Danh sách trạng thái lịch phỏng vấn.
     */
    private function interviewStatuses()
    {
        return [
            'scheduled' => 'Đặt lịch PV',
            'interviewing' => 'Đang PV',
            'rescheduled' => 'Hẹn ngày khác',
            'done' => 'Đã PV',
            'cancelled' => 'Huỷ lịch',
            'no_show' => 'Không đến',
        ];
    }

    /**
     * Danh sách kết quả đánh giá phỏng vấn.
     */
    private function evaluationResults()
    {
        return [
            'passed' => 'Đạt',
            'failed' => 'Không đạt',
            'second_round' => 'Hẹn vòng tiếp',
            'consider' => 'Cân nhắc',
        ];
    }

    /**
     * Danh sách trạng thái thư mời nhận việc.
     */
    private function offerStatuses()
    {
        return [
            'draft' => 'Soạn thư mời',
            'sent' => 'Đã gửi thư mời',
            'accepted' => 'Ứng viên đồng ý',
            'rejected' => 'Từ chối / Không phản hồi',
            'onboarded' => 'Đã tiếp nhận',
            'archived' => 'Lưu hồ sơ',
        ];
    }

    /**
     * Danh sách trạng thái tiếp nhận nhân sự mới.
     */
    private function onboardingStatuses()
    {
        return [
            'pending' => 'Chờ tiếp nhận',
            'collecting_docs' => 'Bổ sung hồ sơ',
            'signed' => 'Đã ký nhận việc',
            'completed' => 'Hoàn tất tiếp nhận',
        ];
    }

    /**
     * Chuẩn hoá các trường datetime dạng HTML (thay chữ T bằng khoảng trắng) ngay trên mảng dữ liệu.
     */
    private function normalizeDateTimes(array &$data, array $fields): void
    {
        foreach ($fields as $field) {
            if (! empty($data[$field]) && is_string($data[$field])) {
                $data[$field] = str_replace('T', ' ', $data[$field]);
            }
        }
    }

    /**
     * Lọc mảng dữ liệu chỉ giữ các key trùng với cột đang tồn tại của bảng.
     */
    private function onlyExistingColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        $columns = Schema::getColumnListing($table);

        return collect($data)
            ->filter(fn ($value, $key) => in_array($key, $columns, true))
            ->all();
    }

    /**
     * Bổ sung các cột còn thiếu cho các bảng tuyển dụng nếu bảng đã tồn tại.
     */
    private function ensureTablesReady(): void
    {
        foreach ([
            'hr_recruitment_requests',
            'hr_recruitment_candidates',
            'hr_recruitment_interviews',
            'hr_recruitment_offers',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        $this->addColumnIfMissing('hr_recruitment_candidates', 'cv_link', fn (Blueprint $table) => $table->string('cv_link', 1000)->nullable()->after('source'));
        $this->addColumnIfMissing('hr_recruitment_candidates', 'zalo', fn (Blueprint $table) => $table->string('zalo')->nullable()->after('email'));
        $this->addColumnIfMissing('hr_recruitment_candidates', 'contact_channel', fn (Blueprint $table) => $table->string('contact_channel')->nullable()->after('cv_link'));
        $this->addColumnIfMissing('hr_recruitment_candidates', 'contacted_at', fn (Blueprint $table) => $table->dateTime('contacted_at')->nullable()->after('contact_channel'));
        $this->addColumnIfMissing('hr_recruitment_candidates', 'suitability', fn (Blueprint $table) => $table->string('suitability')->nullable()->after('contacted_at'));
        $this->addColumnIfMissing('hr_recruitment_candidates', 'reject_reason', fn (Blueprint $table) => $table->text('reject_reason')->nullable()->after('status'));
        $this->addColumnIfMissing('hr_recruitment_candidates', 'archive_note', fn (Blueprint $table) => $table->text('archive_note')->nullable()->after('reject_reason'));
        $this->addColumnIfMissing('hr_recruitment_candidates', 'archive_until', fn (Blueprint $table) => $table->date('archive_until')->nullable()->after('archive_note'));

        $this->addColumnIfMissing('hr_recruitment_interviews', 'status', fn (Blueprint $table) => $table->string('status')->default('scheduled')->after('interviewer'));
        $this->addColumnIfMissing('hr_recruitment_interviews', 'cancel_reason', fn (Blueprint $table) => $table->text('cancel_reason')->nullable()->after('result'));
        $this->addColumnIfMissing('hr_recruitment_interviews', 'evaluation', fn (Blueprint $table) => $table->text('evaluation')->nullable()->after('cancel_reason'));
        $this->addColumnIfMissing('hr_recruitment_interviews', 'evaluation_result', fn (Blueprint $table) => $table->string('evaluation_result')->nullable()->after('evaluation'));
        $this->addColumnIfMissing('hr_recruitment_interviews', 'expected_start_date', fn (Blueprint $table) => $table->date('expected_start_date')->nullable()->after('evaluation_result'));

        $this->addColumnIfMissing('hr_recruitment_offers', 'response_note', fn (Blueprint $table) => $table->text('response_note')->nullable()->after('status'));
        $this->addColumnIfMissing('hr_recruitment_offers', 'onboarding_status', fn (Blueprint $table) => $table->string('onboarding_status')->nullable()->after('response_note'));
        $this->addColumnIfMissing('hr_recruitment_offers', 'onboarding_date', fn (Blueprint $table) => $table->date('onboarding_date')->nullable()->after('onboarding_status'));
    }

    /**
     * Thêm cột vào bảng nếu bảng tồn tại và cột chưa có.
     */
    private function addColumnIfMissing(string $table, string $column, callable $definition): void
    {
        if (Schema::hasTable($table) && ! Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $blueprint) use ($definition) {
                $definition($blueprint);
            });
        }
    }
}
