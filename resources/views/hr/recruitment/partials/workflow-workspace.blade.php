@php
    $textField = fn($name, $label, $type = 'text', $extra = []) => array_merge(['name'=>$name, 'label'=>$label, 'type'=>$type], $extra);
    $selectField = fn($name, $label, $options, $default = '') => ['name'=>$name, 'label'=>$label, 'type'=>'select', 'options'=>$options, 'default'=>$default];
    $candidateOptions = $candidates->mapWithKeys(fn($c) => [$c->id => $c->full_name.' · '.($c->position ?: 'Chưa ghi vị trí')])->all();
    $requestOptions = $requests->mapWithKeys(fn($r) => [$r->id => $r->position.' · '.$r->department])->all();
    $sourceOptions = array_combine($sources, $sources);
    $candidateField = $selectField('candidate_id', 'Ứng viên', [''=>'Chọn ứng viên…'] + $candidateOptions);
    $noteField = $textField('note', 'Ghi chú nội bộ', 'textarea');
    $candidateFields = [
        $textField('full_name','Họ tên'), $textField('phone','Số điện thoại'), $textField('email','Email'), $textField('zalo','Zalo'),
        $textField('position','Vị trí ứng tuyển'), $selectField('source','Nguồn ứng viên',[''=>'Chưa ghi nguồn'] + $sourceOptions, $sources[0] ?? ''),
        $textField('cv_link','Link CV / hồ sơ'), $selectField('status','Trạng thái ứng viên',$candidateStatuses,'screening'),
        $selectField('contact_channel','Kênh liên hệ',[''=>'Chưa liên hệ'] + $contactChannels),
        $textField('contacted_at','Thời gian liên hệ','datetime-local'),
        $selectField('suitability','Mức độ phù hợp',[''=>'Chưa đánh giá'] + $suitabilityLevels),
        $selectField('recruitment_request_id','Yêu cầu tuyển dụng',[''=>'Không gắn'] + $requestOptions),
        $textField('note','Ghi chú sàng lọc','textarea'), $textField('reject_reason','Lý do từ chối / chưa phù hợp','textarea',['editOnly'=>true]),
    ];
    $interviewFields = [
        $candidateField, $textField('interview_at','Ngày giờ phỏng vấn','datetime-local'), $textField('location','Địa điểm / Link họp'),
        $selectField('interview_form','Hình thức',['Offline'=>'Trực tiếp','Online'=>'Trực tuyến','Điện thoại'=>'Điện thoại'],'Offline'),
        $textField('interviewer','Người phỏng vấn'), $selectField('status','Trạng thái lịch',$interviewStatuses,'scheduled'),
        $textField('cancel_reason','Lý do hủy / hẹn lại','textarea'), array_merge($noteField,['editOnly'=>true]),
    ];
    $evaluationFields = [
        $candidateField, $textField('interview_at','Ngày giờ phỏng vấn','datetime-local'), $textField('interviewer','Người phỏng vấn'),
        $selectField('status','Trạng thái lịch',$interviewStatuses,'scheduled'),
        $selectField('evaluation_result','Kết quả đánh giá',[''=>'Chưa chốt'] + $evaluationResults),
        $textField('result','Kết quả tóm tắt'), $textField('expected_start_date','Ngày có thể nhận việc','date'),
        $textField('evaluation','Đánh giá chuyên môn / thái độ / mức phù hợp','textarea'), $noteField,
    ];
    $offerFields = [
        $candidateField, $textField('offer_date','Ngày gửi thư mời','date'), $textField('salary_offer','Mức lương đề nghị'),
        $textField('start_date','Ngày nhận việc','date'), $selectField('status','Trạng thái phản hồi',$offerStatuses,'draft'),
        $textField('response_note','Phản hồi của ứng viên','textarea'), array_merge($noteField,['editOnly'=>true]),
    ];
    $onboardingFields = [
        $candidateField, $textField('start_date','Ngày nhận việc','date'), $textField('salary_offer','Mức lương đề nghị'),
        $selectField('onboarding_status','Tình trạng tiếp nhận',$onboardingStatuses,'pending'),
        $textField('onboarding_date','Ngày tiếp nhận','date'), $textField('note','Hồ sơ cần bổ sung / ghi chú ngày đầu','textarea'),
        $textField('status','','hidden',['fixed'=>'onboarded']), $textField('offer_date','','hidden'), $textField('response_note','','hidden'),
    ];
    $archiveFields = [
        $textField('full_name','Họ tên'), $textField('position','Vị trí ứng tuyển'),
        $selectField('source','Nguồn ứng viên',[''=>'Chưa ghi nguồn'] + $sourceOptions),
        $selectField('status','Trạng thái ứng viên',$candidateStatuses),
        $textField('reject_reason','Lý do từ chối / chưa đạt','textarea'), $textField('archive_note','Ghi chú lưu hồ sơ','textarea'),
        $textField('archive_until','Lưu đến ngày','date'),
    ];
    foreach (['phone','email','zalo','cv_link','contact_channel','contacted_at','suitability','note','recruitment_request_id'] as $preserved) {
        $archiveFields[] = $textField($preserved,'','hidden');
    }
    $onboardingRows = $offers->filter(fn($o) => in_array($o->status ?? '', ['accepted','onboarded'],true) || !empty($o->onboarding_status));
    $archiveRows = $candidates->filter(fn($c) => in_array($c->status ?? '', ['archived','rejected','not_fit','interview_failed'],true));
    $workflows = [
        'screening'=>['rows'=>$candidates,'fields'=>$candidateFields,'route'=>'candidates','create'=>'Thêm ứng viên','save'=>'Lưu ứng viên','title'=>'Kho ứng viên','statusOptions'=>$candidateStatuses,'statusField'=>'status','headers'=>['Ứng viên / Vị trí','Liên hệ','Nguồn / Mức phù hợp','Trạng thái'],'empty'=>'Chưa có ứng viên. Bấm “Thêm ứng viên” để nhập hồ sơ đầu tiên.'],
        'interviews'=>['rows'=>$interviews,'fields'=>$interviewFields,'route'=>'interviews','create'=>'Đặt lịch phỏng vấn','save'=>'Lưu lịch phỏng vấn','title'=>'Danh sách lịch phỏng vấn','statusOptions'=>$interviewStatuses,'statusField'=>'status','headers'=>['Ứng viên / Vị trí','Thời gian / Hình thức','Người PV / Địa điểm','Trạng thái'],'empty'=>'Chưa có lịch phỏng vấn. Thêm ứng viên ở bước Sàng lọc rồi đặt lịch tại đây.'],
        'evaluations'=>['rows'=>$interviews,'fields'=>$evaluationFields,'route'=>'interviews','save'=>'Lưu đánh giá','title'=>'Danh sách đánh giá','statusOptions'=>[''=>'Chưa chốt'] + $evaluationResults,'statusField'=>'evaluation_result','headers'=>['Ứng viên / Vị trí','Lịch / Người phỏng vấn','Nhận xét tóm tắt','Kết quả'],'empty'=>'Chưa có lịch phỏng vấn để đánh giá. Đặt lịch ở bước 3 trước.'],
        'offers'=>['rows'=>$offers,'fields'=>$offerFields,'route'=>'offers','create'=>'Tạo thư mời','save'=>'Lưu thư mời','title'=>'Danh sách thư mời nhận việc','statusOptions'=>$offerStatuses,'statusField'=>'status','headers'=>['Ứng viên / Vị trí','Ngày nhận / Ngày gửi','Lương đề nghị','Phản hồi'],'empty'=>'Chưa có thư mời nhận việc. Chọn “Tạo thư mời” để thêm.'],
        'onboarding'=>['rows'=>$onboardingRows,'fields'=>$onboardingFields,'route'=>'offers','save'=>'Lưu tiếp nhận','title'=>'Danh sách tiếp nhận nhân sự','statusOptions'=>$onboardingStatuses,'statusField'=>'onboarding_status','headers'=>['Nhân sự / Vị trí','Ngày nhận / Tiếp nhận','Lương đề nghị','Tiếp nhận'],'empty'=>'Chưa có ứng viên đồng ý thư mời để tiếp nhận. Kiểm tra phản hồi ở bước 5.'],
        'archives'=>['rows'=>$archiveRows,'fields'=>$archiveFields,'route'=>'candidates','save'=>'Lưu hồ sơ','title'=>'Kho hồ sơ lưu trữ','statusOptions'=>$candidateStatuses,'statusField'=>'status','headers'=>['Ứng viên / Vị trí','Lưu đến / Nguồn','Lý do lưu','Trạng thái'],'empty'=>'Chưa có hồ sơ lưu trữ hoặc từ chối.'],
    ];
    $workflow = $workflows[$active];
    $formatDate = fn($value,$time = false) => $value ? \Illuminate\Support\Carbon::parse($value)->format($time ? 'd/m/Y H:i' : 'd/m/Y') : 'Chưa đặt';
