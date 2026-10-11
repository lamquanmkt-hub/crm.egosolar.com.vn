@extends('layouts.app')

@section('title', 'Sự cố '.$incident->code)

@section('content')
@use('App\Services\Hr\OfficeIncidents')
@php
    $vnd = static fn ($n) => number_format((float) $n, 0, ',', '.');
    $dt = static fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('H:i d/m/Y') : '—';
    $late = $incident->due_at && ! in_array($incident->status, ['waiting_acceptance', 'closed', 'cancelled'], true) && now()->gt($incident->due_at);
    $actions = [
        'reported' => 'Báo sự cố', 'classified' => 'Phân loại', 'cost_approved' => 'Giám đốc duyệt chi', 'cost_rejected' => 'Giám đốc từ chối chi',
        'resolved' => 'Báo xử lý xong', 'accepted' => 'Nghiệm thu & đóng', 'reopened' => 'Chưa đạt — xử lý lại', 'cancelled' => 'Huỷ',
    ];
    $threshold = $vnd($settings['incident_cost_approval_threshold']);
@endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-tools"></i></div>
                <div>
                    <span>SỰ CỐ VĂN PHÒNG · {{ $incident->code }}</span>
                    <h1>{{ $incident->location }}</h1>
                    <p>{{ OfficeIncidents::STATUSES[$incident->status] ?? $incident->status }}
                        @if($incident->level) · {{ OfficeIncidents::LEVELS[$incident->level] }} @endif
                        @if($incident->due_at) · Hạn xử lý {{ $dt($incident->due_at) }} @endif
                        @if($late) · <strong>QUÁ HẠN</strong> @endif
                    </p>
                </div>
            </div>
            <a class="at-btn at-btn--light" href="{{ route('hr.incidents.index') }}"><i class="bi bi-arrow-left"></i>Danh sách</a>
        </header>

        @include('hr.incidents._style')

        <section class="at-panel ic-body">
            <div class="ic-grid">
                <div><div class="ic-muted">NGƯỜI BÁO</div><strong>{{ $names[$incident->reporter_id] ?? '—' }}</strong><div class="ic-muted">{{ $dt($incident->created_at) }}</div></div>
                <div><div class="ic-muted">XỬ LÝ</div><strong>{{ $incident->handler_type === 'vendor' ? 'Thuê ngoài'.($incident->vendor_name ? ': '.$incident->vendor_name : '') : ($incident->handler_type === 'internal' ? 'HCNS / IT tự xử lý' : '—') }}</strong></div>
                <div><div class="ic-muted">CHI PHÍ</div><strong>{{ $incident->actual_cost !== null ? $vnd($incident->actual_cost).'đ' : ((float) $incident->estimated_cost > 0 ? 'Dự kiến '.$vnd($incident->estimated_cost).'đ' : '—') }}</strong>
                    @if($incident->cost_approval)<div class="ic-muted">Duyệt chi: {{ ['pending' => 'Chờ Giám đốc', 'approved' => 'Đã duyệt', 'rejected' => 'Bị từ chối'][$incident->cost_approval] ?? $incident->cost_approval }}{{ $incident->cost_approval_note ? ' — '.$incident->cost_approval_note : '' }}</div>@endif
                </div>
            </div>
            <p class="ic-hint" style="white-space:pre-line">{{ $incident->description }}</p>
            @if($incident->result_note)
                <p class="ic-hint" style="white-space:pre-line"><strong>Kết quả xử lý ({{ $dt($incident->resolved_at) }}):</strong> {{ $incident->result_note }}</p>
            @endif
            @if($incident->rating)
                <p class="ic-hint"><strong>Đánh giá:</strong> {{ str_repeat('★', (int) $incident->rating) }}{{ str_repeat('☆', 5 - (int) $incident->rating) }} {{ $incident->feedback }}
                    @if($incident->on_time !== null) · <span class="{{ $incident->on_time ? 'ic-ok' : 'ic-late' }}">{{ $incident->on_time ? 'Đúng hạn' : 'Trễ hạn' }}</span>@endif
                </p>
            @endif

            @if(session('error'))
                <p class="ic-hint ic-late" data-ic-error>{{ session('error') }}</p>
            @endif

            @foreach(['report' => 'Ảnh lúc báo', 'result' => 'Ảnh sau khi sửa'] as $stage => $stageLabel)
                @php $stageFiles = $files->where('stage', $stage); @endphp
                @if($stageFiles->isNotEmpty())
                    <div class="ic-section"><div class="ic-muted">{{ mb_strtoupper($stageLabel) }}</div>
                        <div class="ic-files">
                            @foreach($stageFiles as $f)
                                <a href="{{ route('hr.incidents.file', [$incident->id, $f->id]) }}" target="_blank">
                                    @if(str_starts_with((string) $f->mime_type, 'image/'))<img src="{{ route('hr.incidents.file', [$incident->id, $f->id]) }}" alt="">@endif
                                    <span>{{ $f->original_name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </section>

        {{-- KPI HC06 · nhóm sự cố: chỉ số đo, chưa tính điểm khi cách gộp/SLA chưa chốt --}}
        @if(! empty($kpiReady) && ($canHandle || $canApprove))
            @php
                $kpiState = [
                    'earned' => ['Đúng hạn — đã nghiệm thu', 'ic-ok'], 'lost' => ['Không đúng hạn', 'ic-late'],
                    'pending' => ['Chờ người báo nghiệm thu', ''], 'waiting' => ['Còn hạn — chưa tính', ''], 'excluded' => ['Loại khỏi KPI', ''],
                ];
                $kpiAssets = (array) ($kpiRules['rules']['assets'] ?? []);
                $kpiOptions = (array) config('hcns_kpi.rules.assets');
            @endphp
            <section class="at-panel ic-body ic-section" id="kpi-hc06" data-ic-kpi="{{ $kpiRow['kpi_state'] ?? 'none' }}">
                <div class="at-card-head"><div><h2><i class="bi bi-speedometer2"></i>Số đo KPI HC06 · Xử lý sự cố</h2>
                    <p>Nhóm “sự cố” là một trong ba nhóm nguồn HC06 (cấp phát VPP, kiểm kê, sự cố). Không lấy riêng số sự cố làm điểm HC06.</p></div></div>
                @if($kpiLocked)
                    <p class="ic-hint">Kỳ KPI tháng {{ substr($kpiLocked, 5, 2) }}/{{ substr($kpiLocked, 0, 4) }} đã chốt lương — không phân loại lại/hủy; vẫn xử lý và nghiệm thu bình thường (kỳ đã chốt giữ ảnh chụp lúc duyệt lương).</p>
                @endif
                @if(! $kpiRow)
                    <p class="ic-hint" data-ic-kpi-missing>Chưa vào số đo: {{ ! $incident->due_at ? 'chưa phân loại (chưa có hạn xử lý)' : 'chưa có người xử lý chịu KPI — phân loại lại để chọn' }}.</p>
                @else
                    <div class="ic-grid">
                        <div><div class="ic-muted">NGƯỜI XỬ LÝ (CHỊU KPI)</div><strong>{{ $kpiRow['owner_name'] }}</strong></div>
                        <div><div class="ic-muted">HẠN DÙNG ĐỂ ĐO</div><strong>{{ $dt($kpiRow['deadline']) }}</strong>
                            @if((int) $incident->approval_wait_minutes > 0 || $incident->waiting_since)<div class="ic-muted">Chờ duyệt chi {{ (int) $incident->approval_wait_minutes }} phút{{ $incident->waiting_since ? ' + đang chờ' : '' }}</div>@endif</div>
                        <div><div class="ic-muted">MỐC ĐO</div><strong>{{ $dt($kpiRow['actual_at']) }}</strong>
                            <div class="ic-muted">Báo xong lần đầu {{ $dt($incident->first_resolved_at) }} · gần nhất {{ $dt($incident->resolved_at) }}</div></div>
                        <div><div class="ic-muted">TRẠNG THÁI SỐ ĐO</div><strong class="{{ $kpiState[$kpiRow['kpi_state']][1] ?? '' }}">{{ $kpiState[$kpiRow['kpi_state']][0] ?? $kpiRow['kpi_state'] }}</strong></div>
                    </div>
                    <p class="ic-hint" data-ic-kpi-reason>{{ $kpiRow['reason'] }}</p>
                @endif
                <p class="ic-muted">Quy tắc kỳ này ({{ ($kpiRules['status'] ?? '') === 'applied' ? 'đã áp dụng' : 'nháp — chưa chốt' }}):
                    {{ $kpiOptions['incident_measure']['options'][$kpiAssets['incident_measure'] ?? 'final_resolution'] ?? '' }} ·
                    {{ $kpiOptions['incident_wait']['options'][$kpiAssets['incident_wait'] ?? 'include'] ?? '' }} ·
                    {{ $kpiOptions['aggregation']['options'][$kpiAssets['aggregation'] ?? 'unset'] ?? '' }}.
                    Hạn theo mức độ là cài đặt hiện có của trang sự cố; lịch làm việc chưa áp dụng (giờ liên tục).</p>
            </section>
        @endif

        {{-- Bước 2: HCNS phân loại --}}
        @if($canHandle && in_array($incident->status, ['new', 'in_progress'], true))
            <section class="at-panel ic-body ic-section">
                <div class="at-card-head"><div><h2><i class="bi bi-diagram-3"></i>{{ $incident->status === 'new' ? 'Phân loại & duyệt chi' : 'Phân loại lại' }}</h2>
                    <p>Phân loại trong {{ (int) $settings['incident_classify_minutes'] }} phút. Hạn xử lý: Mức 1 {{ $settings['incident_sla_hours_1'] + 0 }} giờ · Mức 2 {{ $settings['incident_sla_hours_2'] + 0 }} giờ · Mức 3 {{ $settings['incident_sla_hours_3'] + 0 }} giờ (tính từ lúc báo). Chi phí trên {{ $threshold }}đ cần Giám đốc duyệt trước khi sửa.</p></div></div>
                <form method="POST" action="{{ route('hr.incidents.classify', $incident->id) }}">
                    @csrf
                    <div class="ic-grid">
                        <div class="ic-field"><label>Mức độ *</label>
                            <select class="ic-input" name="level" required>
                                @foreach(OfficeIncidents::LEVELS as $lv => $label)
                                    <option value="{{ $lv }}" @selected((int) $incident->level === $lv) title="{{ OfficeIncidents::LEVEL_EXAMPLES[$lv] }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ic-field"><label>Cách xử lý *</label>
                            <select class="ic-input" name="handler_type" required>
                                <option value="internal" @selected($incident->handler_type === 'internal')>HCNS / IT tự xử lý</option>
                                <option value="vendor" @selected($incident->handler_type === 'vendor')>Thuê ngoài (nhà cung cấp)</option>
                            </select>
                        </div>
                        <div class="ic-field"><label>Nhà cung cấp / thợ</label><input class="ic-input" name="vendor_name" maxlength="255" value="{{ $incident->vendor_name }}"></div>
                        <div class="ic-field"><label>Chi phí dự kiến (đồng)</label><input class="ic-input" type="number" min="0" step="1000" name="estimated_cost" value="{{ (float) $incident->estimated_cost ?: '' }}"></div>
                        @if($handlerOptions->isNotEmpty())
                            <div class="ic-field"><label>Người xử lý (chịu KPI HC06)</label>
                                <select class="ic-input" name="handler_id" data-ic-handler>
                                    @foreach($handlerOptions as $u)
                                        <option value="{{ $u->id }}" @selected((int) ($incident->handler_id ?: auth()->id()) === (int) $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="ic-field" style="grid-column:span 2"><label>Ghi chú</label><input class="ic-input" name="note" maxlength="1000"></div>
                    </div>
                    <p class="ic-muted" style="margin-top:6px">
                        @foreach(OfficeIncidents::LEVEL_EXAMPLES as $lv => $ex) Mức {{ $lv }}: {{ $ex }}.@if(! $loop->last)<br>@endif @endforeach
                    </p>
                    <button class="at-btn at-btn--primary ic-section" type="submit"><i class="bi bi-check2"></i>Lưu phân loại</button>
                </form>
            </section>
        @endif

        {{-- Giám đốc duyệt chi --}}
        @if($canApprove && $incident->status === 'waiting_approval')
            <section class="at-panel ic-body ic-section">
                <div class="at-card-head"><div><h2><i class="bi bi-cash-coin"></i>Duyệt chi phí sửa chữa</h2><p>Dự kiến {{ $vnd($incident->estimated_cost) }}đ — vượt ngưỡng {{ $threshold }}đ.</p></div></div>
                <form method="POST" action="{{ route('hr.incidents.approve-cost', $incident->id) }}" class="ic-filter">
                    @csrf
                    <div class="ic-field" style="flex:1"><label>Ghi chú (bắt buộc khi từ chối)</label><input class="ic-input" name="note" maxlength="1000"></div>
                    <button class="at-btn at-btn--primary" name="decision" value="approve" type="submit"><i class="bi bi-check2"></i>Duyệt chi</button>
                    <button class="at-btn at-btn--light" name="decision" value="reject" type="submit"><i class="bi bi-x"></i>Từ chối</button>
                </form>
            </section>
        @endif

        {{-- Bước 3: HCNS báo xử lý xong --}}
        @if($canHandle && $incident->status === 'in_progress')
            <section class="at-panel ic-body ic-section">
                <div class="at-card-head"><div><h2><i class="bi bi-wrench-adjustable"></i>Báo đã xử lý xong</h2><p>Giám sát thợ trong suốt quá trình sửa, ký biên bản; chụp ảnh kết quả.</p></div></div>
                <form method="POST" action="{{ route('hr.incidents.resolve', $incident->id) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="ic-grid">
                        <div class="ic-field"><label>Chi phí thực tế (đồng)</label><input class="ic-input" type="number" min="0" step="1000" name="actual_cost" value="{{ old('actual_cost') }}"></div>
                        <div class="ic-field" style="grid-column:span 2"><label>Kết quả xử lý *</label><textarea class="ic-input" name="result_note" maxlength="3000" required>{{ old('result_note') }}</textarea></div>
                        <div class="ic-field" style="grid-column:span 3"><label>Ảnh sau khi sửa</label><input class="ic-input" type="file" name="files[]" multiple accept="image/*,video/mp4,video/quicktime,application/pdf"></div>
                    </div>
                    <button class="at-btn at-btn--primary ic-section" type="submit"><i class="bi bi-check2-circle"></i>Đã xử lý xong — mời nghiệm thu</button>
                </form>
            </section>
        @endif

        {{-- Bước 4: người báo nghiệm thu --}}
        @if($incident->status === 'waiting_acceptance' && ($isReporter || $canApprove))
            <section class="at-panel ic-body ic-section">
                <div class="at-card-head"><div><h2><i class="bi bi-patch-check"></i>Nghiệm thu</h2><p>Kiểm tra thực tế. Đạt thì chấm sao để đóng sự cố; chưa đạt thì ghi rõ để HCNS xử lý lại.</p></div></div>
                <form method="POST" action="{{ route('hr.incidents.accept', $incident->id) }}">
                    @csrf
                    <div class="ic-stars">
                        @for($s = 1; $s <= 5; $s++)
                            <label><input type="radio" name="rating" value="{{ $s }}" @checked($s === 5)> {{ str_repeat('★', $s) }}</label>
                        @endfor
                    </div>
                    <div class="ic-field ic-section"><label>Nhận xét</label><input class="ic-input" name="feedback" maxlength="1000"></div>
                    <div class="ic-section" style="display:flex;gap:8px">
                        <button class="at-btn at-btn--primary" name="decision" value="accept" type="submit"><i class="bi bi-check2"></i>Đạt — đóng sự cố</button>
                        <button class="at-btn at-btn--light" name="decision" value="reject" type="submit"><i class="bi bi-arrow-repeat"></i>Chưa đạt — xử lý lại</button>
                    </div>
                </form>
            </section>
        @endif

        @if(! in_array($incident->status, ['closed', 'cancelled'], true) && (($isReporter && $incident->status === 'new') || $canHandle))
            <section class="at-panel ic-body ic-section">
                <form method="POST" action="{{ route('hr.incidents.cancel', $incident->id) }}" class="ic-filter" onsubmit="return confirm('Huỷ sự cố {{ $incident->code }}?')">
                    @csrf
                    <div class="ic-field" style="flex:1"><label>Lý do huỷ</label><input class="ic-input" name="note" maxlength="1000" placeholder="VD: báo trùng, đã tự hết"></div>
                    <button class="at-btn at-btn--light" type="submit"><i class="bi bi-x-circle"></i>Huỷ sự cố</button>
                </form>
            </section>
        @endif

        <section class="at-panel ic-section">
            <div class="at-card-head" style="padding:12px 16px 0"><div><h2><i class="bi bi-clock-history"></i>Lịch sử</h2></div></div>
            <div class="at-table-wrap">
                <table class="at-table">
                    <thead><tr><th>Thời gian</th><th>Người</th><th>Thao tác</th><th>Ghi chú</th></tr></thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr><td>{{ $dt($log->created_at) }}</td><td>{{ $log->user_name ?? '—' }}</td><td>{{ $actions[$log->action] ?? $log->action }}</td><td>{{ $log->note }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
