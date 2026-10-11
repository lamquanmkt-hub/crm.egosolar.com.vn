{{-- KPI kỹ thuật KS06 — mốc SLA bảo hành trên phiếu (chỉ hiện với loại phiếu bảo hành). --}}
@php
    $slaReady = \App\Support\SchemaCache::hasColumn('solar_maintenance_schedules', 'first_responded_at');
    $slaIsWarranty = \App\Http\Controllers\Technical\MaintenanceWarrantySlaController::isWarrantyType((string) $schedule->type);
@endphp
@if($slaReady && $slaIsWarranty)
    @php
        $slaRow = \Illuminate\Support\Facades\DB::table('solar_maintenance_schedules')->where('id', $schedule->id)->first();
        $slaFmt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y H:i') : null;
        $slaUser = auth()->user();
        $slaManager = \App\Support\SolarMaintenanceAccess::isManager($slaUser);
        $slaAssignee = $slaUser && \App\Http\Controllers\Technical\MaintenanceWarrantySlaController::isAssignee($schedule, $slaUser);
        $slaInScope = \App\Http\Controllers\Technical\MaintenanceWarrantySlaController::inKpiScope($schedule);
        $slaLocked = ! empty($slaRow->complaint_checked_at);
        $slaComplaintLabels = \App\Http\Controllers\Technical\MaintenanceWarrantySlaController::COMPLAINT_STATUSES;
    @endphp
    <section class="emd9-card" data-kpi-warranty-sla="{{ $schedule->id }}">
        <h3 class="mb-1" style="font-size:1rem">Mốc bảo hành tính KPI</h3>
        @php
            $slaCriterion = app(\App\Services\TechnicalKpi\KpiActionHintService::class)->warranty($schedule)['criterion'];
            $slaMinutes = (int) ($slaCriterion['response_minutes'] ?? 5);
            $slaHours = (int) ($slaCriterion['resolution_hours'] ?? 24);
            $slaPoints = isset($slaCriterion['weight']) ? rtrim(rtrim(number_format($slaCriterion['weight'] * 100, 2, ',', ''), '0'), ',') : null;
        @endphp
        <p class="text-muted small mb-2">Bảo hành{{ $slaPoints !== null ? ': '.$slaPoints.' điểm tối đa trong kỳ' : '' }}. Cách chấm hiện tại: phản hồi ≤ {{ $slaMinutes }} phút, hoàn tất ≤ {{ $slaHours }} giờ từ lúc khách báo và xác nhận không khiếu nại. Giờ tính liên tục, chưa trừ thời gian chờ; quy tắc SLA chi tiết vẫn chờ quản lý chốt.</p>
        @include('technical.maintenance.partials.kpi-warranty-status')
        <ol class="small ps-3 mb-2" data-kpi-warranty-steps>
            <li><strong>Nhận phiếu / phân công:</strong> gắn người phụ trách chính để gán KPI. Tạo phiếu và nhận việc chưa nhận điểm.</li>
            <li><strong>Lưu giờ khách báo:</strong> người tiếp nhận ghi đúng giờ khách thông báo; đây là mốc bắt đầu {{ $slaMinutes }} phút / {{ $slaHours }} giờ. Ghi mốc này chưa nhận điểm.</li>
            <li><strong>Đã phản hồi khách:</strong> kỹ thuật bấm ngay khi phản hồi, hệ thống ghi giờ hiện tại. Quản lý có thể ghi giờ thực tế kèm lý do. Đủ mốc phản hồi mới là một điều kiện, chưa đủ điểm.</li>
            <li><strong>Thực hiện và Hoàn tất đợt:</strong> nhập kết quả, tải minh chứng cần thiết, rồi bấm Hoàn tất đợt để ghi mốc hoàn tất. Lưu ghi chú, đánh dấu hạng mục hay Gửi duyệt chưa thay mốc này. Hoàn tất vẫn cần kiểm tra khiếu nại.</li>
            <li><strong>Kiểm tra khiếu nại:</strong> sau hoàn tất, trưởng phòng không thuộc nhóm thực hiện xác nhận (Admin có ngoại lệ theo quyền hiện có). Chỉ khi đủ mốc và đạt cả ba điều kiện, phiếu mới đủ điều kiện nhận phần điểm. Có khiếu nại hoặc vi phạm thời hạn: không nhận phần điểm; chưa kiểm tra: thiếu dữ liệu.</li>
        </ol>
        <dl class="emp1-summary-list emp1-summary-meta">
            <div><dt>Khách báo</dt><dd>{{ $slaFmt($slaRow->customer_reported_at) ?? 'Chưa ghi' }}</dd></div>
            <div><dt>Phản hồi lần đầu</dt><dd>{{ $slaFmt($slaRow->first_responded_at) ?? 'Chưa ghi' }}</dd></div>
            <div><dt>Hoàn tất thực hiện</dt><dd>{{ $slaFmt($slaRow->execution_finished_at) ?? 'Chưa hoàn tất' }}</dd></div>
            <div><dt>Khiếu nại</dt><dd>{{ $slaRow->complaint_status ? ($slaComplaintLabels[$slaRow->complaint_status] ?? $slaRow->complaint_status).' · '.$slaFmt($slaRow->complaint_checked_at) : 'Chưa kiểm tra' }}</dd></div>
        </dl>
        @if(! $slaLocked && $slaInScope)
            <div class="d-flex flex-wrap gap-2 mt-2">
                @if($slaManager || $slaAssignee || (int) $slaRow->created_by === (int) ($slaUser->id ?? 0))
                    @if(! $slaRow->customer_reported_at || $slaManager)
                        <form method="post" action="{{ route('projects-unified.maintenance.warranty-sla.store', $schedule->id) }}" class="d-flex flex-wrap gap-1 align-items-center">
                            @csrf
                            <input type="hidden" name="action" value="reported">
                            <input type="datetime-local" name="customer_reported_at" class="form-control form-control-sm" required
                                   value="{{ $slaRow->customer_reported_at ? \Illuminate\Support\Carbon::parse($slaRow->customer_reported_at)->format('Y-m-d\TH:i') : '' }}">
                            <button class="btn btn-sm btn-outline-secondary">Lưu giờ khách báo</button>
                            <small class="text-muted">KPI: ghi mốc bắt đầu, chưa nhận điểm.</small>
                        </form>
                    @endif
                @endif
                @if(($slaManager || $slaAssignee) && $slaRow->customer_reported_at && ! $slaRow->first_responded_at)
                    <form method="post" action="{{ route('projects-unified.maintenance.warranty-sla.store', $schedule->id) }}">
                        @csrf
                        <input type="hidden" name="action" value="responded">
                        <button class="btn btn-sm btn-primary">Đã phản hồi khách (ghi giờ hiện tại)</button>
                        <div class="small text-muted mt-1">KPI: tính từ giờ khách báo; bấm khi phản hồi thật, không chờ tới cuối ngày. Chưa đủ điều kiện nhận điểm nếu chưa hoàn tất / kiểm tra khiếu nại.</div>
                    </form>
                    @if($slaManager)
                        <form method="post" action="{{ route('projects-unified.maintenance.warranty-sla.store', $schedule->id) }}" class="d-flex flex-wrap gap-1 align-items-center">
                            @csrf
                            <input type="hidden" name="action" value="responded">
                            <input type="datetime-local" name="first_responded_at" class="form-control form-control-sm" required aria-label="Giờ phản hồi thực tế">
                            <input type="text" name="response_recording_reason" class="form-control form-control-sm" required placeholder="Lý do ghi giờ phản hồi thực tế" maxlength="1000">
                            <button class="btn btn-sm btn-outline-primary">Lưu giờ phản hồi thực tế</button>
                        </form>
                    @endif
                @endif
                @if($slaManager && (! $slaAssignee || \App\Support\SolarMaintenanceAccess::isAdmin($slaUser)) && $slaRow->status === 'completed' && $slaRow->execution_finished_at && $slaRow->customer_reported_at && $slaRow->first_responded_at)
                    <form method="post" action="{{ route('projects-unified.maintenance.warranty-sla.store', $schedule->id) }}" class="d-flex flex-wrap gap-1 align-items-center">
                        @csrf
                        <input type="hidden" name="action" value="verify">
                        <select name="complaint_status" class="form-select form-select-sm" required>
                            <option value="">Kết quả kiểm tra khiếu nại…</option>
                            @foreach($slaComplaintLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="complaint_note" class="form-control form-control-sm" placeholder="Nội dung (bắt buộc nếu có khiếu nại)">
                        <button class="btn btn-sm btn-outline-success">Xác nhận</button>
                        <small class="text-muted">KPI: xác nhận kết quả kiểm tra, không tự biến phiếu trễ hạn thành đạt.</small>
                    </form>
                @endif
            </div>
        @endif
    </section>
@endif