@endphp

@if(isset($workflow['create']))
<details class="rec-card rec-create-panel" id="rec-workflow-create" @if($errors->any() && old('_workflow_form') === $active.'-new') open @endif>
    <summary class="rec-create-summary"><span class="rec-create-label"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ $workflow['create'] }}</span><span class="rec-create-hint">Bấm để nhập thông tin mới</span><i class="bi bi-chevron-down rec-expand-icon" aria-hidden="true"></i></summary>
    <div class="rec-card-body">
        @if($active !== 'screening' && $candidates->isEmpty())
            <p class="rec-card-note">Chưa có ứng viên để chọn. <a href="{{ route('hr.recruitment.screening') }}">Thêm ứng viên ở bước Sàng lọc</a> trước.</p>
        @else
        <form method="POST" enctype="multipart/form-data" action="{{ route('hr.recruitment.'.$workflow['route'].'.store') }}">@csrf
            @include('hr.recruitment.partials.workflow-fields',['formRow'=>null])
            @include('hr.recruitment.partials.attachments',['attachmentEntity'=>$workflow['route'],'attachmentRecord'=>null,'attachmentRestore'=>$errors->any() && old('_workflow_form') === $active.'-new'])
            <div class="rec-form-foot"><button class="rec-btn" type="submit">{{ $workflow['create'] }}</button></div>
        </form>
        @endif
    </div>
