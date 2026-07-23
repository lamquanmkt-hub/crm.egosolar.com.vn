@extends('layouts.app')

@section('title', $project->code.' · Công Trình Test new')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/project-test.css') }}?v={{ file_exists(public_path('css/project-test.css')) ? filemtime(public_path('css/project-test.css')) : time() }}">
@endpush

@section('content')
@php
    $groups = ['Khảo sát','Phương án','Chuẩn bị','Vật tư','Điều phối','Thi công','Nghiệm thu','Bảo hành'];
    $groupIcons = ['Khảo sát'=>'bi-calendar-check','Phương án'=>'bi-badge-3d','Chuẩn bị'=>'bi-calendar2-week','Vật tư'=>'bi-box-seam','Điều phối'=>'bi-people','Thi công'=>'bi-tools','Nghiệm thu'=>'bi-clipboard-check','Bảo hành'=>'bi-shield-check'];
    $currentGroup = $statusInfo['group'] ?? 'Khảo sát';
    $preliminary = $project->proposal?->preliminary_materials_json ?: [];
@endphp
<div class="pt-page">
<div class="pt-shell">
    @if(session('success'))<div class="pt-alert pt-alert--success" style="margin-bottom:14px"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>@endif
    @if($errors->any())<div class="pt-alert" style="margin-bottom:14px"><strong>Không thể xử lý:</strong> {{ $errors->first() }}</div>@endif

    <section class="pt-card pt-detail-hero">
        <div class="pt-detail-top">
            <div>
                <div class="pt-detail-code"><span class="pt-new">TEST NEW</span><span>{{ $project->code }}</span></div>
                <h1>{{ $project->name }}</h1>
                <div class="pt-meta">
                    <span><i class="bi bi-geo-alt"></i>{{ $project->address ?: 'Chưa có địa chỉ' }}</span>
                    <span><i class="bi bi-person"></i>{{ $project->contact_name ?: 'Chưa có người liên hệ' }}</span>
                    <span><i class="bi bi-telephone"></i>{{ $project->contact_phone ?: '—' }}</span>
                    <span><i class="bi bi-lightning-charge"></i>{{ $project->estimated_kwp ? number_format((float)$project->estimated_kwp,2).' kWp' : 'Chưa chốt công suất' }}</span>
                </div>
            </div>
            <div class="pt-current">
                <small>Việc đang chờ {{ str_replace('_',' ', $project->current_owner_role) }}</small>
                <strong>{{ $statusInfo['label'] }}</strong>
                <div class="pt-progress"><span style="width:{{ $project->progress }}%"></span></div>
                <div class="pt-help">Tiến độ workflow: {{ $project->progress }}%</div>
            </div>
        </div>
    </section>

    <div class="pt-flow">
        @foreach($groups as $index => $group)
            <div class="pt-flow__step {{ $group === $currentGroup ? 'is-current' : '' }}">
                <i class="bi {{ $groupIcons[$group] }}"></i><div><strong>{{ $group }}</strong><small>Bước {{ $index + 1 }}</small></div>
            </div>
        @endforeach
    </div>

    <nav class="pt-tabs">
        <button class="pt-tab active" data-pt-tab="overview">Tổng quan</button>
        <button class="pt-tab" data-pt-tab="action">Xử lý bước hiện tại</button>
        <button class="pt-tab" data-pt-tab="survey">Khảo sát & 3D</button>
        <button class="pt-tab" data-pt-tab="materials">Vật tư</button>
        <button class="pt-tab" data-pt-tab="installation">Thi công</button>
        <button class="pt-tab" data-pt-tab="acceptance">Nghiệm thu & bảo hành</button>
        @if($can['editBasic'] || $can['editPeople'] || $can['editSurvey'] || $can['editAcceptance'])
            <button class="pt-tab" data-pt-tab="edit"><i class="bi bi-pencil-square"></i> Chỉnh sửa hồ sơ</button>
        @endif
        <button class="pt-tab" data-pt-tab="history">Lịch sử</button>
    </nav>

    <div class="pt-grid">
        <main class="pt-main">
            <section class="pt-panel active" data-pt-panel="overview">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Hồ sơ bàn giao từ Sales</h2><p>Thông tin gốc để các phòng ban phối hợp mà không phải nhập lại.</p></div><a href="{{ route('project-test.index') }}" class="pt-btn pt-btn--soft pt-btn--sm"><i class="bi bi-arrow-left"></i> Danh sách</a></div>
                    <div class="pt-summary">
                        <div><small>Sales phụ trách</small><strong>{{ $project->salesUser?->name ?: '—' }}</strong></div>
                        <div><small>Trưởng phòng Kỹ thuật</small><strong>{{ $project->technicalManager?->name ?: 'Chưa tiếp nhận' }}</strong></div>
                        <div><small>Người khảo sát</small><strong>{{ $project->survey?->surveyor?->name ?: 'Chưa phân công' }}</strong></div>
                        <div><small>Đội trưởng thi công</small><strong>{{ $project->leadTechnician?->name ?: 'Chưa phân công' }}</strong></div>
                        <div><small>Mức ưu tiên</small><strong>{{ mb_strtoupper($project->priority) }}</strong></div>
                        <div><small>Lịch khảo sát</small><strong>{{ optional($project->proposed_survey_at)->format('d/m/Y H:i') ?: '—' }}</strong></div>
                        <div><small>Lịch thi công</small><strong>{{ optional($project->proposed_installation_at)->format('d/m/Y H:i') ?: '—' }}</strong></div>
                        <div class="pt-field--full"><small>Nhu cầu khách hàng</small><strong style="line-height:1.6">{{ $project->customer_need ?: '—' }}</strong></div>
                        <div class="pt-field--full"><small>Ghi chú bàn giao</small><strong style="line-height:1.6">{{ $project->note ?: '—' }}</strong></div>
                    </div>
                </article>

                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Đội thi công</h2><p>Kỹ thuật trưởng phân công sau khi Kho xuất hàng.</p></div></div>
                    @forelse($project->assignments as $assignment)
                        <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-person-check"></i></span><div><strong>{{ $assignment->user?->name }} · {{ $assignment->assignment_role === 'leader' ? 'Đội trưởng thi công' : 'Thành viên kỹ thuật' }}</strong><p>Ngày làm: {{ optional($assignment->work_date)->format('d/m/Y') ?: '—' }} · {{ $assignment->note ?: 'Không ghi chú' }}</p></div></div>
                    @empty
                        <div class="pt-alert pt-alert--info">Chưa phân công đội thi công.</div>
                    @endforelse
                </article>
            </section>

            <section class="pt-panel" data-pt-panel="action">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Xử lý bước hiện tại</h2><p>Hệ thống chỉ mở đúng biểu mẫu của phòng ban đang giữ việc.</p></div><span class="pt-status">{{ $statusInfo['label'] }}</span></div>

                    @if(in_array($project->status, ['survey_pending','survey_reschedule']) && $can['technicalManager'])
                        <form method="POST" action="{{ route('project-test.survey.review', $project) }}" class="pt-form-grid" data-confirm="Xác nhận phản hồi lịch khảo sát?">@csrf
                            <div><label class="pt-label">Phản hồi Kỹ thuật</label><select class="pt-select" name="decision" required><option value="confirm">Đồng ý lịch khảo sát</option><option value="reschedule">Từ chối · Đề nghị lịch khác</option></select></div>
                            <div><label class="pt-label">Thời gian khảo sát</label><input class="pt-input" type="datetime-local" name="scheduled_at" value="{{ optional($project->proposed_survey_at)->format('Y-m-d\TH:i') }}" required></div>
                            <div><label class="pt-label">Trưởng phòng Kỹ thuật</label><select class="pt-select" name="technical_manager_id" required><option value="">-- Chọn đúng role technical_manager --</option>@foreach($technicalManagers as $user)<option value="{{ $user->id }}" @selected($project->technical_manager_id==$user->id || (!$project->technical_manager_id && auth()->id()==$user->id))>{{ $user->name }}</option>@endforeach</select><div class="pt-help">Chỉ tài khoản có role technical_manager xuất hiện.</div></div>
                            <div><label class="pt-label">Người khảo sát</label><select class="pt-select" name="surveyor_id"><option value="">-- Chọn kỹ thuật khảo sát --</option>@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->survey?->surveyed_by==$user->id)>{{ $user->name }}</option>@endforeach</select><div class="pt-help">Chỉ tài khoản role ky_thuat xuất hiện.</div></div>
                            <div class="pt-field--full"><label class="pt-label">Lý do / ghi chú</label><textarea class="pt-textarea" name="reason" placeholder="Bắt buộc ghi lý do nếu đề nghị đổi lịch"></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-send-check"></i> Gửi phản hồi lịch khảo sát</button></div>
                        </form>
                    @elseif($project->status === 'survey_reschedule' && $can['sales'])
                        <form method="POST" action="{{ route('project-test.survey.sales-reschedule', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Sales hẹn lại với khách</label><input class="pt-input" type="datetime-local" name="proposed_survey_at" required></div>
                            <div><label class="pt-label">Ghi chú cho Kỹ thuật</label><input class="pt-input" name="note"></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-calendar-plus"></i> Gửi lại lịch khảo sát</button></div>
                        </form>
                    @elseif(in_array($project->status, ['survey_confirmed','survey_in_progress','proposal_revision']) && $can['technical'])
                        <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.survey.submit', $project) }}" class="pt-form-grid">@csrf
                            <div class="pt-field--full"><label class="pt-label">Hiện trạng khảo sát</label><textarea class="pt-textarea" name="site_condition" required>{{ old('site_condition',$project->survey?->site_condition) }}</textarea></div>
                            <div><label class="pt-label">Công suất đề xuất (kWp)</label><input class="pt-input" type="number" step="0.01" name="proposed_kwp" value="{{ old('proposed_kwp',$project->proposal?->proposed_kwp ?: $project->estimated_kwp) }}" required></div>
                            <div><label class="pt-label">File mô phỏng 3D</label><input class="pt-input" type="file" name="design_3d_file"></div>
                            <div class="pt-field--full"><label class="pt-label">Phương án kỹ thuật</label><textarea class="pt-textarea" name="solution_summary" required>{{ old('solution_summary',$project->proposal?->solution_summary) }}</textarea></div>
                            <div class="pt-field--full"><label class="pt-label">Danh mục thiết bị sơ bộ · mỗi dòng một vật tư</label><textarea class="pt-textarea" name="preliminary_materials" required>{{ old('preliminary_materials',implode("\n",$preliminary)) }}</textarea></div>
                            <div><label class="pt-label">Ghi chú kỹ thuật</label><textarea class="pt-textarea" name="technical_notes">{{ old('technical_notes',$project->survey?->technical_notes) }}</textarea></div>
                            <div><label class="pt-label">File khảo sát bổ sung</label><input class="pt-input" type="file" name="attachment_file"></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-badge-3d"></i> Gửi 3D & phương án cho Sales</button></div>
                        </form>
                    @elseif($project->status === 'sales_review' && $can['sales'])
                        <form method="POST" action="{{ route('project-test.proposal.sales-review', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Kết quả làm việc với khách</label><select class="pt-select" name="decision"><option value="accept">Khách chốt phương án</option><option value="revision">Khách yêu cầu chỉnh</option></select></div>
                            <div><label class="pt-label">Sales đề xuất lịch thi công</label><input class="pt-input" type="datetime-local" name="proposed_installation_at"></div>
                            <div class="pt-field--full"><label class="pt-label">Phản hồi khách hàng</label><textarea class="pt-textarea" name="feedback"></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-hand-thumbs-up"></i> Cập nhật kết quả chốt khách</button></div>
                        </form>
                    @elseif(in_array($project->status, ['installation_pending','installation_reschedule']) && $can['technicalManager'])
                        <form method="POST" action="{{ route('project-test.installation.review', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Phản hồi lịch thi công</label><select class="pt-select" name="decision"><option value="confirm">Kỹ thuật xác nhận lịch</option><option value="reschedule">Đề nghị Sales dời lịch</option></select></div>
                            <div><label class="pt-label">Ngày giờ thi công</label><input class="pt-input" type="datetime-local" name="installation_at" value="{{ optional($project->proposed_installation_at)->format('Y-m-d\TH:i') }}" required></div>
                            <div class="pt-field--full"><label class="pt-label">Lý do / ghi chú</label><textarea class="pt-textarea" name="reason"></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-calendar-check"></i> Gửi phản hồi lịch thi công</button></div>
                        </form>
                    @elseif($project->status === 'installation_reschedule' && $can['sales'])
                        <form method="POST" action="{{ route('project-test.installation.sales-reschedule', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Lịch thi công mới</label><input class="pt-input" type="datetime-local" name="proposed_installation_at" required></div>
                            <div><label class="pt-label">Ghi chú</label><input class="pt-input" name="note"></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand">Gửi lại Kỹ thuật duyệt</button></div>
                        </form>
                    @elseif(in_array($project->status, ['materials_pending','materials_revision']) && $can['technical'])
                        <form method="POST" action="{{ route('project-test.materials.submit', $project) }}">@csrf
                            <div class="pt-form-grid"><div><label class="pt-label">Ngày cần vật tư</label><input class="pt-input" type="date" name="needed_at" required></div><div><label class="pt-label">Ghi chú yêu cầu</label><input class="pt-input" name="request_note"></div></div>
                            <div class="pt-alert pt-alert--info" style="margin-top:12px"><strong>Kỹ thuật chỉ ghi nhu cầu:</strong> tên vật tư, số lượng, đơn vị và thông số cần đáp ứng. Kho sẽ tự ghép với SKU thật, tồn kho và serial.</div>
                            <div style="overflow:auto;margin-top:12px"><table class="pt-material-table"><thead><tr><th>Tên vật tư / nhu cầu kỹ thuật</th><th>SL cần</th><th>ĐVT</th><th>Thông số & ghi chú</th><th></th></tr></thead><tbody id="pt-material-lines"><tr><td><input class="pt-input" name="item_name[]" placeholder="VD: Inverter Hybrid 8 kW" required></td><td><input class="pt-input" type="number" step="0.001" min="0.001" name="quantity[]" value="1" required></td><td><input class="pt-input" name="unit[]" value="cái" required></td><td><input class="pt-input" name="item_note[]" placeholder="VD: 1 pha, hỗ trợ pin 51.2V, cho phép tương đương"></td><td><button type="button" class="pt-btn pt-btn--danger pt-btn--sm" data-remove-line><i class="bi bi-x"></i></button></td></tr></tbody></table></div>
                            <button type="button" class="pt-btn pt-btn--soft pt-btn--sm pt-add-line" data-add-material="#pt-material-lines"><i class="bi bi-plus"></i> Thêm dòng vật tư</button>
                            <button class="pt-btn pt-btn--brand" style="margin-top:10px"><i class="bi bi-send"></i> Gửi Admin duyệt vật tư</button>
                        </form>
                    @elseif($project->status === 'materials_admin_review' && $can['admin'] && $project->materialRequests->first())
                        @php($requestItem = $project->materialRequests->first())
                        <form method="POST" action="{{ route('project-test.materials.review', [$project,$requestItem]) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Quyết định Admin</label><select class="pt-select" name="decision"><option value="approve">Duyệt · Chuyển Kho</option><option value="reject">Trả Kỹ thuật điều chỉnh</option></select></div>
                            <div><label class="pt-label">Phiếu</label><input class="pt-input" value="{{ $requestItem->code }}" disabled></div>
                            <div class="pt-field--full"><label class="pt-label">Ghi chú duyệt</label><textarea class="pt-textarea" name="review_note"></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-shield-check"></i> Xác nhận quyết định</button></div>
                        </form>
                    @elseif(in_array($project->status, ['warehouse_issued','assignment_pending']) && $can['technicalManager'])
                        <form method="POST" action="{{ route('project-test.assign', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Đội trưởng</label><select class="pt-select" name="lead_technician_id" required>@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->lead_technician_id==$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                            <div><label class="pt-label">Ngày thi công</label><input class="pt-input" type="date" name="work_date" value="{{ optional($project->proposed_installation_at)->format('Y-m-d') }}" required></div>
                            <div class="pt-field--full"><label class="pt-label">Thành viên</label><select class="pt-select" name="member_ids[]" multiple size="6">@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->assignments->where('assignment_role','member')->pluck('user_id')->contains($user->id))>{{ $user->name }}</option>@endforeach</select><div class="pt-help">Chỉ tài khoản role ky_thuat. Giữ Ctrl để chọn nhiều người.</div></div>
                            <div class="pt-field--full"><label class="pt-label">Ghi chú điều phối</label><textarea class="pt-textarea" name="note"></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-people"></i> Phân công đội thi công</button></div>
                        </form>
                    @elseif($project->status === 'ready_install' && $can['technical'])
                        <form method="POST" action="{{ route('project-test.installation.start', $project) }}" data-confirm="Xác nhận đội kỹ thuật bắt đầu thi công?">@csrf<button class="pt-btn pt-btn--brand"><i class="bi bi-play-circle"></i> Bắt đầu thi công</button></form>
                    @elseif($project->status === 'installing' && $can['technical'])
                        <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.logs.store', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Ngày cập nhật</label><input class="pt-input" type="date" name="log_date" value="{{ now()->format('Y-m-d') }}" required></div>
                            <div><label class="pt-label">Tiến độ thực tế (%)</label><input class="pt-input" type="number" name="progress" min="0" max="100" value="{{ max(0,$project->progress) }}" required></div>
                            <div><label class="pt-label">Tình trạng</label><select class="pt-select" name="status"><option value="working">Đang làm</option><option value="blocked">Bị vướng</option><option value="waiting_customer">Chờ khách</option><option value="waiting_material">Chờ vật tư</option><option value="done">Hoàn tất · Gửi nghiệm thu</option></select></div>
                            <div><label class="pt-label">Ảnh / tài liệu</label><input class="pt-input" type="file" name="attachment_file"></div>
                            <div class="pt-field--full"><label class="pt-label">Nội dung nhật ký</label><textarea class="pt-textarea" name="content" required></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-journal-check"></i> Lưu nhật ký</button></div>
                        </form>
                    @elseif($project->status === 'acceptance_pending' && $can['acceptance'])
                        <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.accept', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Ngày nghiệm thu</label><input class="pt-input" type="date" name="accepted_at" value="{{ now()->format('Y-m-d') }}" required></div>
                            <div><label class="pt-label">Thời hạn bảo hành (tháng)</label><input class="pt-input" type="number" name="warranty_months" value="60" min="1" max="240" required></div>
                            <div class="pt-field--full"><label class="pt-label">Serial thiết bị thực tế</label><textarea class="pt-textarea" name="device_serials" required placeholder="Mỗi dòng một serial hoặc ghi rõ thiết bị · serial"></textarea></div>
                            <div><label class="pt-label">Link Monitoring</label><input class="pt-input" type="url" name="monitoring_link"></div>
                            <div><label class="pt-label">Tài khoản Monitoring</label><input class="pt-input" name="monitoring_account"></div>
                            <div><label class="pt-label">Biên bản nghiệm thu</label><input class="pt-input" type="file" name="report_file"></div>
                            <div><label class="pt-label">Ghi chú</label><input class="pt-input" name="note"></div>
                            <div class="pt-field--full"><label class="pt-label">Checklist</label><div class="pt-summary"><label><input type="checkbox" name="checklist[]" value="installation_complete"> Lắp đặt hoàn tất</label><label><input type="checkbox" name="checklist[]" value="system_tested"> Đã kiểm tra vận hành</label><label><input type="checkbox" name="checklist[]" value="customer_handover"> Đã bàn giao khách</label><label><input type="checkbox" name="checklist[]" value="monitoring_ready"> Monitoring hoạt động</label></div></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-clipboard-check"></i> Duyệt nghiệm thu & tạo bảo hành</button></div>
                        </form>
                    @elseif($project->status === 'warehouse_preparing')
                        <div class="pt-alert pt-alert--info">Phiếu vật tư đã chuyển sang <strong>Sản phẩm → Xuất kho công trình Test</strong>. Kho xử lý tại trang riêng, không cần vào hồ sơ Công trình.</div>
                        @can('project-test.warehouse')<a href="{{ route('project-test.warehouse.index') }}" class="pt-btn pt-btn--brand" style="margin-top:12px"><i class="bi bi-box-arrow-up-right"></i> Mở trang Xuất kho Test</a>@endcan
                    @elseif($project->status === 'warranty_active')
                        <div class="pt-alert pt-alert--success"><strong>Workflow triển khai đã hoàn tất.</strong> Hồ sơ bảo hành đã được mở tự động và giữ liên kết với nghiệm thu, serial và Kỹ thuật phụ trách.</div>
                    @else
                        <div class="pt-alert pt-alert--info">Tài khoản hiện tại không giữ bước này hoặc workflow đang chờ phòng ban khác xử lý.</div>
                    @endif
                </article>
            </section>

            <section class="pt-panel" data-pt-panel="survey">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Khảo sát & mô phỏng 3D</h2><p>Kết quả kỹ thuật được lưu riêng, không trộn vào form tạo công trình.</p></div></div>
                    <div class="pt-summary">
                        <div><small>Trạng thái lịch</small><strong>{{ $project->survey?->schedule_status ?: 'Chưa có' }}</strong></div>
                        <div><small>Ngày khảo sát</small><strong>{{ optional($project->survey?->scheduled_at)->format('d/m/Y H:i') ?: '—' }}</strong></div>
                        <div class="pt-field--full"><small>Hiện trạng</small><strong>{{ $project->survey?->site_condition ?: 'Chưa khảo sát' }}</strong></div>
                        <div class="pt-field--full"><small>Phương án kỹ thuật</small><strong>{{ $project->proposal?->solution_summary ?: 'Chưa có phương án' }}</strong></div>
                    </div>
                    <div class="pt-actions" style="margin-top:12px">
                        @if($project->survey?->design_3d_file)<a class="pt-btn pt-btn--brand" href="{{ route('project-test.download',[$project,'design-3d']) }}"><i class="bi bi-badge-3d"></i> Tải mô phỏng 3D</a>@endif
                        @if($project->survey?->attachment_file)<a class="pt-btn pt-btn--soft" href="{{ route('project-test.download',[$project,'survey']) }}"><i class="bi bi-paperclip"></i> Tài liệu khảo sát</a>@endif
                    </div>
                    @if($preliminary)<div style="margin-top:15px"><label class="pt-label">Thiết bị sơ bộ</label>@foreach($preliminary as $line)<span class="pt-status" style="margin:3px">{{ $line }}</span>@endforeach</div>@endif
                </article>
            </section>

            <section class="pt-panel" data-pt-panel="materials">
                @forelse($project->materialRequests as $materialRequest)
                    <article class="pt-card pt-section">
                        <div class="pt-section__head"><div><h2>{{ $materialRequest->code }}</h2><p>Ngày cần: {{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }}</p></div><span class="pt-status">{{ $materialRequest->status }}</span></div>
                        <table class="pt-material-table"><thead><tr><th>Nhu cầu Kỹ thuật</th><th>SL cần</th><th>Hàng thật Kho ghép</th><th>Trạng thái</th></tr></thead><tbody>@foreach($materialRequest->items as $item)<tr><td><strong>{{ $item->item_name }}</strong><div class="pt-help">{{ $item->note }}</div></td><td>{{ rtrim(rtrim(number_format((float)$item->quantity,3,'.',''),'0'),'.') }} {{ $item->unit }}</td><td>@forelse($item->allocations as $allocation)<div><strong>{{ $allocation->product?->name ?: 'Chưa xác định' }}</strong><div class="pt-help">{{ $allocation->product?->sku }} · {{ $allocation->warehouse?->name }} · {{ rtrim(rtrim(number_format((float)$allocation->allocated_quantity,3,'.',''),'0'),'.') }} {{ $allocation->product?->unit ?: $item->unit }}</div></div>@empty<span class="pt-muted">Kho chưa ghép SKU thật</span>@endforelse</td><td><span class="pt-status">{{ $materialRequest->warehouse_status ?: ($materialRequest->status === 'issued' ? 'Đã xuất' : 'Chờ Kho ghép hàng') }}</span></td></tr>@endforeach</tbody></table>
                        @if($materialRequest->review_note)<div class="pt-alert" style="margin-top:10px">Admin: {{ $materialRequest->review_note }}</div>@endif
                        @if($can['editMaterials'] && $materialRequest->status !== 'issued')
                            <details class="pt-edit-details" style="margin-top:14px">
                                <summary><i class="bi bi-pencil-square"></i> Chỉnh sửa phiếu vật tư</summary>
                                <form method="POST" action="{{ route('project-test.materials.update', [$project,$materialRequest]) }}" style="margin-top:14px" data-confirm="Sửa phiếu sẽ yêu cầu Admin duyệt lại. Tiếp tục?">
                                    @csrf
                                    <div class="pt-form-grid"><div><label class="pt-label">Ngày cần vật tư</label><input class="pt-input" type="date" name="needed_at" value="{{ optional($materialRequest->needed_at)->format('Y-m-d') }}" required></div><div><label class="pt-label">Ghi chú yêu cầu</label><input class="pt-input" name="request_note" value="{{ $materialRequest->request_note }}"></div></div>
                                    <div class="pt-alert pt-alert--info" style="margin-top:12px">Kỹ thuật chỉ chỉnh nhu cầu. Khi lưu, các ghép SKU của Kho sẽ được làm lại sau khi Admin duyệt.</div>
                                    <div style="overflow:auto;margin-top:12px"><table class="pt-material-table"><thead><tr><th>Tên vật tư / nhu cầu</th><th>SL</th><th>ĐVT</th><th>Thông số & ghi chú</th></tr></thead><tbody>@foreach($materialRequest->items as $item)<tr><td><input class="pt-input" name="item_name[]" value="{{ $item->item_name }}" required></td><td><input class="pt-input" type="number" step="0.001" min="0.001" name="quantity[]" value="{{ $item->quantity }}" required></td><td><input class="pt-input" name="unit[]" value="{{ $item->unit }}" required></td><td><input class="pt-input" name="item_note[]" value="{{ $item->note }}"></td></tr>@endforeach</tbody></table></div>
                                    <button class="pt-btn pt-btn--brand" style="margin-top:12px"><i class="bi bi-save"></i> Lưu phiếu & gửi duyệt lại</button>
                                </form>
                            </details>
                        @endif
                    </article>
                @empty
                    <article class="pt-card pt-section"><div class="pt-empty"><i class="bi bi-box-seam"></i><h3>Chưa có yêu cầu vật tư chính thức</h3><p>Vật tư chỉ được lập sau khi Sales chốt khách và Kỹ thuật xác nhận lịch thi công.</p></div></article>
                @endforelse
            </section>

            <section class="pt-panel" data-pt-panel="installation">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Nhật ký thi công</h2><p>Đội kỹ thuật cập nhật tiến độ, vướng mắc và ảnh thực tế theo ngày.</p></div></div>
                    @forelse($project->dailyLogs as $log)
                        <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-journal-text"></i></span><div style="width:100%"><strong>{{ optional($log->log_date)->format('d/m/Y') }} · {{ $log->author?->name }} · {{ $log->progress }}%</strong><p>{{ $log->content }}</p><time>{{ $log->status }}</time>
                            @if($can['editSurvey'] && (auth()->user()->hasAnyRole(['admin','technical_manager']) || $log->created_by===auth()->id()))
                                <details class="pt-edit-details" style="margin-top:9px"><summary><i class="bi bi-pencil"></i> Sửa nhật ký</summary>
                                    <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.logs.update',[$project,$log]) }}" class="pt-form-grid" style="margin-top:12px">@csrf
                                        <div><label class="pt-label">Ngày</label><input class="pt-input" type="date" name="log_date" value="{{ optional($log->log_date)->format('Y-m-d') }}" required></div>
                                        <div><label class="pt-label">Tiến độ (%)</label><input class="pt-input" type="number" min="0" max="100" name="progress" value="{{ $log->progress }}" required></div>
                                        <div><label class="pt-label">Tình trạng</label><select class="pt-select" name="status">@foreach(['working'=>'Đang làm','blocked'=>'Bị vướng','waiting_customer'=>'Chờ khách','waiting_material'=>'Chờ vật tư','done'=>'Hoàn tất'] as $value=>$label)<option value="{{ $value }}" @selected($log->status===$value)>{{ $label }}</option>@endforeach</select></div>
                                        <div><label class="pt-label">Thay file</label><input class="pt-input" type="file" name="attachment_file"></div>
                                        <div class="pt-field--full"><label class="pt-label">Nội dung</label><textarea class="pt-textarea" name="content" required>{{ $log->content }}</textarea></div>
                                        <div class="pt-field--full"><button class="pt-btn pt-btn--brand pt-btn--sm"><i class="bi bi-save"></i> Lưu nhật ký</button></div>
                                    </form>
                                </details>
                            @endif
                        </div></div>
                    @empty<div class="pt-alert pt-alert--info">Chưa có nhật ký thi công.</div>@endforelse
                </article>
            </section>

            <section class="pt-panel" data-pt-panel="acceptance">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Nghiệm thu & bảo hành</h2><p>Bảo hành tự động tạo sau khi nghiệm thu được duyệt.</p></div></div>
                    @if($project->acceptance)
                        <div class="pt-summary"><div><small>Ngày nghiệm thu</small><strong>{{ optional($project->acceptance->accepted_at)->format('d/m/Y') }}</strong></div><div><small>Monitoring</small><strong>{{ $project->acceptance->monitoring_link ?: '—' }}</strong></div><div class="pt-field--full"><small>Serial thiết bị</small><strong style="white-space:pre-line">{{ $project->acceptance->device_serials }}</strong></div></div>
                        @if($project->acceptance->report_file)<a href="{{ route('project-test.download',[$project,'acceptance']) }}" class="pt-btn pt-btn--soft" style="margin-top:10px"><i class="bi bi-file-earmark-arrow-down"></i> Tải biên bản nghiệm thu</a>@endif
                    @else<div class="pt-alert pt-alert--info">Chưa nghiệm thu.</div>@endif
                    @if($project->warranty)
                        <div class="pt-alert pt-alert--success" style="margin-top:12px"><strong>Bảo hành đang hoạt động:</strong> {{ optional($project->warranty->starts_at)->format('d/m/Y') }} → {{ optional($project->warranty->ends_at)->format('d/m/Y') }} · Bảo trì tiếp theo {{ optional($project->warranty->next_maintenance_at)->format('d/m/Y') }}</div>
                    @endif
                </article>
            </section>

            @if($can['editBasic'] || $can['editPeople'] || $can['editSurvey'] || $can['editAcceptance'])
            <section class="pt-panel" data-pt-panel="edit">
                <div class="pt-edit-stack">
                    @if($can['editBasic'])
                        <article class="pt-card pt-section pt-edit-block">
                            <div class="pt-section__head">
                                <div><h2><i class="bi bi-pencil-square"></i> Chỉnh sửa hồ sơ bàn giao</h2><p>Chỉ Sales, Sales Manager và Admin thấy biểu mẫu này.</p></div>
                                <span class="pt-role-lock">SALES</span>
                            </div>
                            <form method="POST" action="{{ route('project-test.update-basic', $project) }}" class="pt-form-grid" data-confirm="Lưu thay đổi hồ sơ bàn giao?">
                                @csrf
                                <div><label class="pt-label">Công ty</label><select class="pt-select" name="company_id"><option value="">-- Không chọn --</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected($project->company_id==$company->id)>{{ $company->name }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Khách hàng CRM</label><select class="pt-select" name="customer_id"><option value="">-- Không liên kết --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected($project->customer_id==$customer->id)>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Sales phụ trách</label><select class="pt-select" name="sales_user_id" required>@foreach($salesUsers as $user)<option value="{{ $user->id }}" @selected($project->sales_user_id==$user->id)>{{ $user->name }}</option>@endforeach</select><div class="pt-help">Chỉ role sales hoặc sales_manager.</div></div>
                                <div><label class="pt-label">Mức ưu tiên</label><select class="pt-select" name="priority" required>@foreach(['low'=>'Thấp','normal'=>'Bình thường','high'=>'Cao','urgent'=>'Khẩn'] as $value=>$label)<option value="{{ $value }}" @selected($project->priority===$value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="pt-field--full"><label class="pt-label">Tên công trình</label><input class="pt-input" name="name" value="{{ $project->name }}" required></div>
                                <div class="pt-field--full"><label class="pt-label">Địa chỉ</label><input class="pt-input" name="address" value="{{ $project->address }}" required></div>
                                <div><label class="pt-label">Người liên hệ</label><input class="pt-input" name="contact_name" value="{{ $project->contact_name }}"></div>
                                <div><label class="pt-label">Số điện thoại</label><input class="pt-input" name="contact_phone" value="{{ $project->contact_phone }}"></div>
                                <div><label class="pt-label">Loại hệ thống</label><input class="pt-input" name="system_type" value="{{ $project->system_type }}"></div>
                                <div><label class="pt-label">Công suất dự kiến (kWp)</label><input class="pt-input" type="number" step="0.01" name="estimated_kwp" value="{{ $project->estimated_kwp }}"></div>
                                <div><label class="pt-label">Lịch khảo sát Sales đề xuất</label><input class="pt-input" type="datetime-local" name="proposed_survey_at" value="{{ optional($project->proposed_survey_at)->format('Y-m-d\TH:i') }}"></div>
                                <div><label class="pt-label">Lịch thi công Sales đề xuất</label><input class="pt-input" type="datetime-local" name="proposed_installation_at" value="{{ optional($project->proposed_installation_at)->format('Y-m-d\TH:i') }}"></div>
                                <div><label class="pt-label">Hạn hoàn thành</label><input class="pt-input" type="date" name="target_completion_at" value="{{ optional($project->target_completion_at)->format('Y-m-d') }}"></div>
                                <div class="pt-field--full"><label class="pt-label">Nhu cầu khách hàng</label><textarea class="pt-textarea" name="customer_need" required>{{ $project->customer_need }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Ghi chú bàn giao</label><textarea class="pt-textarea" name="note">{{ $project->note }}</textarea></div>
                                <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-save"></i> Lưu thông tin hồ sơ</button></div>
                            </form>
                        </article>
                    @endif

                    @if($can['editPeople'])
                        <article class="pt-card pt-section pt-edit-block">
                            <div class="pt-section__head">
                                <div><h2><i class="bi bi-people-fill"></i> Nhân sự phụ trách & phân công</h2><p>Đây là nơi thêm người sau bước khảo sát và sửa lại bất kỳ lúc nào.</p></div>
                                <span class="pt-role-lock">TECHNICAL MANAGER</span>
                            </div>
                            <div class="pt-alert pt-alert--info" style="margin-bottom:14px">
                                Chỉ <strong>role technical_manager</strong> (và Admin dự phòng) nhìn thấy và thao tác. Dropdown nhân sự kỹ thuật chỉ lấy <strong>role ky_thuat</strong>.
                            </div>
                            <form method="POST" action="{{ route('project-test.people.update', $project) }}" class="pt-form-grid" data-confirm="Cập nhật nhân sự phụ trách?">
                                @csrf
                                <div><label class="pt-label">Trưởng phòng Kỹ thuật</label><select class="pt-select" name="technical_manager_id" required><option value="">-- Chọn technical_manager --</option>@foreach($technicalManagers as $user)<option value="{{ $user->id }}" @selected($project->technical_manager_id==$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Người khảo sát</label><select class="pt-select" name="surveyor_id"><option value="">-- Chưa phân công --</option>@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->survey?->surveyed_by==$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Ngày giờ khảo sát</label><input class="pt-input" type="datetime-local" name="survey_at" value="{{ optional($project->survey?->scheduled_at ?: $project->proposed_survey_at)->format('Y-m-d\TH:i') }}"></div>
                                <div class="pt-field--full pt-toggle-row"><label><input type="checkbox" name="update_team" value="1"> <strong>Cập nhật cả đội thi công</strong></label><span>Chỉ tick khi cần tạo/sửa/xóa đội thi công.</span></div>
                                <div><label class="pt-label">Đội trưởng thi công</label><select class="pt-select" name="lead_technician_id"><option value="">-- Chưa phân công / xóa đội --</option>@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->assignments->where('assignment_role','leader')->pluck('user_id')->contains($user->id))>{{ $user->name }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Ngày thi công của đội</label><input class="pt-input" type="date" name="work_date" value="{{ optional($project->assignments->first()?->work_date ?: $project->proposed_installation_at)->format('Y-m-d') }}"></div>
                                <div class="pt-field--full"><label class="pt-label">Thành viên kỹ thuật</label><select class="pt-select" name="member_ids[]" multiple size="7">@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->assignments->where('assignment_role','member')->pluck('user_id')->contains($user->id))>{{ $user->name }}</option>@endforeach</select><div class="pt-help">Giữ Ctrl để chọn nhiều người. Danh sách này không hiển thị Sales, HR, Marketing hoặc role khác.</div></div>
                                <div class="pt-field--full"><label class="pt-label">Ghi chú phân công</label><textarea class="pt-textarea" name="note">{{ $project->assignments->first()?->note }}</textarea></div>
                                <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-person-check"></i> Lưu nhân sự & phân công</button></div>
                            </form>
                        </article>
                    @endif

                    @if($can['editSurvey'] && ($project->survey || $project->proposal))
                        <article class="pt-card pt-section pt-edit-block">
                            <div class="pt-section__head">
                                <div><h2><i class="bi bi-badge-3d"></i> Chỉnh sửa khảo sát & phương án</h2><p>Thay nội dung hoặc tải file mới mà không làm nhảy workflow.</p></div>
                                <span class="pt-role-lock">KỸ THUẬT</span>
                            </div>
                            <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.survey.update', $project) }}" class="pt-form-grid">
                                @csrf
                                <div><label class="pt-label">Ngày hoàn tất khảo sát</label><input class="pt-input" type="datetime-local" name="completed_at" value="{{ optional($project->survey?->completed_at)->format('Y-m-d\TH:i') }}"></div>
                                <div><label class="pt-label">Công suất đề xuất (kWp)</label><input class="pt-input" type="number" step="0.01" name="proposed_kwp" value="{{ $project->proposal?->proposed_kwp ?: $project->estimated_kwp }}" required></div>
                                <div class="pt-field--full"><label class="pt-label">Hiện trạng khảo sát</label><textarea class="pt-textarea" name="site_condition" required>{{ $project->survey?->site_condition }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Phương án kỹ thuật</label><textarea class="pt-textarea" name="solution_summary" required>{{ $project->proposal?->solution_summary }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Thiết bị sơ bộ · mỗi dòng một mục</label><textarea class="pt-textarea" name="preliminary_materials" required>{{ implode("\n", $preliminary) }}</textarea></div>
                                <div><label class="pt-label">Ghi chú kỹ thuật</label><textarea class="pt-textarea" name="technical_notes">{{ $project->survey?->technical_notes }}</textarea></div>
                                <div><label class="pt-label">Thay file mô phỏng 3D</label><input class="pt-input" type="file" name="design_3d_file"><div class="pt-help">Bỏ trống để giữ file hiện tại.</div></div>
                                <div><label class="pt-label">Thay tài liệu khảo sát</label><input class="pt-input" type="file" name="attachment_file"><div class="pt-help">Bỏ trống để giữ file hiện tại.</div></div>
                                <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-save"></i> Lưu khảo sát & phương án</button></div>
                            </form>
                        </article>
                    @endif

                    @if($can['editAcceptance'] && $project->acceptance)
                        <article class="pt-card pt-section pt-edit-block">
                            <div class="pt-section__head"><div><h2><i class="bi bi-shield-check"></i> Chỉnh sửa nghiệm thu & bảo hành</h2><p>Giữ nguyên workflow, cập nhật lại hồ sơ sau bàn giao.</p></div><span class="pt-role-lock">TRƯỞNG PHÒNG / ADMIN</span></div>
                            <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.acceptance.update', $project) }}" class="pt-form-grid">
                                @csrf
                                <div><label class="pt-label">Ngày nghiệm thu</label><input class="pt-input" type="date" name="accepted_at" value="{{ optional($project->acceptance->accepted_at)->format('Y-m-d') }}" required></div>
                                <div><label class="pt-label">Thời hạn bảo hành (tháng)</label><input class="pt-input" type="number" name="warranty_months" min="1" max="240" value="{{ $project->warranty ? max(1, $project->warranty->starts_at->diffInMonths($project->warranty->ends_at)) : 60 }}" required></div>
                                <div><label class="pt-label">Bảo trì tiếp theo</label><input class="pt-input" type="date" name="next_maintenance_at" value="{{ optional($project->warranty?->next_maintenance_at)->format('Y-m-d') }}"></div>
                                <div><label class="pt-label">Link Monitoring</label><input class="pt-input" type="url" name="monitoring_link" value="{{ $project->acceptance->monitoring_link }}"></div>
                                <div><label class="pt-label">Tài khoản Monitoring</label><input class="pt-input" name="monitoring_account" value="{{ $project->acceptance->monitoring_account }}"></div>
                                <div><label class="pt-label">Thay biên bản</label><input class="pt-input" type="file" name="report_file"><div class="pt-help">Bỏ trống để giữ file cũ.</div></div>
                                <div class="pt-field--full"><label class="pt-label">Serial thiết bị</label><textarea class="pt-textarea" name="device_serials" required>{{ $project->acceptance->device_serials }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Ghi chú</label><textarea class="pt-textarea" name="note">{{ $project->acceptance->note }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Checklist</label><div class="pt-summary">@foreach(['installation_complete'=>'Lắp đặt hoàn tất','system_tested'=>'Đã kiểm tra vận hành','customer_handover'=>'Đã bàn giao khách','monitoring_ready'=>'Monitoring hoạt động'] as $value=>$label)<label><input type="checkbox" name="checklist[]" value="{{ $value }}" @checked(in_array($value,$project->acceptance->checklist_json ?: []))> {{ $label }}</label>@endforeach</div></div>
                                <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-save"></i> Lưu nghiệm thu & bảo hành</button></div>
                            </form>
                        </article>
                    @endif
                </div>
            </section>
            @endif

            <section class="pt-panel" data-pt-panel="history">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Lịch sử workflow</h2><p>Mọi chuyển bước đều lưu người thao tác, thời gian và trạng thái trước/sau.</p></div></div>
                    <div class="pt-timeline">
                        @forelse($project->histories as $history)
                            <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-clock-history"></i></span><div><strong>{{ $history->action }}</strong><p>{{ $history->from_status ?: 'Khởi tạo' }} → {{ $history->to_status ?: 'Không đổi trạng thái' }} · {{ $history->user?->name ?: 'Hệ thống' }}</p><time>{{ $history->created_at->format('d/m/Y H:i:s') }}</time></div></div>
                        @empty<div class="pt-alert pt-alert--info">Chưa có lịch sử.</div>@endforelse
                    </div>
                </article>
            </section>
        </main>

        <aside class="pt-side">
            <article class="pt-card pt-section">
                <div class="pt-section__head"><div><h2>Việc tiếp theo</h2><p>Ai đang giữ việc và cần làm gì.</p></div></div>
                <span class="pt-status">{{ $statusInfo['label'] }}</span>
                <div class="pt-summary" style="margin-top:12px">
                    <div><small>Chủ việc</small><strong>{{ str_replace('_',' ',mb_strtoupper($project->current_owner_role)) }}</strong></div>
                    <div><small>Tiến độ</small><strong>{{ $project->progress }}%</strong></div>
                    <div><small>Khảo sát</small><strong>{{ optional($project->proposed_survey_at)->format('d/m H:i') ?: '—' }}</strong></div>
                    <div><small>Thi công</small><strong>{{ optional($project->proposed_installation_at)->format('d/m H:i') ?: '—' }}</strong></div>
                </div>
            </article>

            <article class="pt-card pt-section">
                <div class="pt-section__head"><div><h2>Liên kết phòng ban</h2><p>Mỗi nhà xử lý tại khu vực của mình.</p></div></div>
                <div class="pt-timeline">
                    <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-graph-up-arrow"></i></span><div><strong>Sales</strong><p>Tạo hồ sơ, hẹn khảo sát, chốt phương án và lịch thi công.</p></div></div>
                    <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-tools"></i></span><div><strong>Kỹ thuật</strong><p>Duyệt lịch, khảo sát, 3D, vật tư, thi công và nghiệm thu.</p></div></div>
                    <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-shield-check"></i></span><div><strong>Admin</strong><p>Duyệt vật tư trước khi chuyển Kho.</p></div></div>
                    <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-box-arrow-up-right"></i></span><div><strong>Kho</strong><p>Xuất tại trang riêng trong Sản phẩm/Kho.</p></div></div>
                </div>
            </article>
        </aside>
    </div>
</div>
</div>

<template data-material-template>
<tr><td><input class="pt-input" name="item_name[]" placeholder="VD: Tấm pin khoảng 550 W" required></td><td><input class="pt-input" type="number" step="0.001" min="0.001" name="quantity[]" value="1" required></td><td><input class="pt-input" name="unit[]" value="cái" required></td><td><input class="pt-input" name="item_note[]" placeholder="Thông số, tiêu chí, cho phép tương đương..."></td><td><button type="button" class="pt-btn pt-btn--danger pt-btn--sm" data-remove-line><i class="bi bi-x"></i></button></td></tr>
</template>
@endsection

@push('scripts')
<script src="{{ asset('js/project-test.js') }}?v={{ file_exists(public_path('js/project-test.js')) ? filemtime(public_path('js/project-test.js')) : time() }}"></script>
@endpush
