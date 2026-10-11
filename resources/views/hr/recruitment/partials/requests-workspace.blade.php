<details class="rec-card rec-create-panel" id="rec-create-request" @if($errors->any() && !old('_editing_request_id')) open @endif>
    <summary class="rec-create-summary">
        <span class="rec-create-label"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tạo yêu cầu tuyển dụng</span>
        <span class="rec-create-hint">Chỉ nhập khi có nhu cầu tuyển mới</span>
        <i class="bi bi-chevron-down rec-expand-icon" aria-hidden="true"></i>
    </summary>
    <div class="rec-card-body">
        <form method="POST" enctype="multipart/form-data" action="{{ route('hr.recruitment.requests.store') }}">
            @csrf
            @include('hr.recruitment.partials.request-fields', ['requestRow' => null])
            @include('hr.recruitment.partials.attachments', ['attachmentEntity'=>'requests','attachmentRecord'=>null,'attachmentRestore'=>$errors->any() && !old('_editing_request_id')])
            <div class="rec-form-foot"><button class="rec-btn" type="submit">Tạo yêu cầu</button></div>
        </form>
    </div>
</details>

<section class="rec-card rec-request-list" data-rec-requests>
    <div class="rec-card-head">
        <div>
            <h2 class="rec-card-title">Danh sách yêu cầu <span class="rec-list-count" data-rec-count>{{ $requests->count() }}</span></h2>
            <p class="rec-card-note">Bấm vào một dòng để xem đầy đủ nội dung và chỉnh sửa.</p>
        </div>
    </div>
    @if($requests->count())
        <div class="rec-request-toolbar">
            <div class="rec-field rec-request-search">
                <label class="rec-label" for="rec-request-search">Tìm yêu cầu</label>
                <input class="rec-input" id="rec-request-search" type="search" placeholder="Vị trí, bộ phận hoặc lý do tuyển…" data-rec-search>
            </div>
            <div class="rec-field">
                <label class="rec-label" for="rec-request-status">Phê duyệt</label>
                <select class="rec-select" id="rec-request-status" data-rec-status>
                    <option value="">Tất cả trạng thái</option>
                    @foreach($approvalStatuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="rec-request-columns" aria-hidden="true"><span>Vị trí / Bộ phận</span><span>Số lượng</span><span>Ngày cần</span><span>Lương dự kiến</span><span>Phê duyệt</span><span></span></div>
        @foreach($requests as $row)
            <details class="rec-request" id="rec-request-{{ $row->id }}" data-rec-request data-search="{{ $row->position.' '.$row->department.' '.$row->reason }}" data-status="{{ $row->approval_status }}" @if($errors->any() && (string)old('_editing_request_id') === (string)$row->id) open @endif>
                <summary class="rec-request-row">
                    <span class="rec-request-person"><strong>{{ $row->position }}</strong><small>{{ $row->department }} · {{ $row->reason ?: 'Chưa ghi lý do' }}</small></span>
                    <span class="rec-request-cell"><small>Số lượng</small>{{ $row->quantity }} người</span>
                    <span class="rec-request-cell"><small>Ngày cần</small>{{ $row->needed_date ? \Illuminate\Support\Carbon::parse($row->needed_date)->format('d/m/Y') : 'Chưa đặt' }}</span>
                    <span class="rec-request-cell"><small>Lương dự kiến</small>{{ $row->expected_salary ?: 'Chưa nhập' }}</span>
                    <span><span class="rec-pill {{ ['pending'=>'amber','approved'=>'green','rejected'=>'red','closed'=>'gray'][$row->approval_status] ?? 'gray' }}">{{ $approvalStatuses[$row->approval_status] ?? $row->approval_status }}</span></span>
                    <span class="rec-request-open">Xem / sửa <i class="bi bi-chevron-down rec-expand-icon" aria-hidden="true"></i></span>
                </summary>
                <div class="rec-request-editor">
                    @if(\App\Services\Hr\Kpi\RecruitmentKpi::ready())
                        <p class="rec-kpi-link" data-rec-kpi-link="{{ $row->id }}">
                            <a class="rec-btn-outline" href="{{ route('hr.recruitment.requests.kpi', $row->id) }}">KPI tuyển dụng (HC01–HC03): lượt tuyển, chi phí →</a>
                            <span class="rec-small">{{ ! empty($row->kpi_tracked) ? 'Đang theo dõi KPI · tiếp nhận '.(! empty($row->request_received_at) ? \Illuminate\Support\Carbon::parse($row->request_received_at)->format('H:i d/m/Y') : 'chưa ghi') : 'Chưa bật theo dõi KPI' }}
                                @if(! empty($row->approved_at)) · duyệt {{ \Illuminate\Support\Carbon::parse($row->approved_at)->format('H:i d/m/Y') }} @endif</span>
                        </p>
                    @endif
                    <form method="POST" enctype="multipart/form-data" action="{{ route('hr.recruitment.requests.update', $row->id) }}">
                        @csrf @method('PUT')
                        @include('hr.recruitment.partials.request-fields', ['requestRow' => $row])
                        @include('hr.recruitment.partials.attachments', ['attachmentEntity'=>'requests','attachmentRecord'=>$row->id,'attachmentRestore'=>$errors->any() && (string)old('_editing_request_id') === (string)$row->id])
                        <div class="rec-form-foot"><button class="rec-btn" type="submit">Lưu thay đổi</button></div>
                    </form>
                    <div class="rec-request-danger-zone">
                        <form method="POST" enctype="multipart/form-data" action="{{ route('hr.recruitment.requests.destroy', $row->id) }}" onsubmit="return confirm('Xoá yêu cầu này?')">
                            @csrf @method('DELETE')<button class="rec-btn-danger" type="submit">Xoá yêu cầu</button>
                        </form>
                    </div>
                </div>
            </details>
        @endforeach
        <p class="rec-filter-empty" data-rec-empty hidden>Không có yêu cầu khớp bộ lọc. Hãy đổi từ khóa hoặc trạng thái.</p>
    @else
        <div class="rec-request-empty"><i class="bi bi-clipboard-plus" aria-hidden="true"></i><strong>Chưa có yêu cầu tuyển dụng</strong><p>Mở “Tạo yêu cầu tuyển dụng” ở phía trên để nhập nhu cầu đầu tiên.</p></div>
    @endif
</section>

@push('scripts')
<script src="{{ asset('js/hr-recruitment-workspace.js') }}?v={{ filemtime(public_path('js/hr-recruitment-workspace.js')) }}" defer></script>
@endpush