</details>
@endif

<section class="rec-card rec-request-list rec-workflow-list" data-rec-workflow>
    <div class="rec-card-head"><div><h2 class="rec-card-title">{{ $workflow['title'] }} <span class="rec-list-count" data-rec-count aria-live="polite">{{ $workflow['rows']->count() }}</span></h2><p class="rec-card-note">Bấm “Xem / sửa” ở từng dòng để mở nội dung đầy đủ.</p></div>@if($active === 'archives')<a class="rec-btn-outline" href="{{ route('hr.recruitment.reports') }}">Báo cáo tổng</a>@endif</div>
    @if($workflow['rows']->isNotEmpty())
    <div class="rec-request-toolbar">
        <div class="rec-field rec-request-search"><label class="rec-label" for="rec-workflow-search">Tìm kiếm</label><input class="rec-input" type="search" id="rec-workflow-search" data-rec-search placeholder="Họ tên, vị trí hoặc thông tin liên quan…"></div>
        <div class="rec-field"><label class="rec-label" for="rec-workflow-status">{{ $active === 'evaluations' ? 'Kết quả đánh giá' : 'Trạng thái' }}</label><select class="rec-select" id="rec-workflow-status" data-rec-status><option value="">Tất cả</option>@foreach($workflow['statusOptions'] as $key=>$label)<option value="{{ $key === '' ? '__unset' : $key }}">{{ $label }}</option>@endforeach</select></div>
    </div>
    <div class="rec-request-columns rec-workflow-columns" aria-hidden="true">@foreach($workflow['headers'] as $header)<span>{{ $header }}</span>@endforeach<span></span></div>
    @foreach($workflow['rows'] as $row)
        @php
            $rowKey = $active.'-'.$row->id;
            $rowName = $row->full_name ?? $row->candidate_name ?? 'Ứng viên không còn trong hệ thống';
            $rowPosition = $row->position ?? $row->candidate_position ?? 'Chưa ghi vị trí';
            $rowStatus = $row->{$workflow['statusField']} ?? '';
            if ($active === 'onboarding' && !$rowStatus) $rowStatus = 'pending';
            $rowStatusLabel = $workflow['statusOptions'][$rowStatus] ?? ($rowStatus ?: 'Chưa ghi nhận');
            $cells = match($active) {
                'screening'=>[[$row->phone ?: 'Chưa ghi SĐT',$row->email ?: 'Chưa ghi email'],[$row->source ?: 'Chưa ghi nguồn',$suitabilityLevels[$row->suitability ?? ''] ?? 'Chưa đánh giá']],
                'interviews'=>[[$formatDate($row->interview_at,true),$row->interview_form ?: 'Chưa ghi hình thức'],[$row->interviewer ?: 'Chưa phân công',$row->location ?: 'Chưa ghi địa điểm']],
                'evaluations'=>[[$formatDate($row->interview_at,true),$row->interviewer ?: 'Chưa phân công'],[$row->result ?: 'Chưa ghi kết quả',$row->evaluation ?: 'Chưa có nhận xét']],
                'offers'=>[[$formatDate($row->start_date),'Gửi: '.$formatDate($row->offer_date)],[$row->salary_offer ?: 'Chưa ghi mức lương',$row->response_note ?: 'Chưa có phản hồi']],
                'onboarding'=>[[$formatDate($row->start_date),'Tiếp nhận: '.$formatDate($row->onboarding_date ?? null)],[$row->salary_offer ?: 'Chưa ghi mức lương',$row->note ?: 'Chưa có ghi chú']],
                'archives'=>[[$formatDate($row->archive_until ?? null),$row->source ?: 'Chưa ghi nguồn'],[$row->reject_reason ?: 'Chưa ghi lý do',$row->archive_note ?: 'Chưa có ghi chú lưu']],
            };
            $rowColor = in_array($rowStatus,['good','passed','accepted','completed','done','onboarded','hired','interview_passed'],true) ? 'green' : (in_array($rowStatus,['rejected','failed','cancelled','no_show','not_fit','interview_failed'],true) ? 'red' : (in_array($rowStatus,['','new','draft','archived'],true) ? 'gray' : 'amber'));
            $searchText = implode(' ',[$rowName,$rowPosition,...array_merge(...$cells)]);
        @endphp
        <details class="rec-request" id="rec-workflow-{{ $rowKey }}" data-rec-row data-search="{{ $searchText }}" data-status="{{ $rowStatus === '' ? '__unset' : $rowStatus }}" @if($errors->any() && old('_workflow_form') === $rowKey) open @endif>
            <summary class="rec-request-row rec-workflow-row">
                <span class="rec-request-person"><strong>{{ $rowName }}</strong><small>{{ $rowPosition }}</small></span>
                @foreach($cells as $cell)<span class="rec-request-cell rec-workflow-cell"><small>{{ $workflow['headers'][$loop->index+1] }}</small><span>{{ $cell[0] }}</span><span class="rec-workflow-secondary">{{ $cell[1] }}</span></span>@endforeach
                <span><span class="rec-pill {{ $rowColor }}">{{ $rowStatusLabel }}</span></span>
                <span class="rec-request-open">Xem / sửa <i class="bi bi-chevron-down rec-expand-icon" aria-hidden="true"></i></span>
            </summary>
            <div class="rec-request-editor">
                @if($active === 'screening' && !empty($row->cv_link) && in_array(strtolower(parse_url($row->cv_link,PHP_URL_SCHEME) ?? ''),['http','https'],true))<p><a class="rec-btn-outline" href="{{ $row->cv_link }}" target="_blank" rel="noopener noreferrer">Mở CV / hồ sơ <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></p>@endif
                @if(in_array($active, ['offers', 'onboarding'], true) && isset($kpiHireByCandidate) && \App\Services\Hr\Kpi\RecruitmentKpi::ready())
                    @php $kpiHire = $kpiHireByCandidate->get($row->candidate_id ?? 0); $kpiReq = $candidateRequest[$row->candidate_id ?? 0] ?? null; @endphp
                    <p class="rec-kpi-link" data-rec-kpi-hire="{{ $kpiHire->id ?? '' }}">
                        @if($kpiHire)
                            <a class="rec-btn-outline" href="{{ route('hr.recruitment.hires.show', $kpiHire->id) }}">Lượt tuyển KPI #{{ $kpiHire->id }} (nhận việc thực tế, thử việc) →</a>
                        @elseif($kpiReq)
                            <a class="rec-btn-outline" href="{{ route('hr.recruitment.requests.kpi', $kpiReq) }}">Ghi lượt tuyển KPI tại yêu cầu tuyển →</a>
                            <span class="rec-small">Ngày nhận việc ở thư mời là ngày dự kiến — KPI HC01 dùng ngày nhận việc thực tế ở lượt tuyển.</span>
                        @else
                            <span class="rec-small">Ứng viên chưa gắn yêu cầu tuyển (bước Sàng lọc) — chưa theo dõi KPI HC01.</span>
                        @endif
                    </p>
                @endif
                <form method="POST" enctype="multipart/form-data" action="{{ route('hr.recruitment.'.$workflow['route'].'.update',$row->id) }}">@csrf @method('PUT')
                    @include('hr.recruitment.partials.workflow-fields',['formRow'=>$row])
                    @include('hr.recruitment.partials.attachments',['attachmentEntity'=>$workflow['route'],'attachmentRecord'=>$row->id,'attachmentRestore'=>$errors->any() && old('_workflow_form') === $rowKey])
                    <div class="rec-form-foot"><button class="rec-btn" type="submit">{{ $workflow['save'] }}</button></div>
                </form>
                @if(in_array($active,['screening','interviews','offers'],true))
                    <div class="rec-request-danger-zone"><form method="POST" enctype="multipart/form-data" action="{{ route('hr.recruitment.'.$workflow['route'].'.destroy',$row->id) }}" onsubmit="return confirm('Xoá bản ghi này?')">@csrf @method('DELETE')<button class="rec-btn-danger" type="submit">Xoá bản ghi</button></form></div>
                @endif
            </div>
        </details>
    @endforeach
    <p class="rec-filter-empty" data-rec-empty hidden>Không có kết quả khớp bộ lọc. Hãy đổi từ khóa hoặc trạng thái.</p>
    @else
    <div class="rec-request-empty"><i class="bi bi-inbox" aria-hidden="true"></i><strong>{{ $workflow['empty'] }}</strong></div>
    @endif
</section>
@push('scripts')
<script src="{{ asset('js/hr-recruitment-workspace.js') }}?v={{ filemtime(public_path('js/hr-recruitment-workspace.js')) }}" defer></script>
@endpush
