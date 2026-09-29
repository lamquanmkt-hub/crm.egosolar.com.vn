@php
    /* Đánh giá KPI kỹ thuật tại bước Nghiệm thu và bàn giao — dữ liệu lưu vào project_workflow_steps.data. */
    $kpiData = is_array($wfStepData ?? null) ? $wfStepData : [];
    $kpiLinks = app(\App\Services\TechnicalKpi\ProjectKpiLinkService::class);
    $kpiSurveyMissing = $kpiLinks->workflowSurveyMissing((int) $site->id);
    $kpiAutoDeviation = $kpiLinks->workflowMaterialDeviationPercent((int) $site->id);
    $kpiComplaint = old('kpi_customer_complaint', array_key_exists('kpi_customer_complaint', $kpiData) ? (int) $kpiData['kpi_customer_complaint'] : null);
    $kpiReviewed = !empty($kpiData['kpi_reviewed_at']);
    $kpiFmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    // Chỉ Trưởng phòng Kỹ thuật / Admin đánh giá; người được giao chỉ xem (fieldset disabled → không gửi dữ liệu).
    $kpiCanReview = (bool) ($kpiCanReview ?? false);
    $kpiReviewer = $kpiReviewed && !empty($kpiData['kpi_reviewed_by']) ? \App\Models\User::query()->whereKey((int) $kpiData['kpi_reviewed_by'])->value('name') : null;

    // Trạng thái từng tiêu chí theo dữ liệu đã lưu (cùng kiểu nhãn với bảng hồ sơ).
    $hseKeys = array_keys(\App\Services\TechnicalKpi\ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS);
    $hseReviewed = collect($hseKeys)->contains(fn ($k) => array_key_exists($k, $kpiData));
    $hseDone = collect($hseKeys)->filter(fn ($k) => !empty($kpiData[$k]))->count();
    $deviation = isset($kpiData['kpi_material_deviation_percent']) && is_numeric($kpiData['kpi_material_deviation_percent'])
        ? (float) $kpiData['kpi_material_deviation_percent'] : $kpiAutoDeviation;
    $kpiStatus = [
        'quality' => $kpiComplaint === null ? ['Chưa đánh giá', 'is-optional'] : ((string) $kpiComplaint === '1' ? ['Có phàn nàn', 'is-pending'] : ['Không phàn nàn', 'is-done']),
        'hse' => ! $hseReviewed ? ['Chưa đánh giá', 'is-optional'] : ($hseDone === count($hseKeys) ? ['Đạt', 'is-done'] : ['Đạt '.$hseDone.'/'.count($hseKeys), 'is-pending']),
        'evn' => ! empty($kpiData['kpi_app_installed']) || ! empty($kpiData['kpi_evn_grid_tested'])
            ? (! empty($kpiData['kpi_app_installed']) && ! empty($kpiData['kpi_evn_grid_tested']) ? ['Hoàn tất', 'is-done'] : ['Chưa đủ', 'is-pending'])
            : ['Chưa đánh giá', 'is-optional'],
        'survey' => $kpiSurveyMissing !== [] ? ['Thiếu hồ sơ', 'is-pending']
            : ($deviation === null ? ['Chưa có số vật tư', 'is-optional'] : ($deviation < 3 ? ['Đạt · lệch '.$kpiFmt($deviation).'%', 'is-done'] : ['Lệch '.$kpiFmt($deviation).'%', 'is-pending'])),
    ];
@endphp
<fieldset class="ewd-doc-section pword-kpi" @disabled(!$kpiCanReview)>
    <input type="hidden" name="kpi_review_submitted" value="1">
    <header class="ewd-doc-section-head">
        <div>
            <h3>ĐÁNH GIÁ KPI KỸ THUẬT</h3>
            <small>
                {{ $kpiReviewed ? 'Đã đánh giá '.\Illuminate\Support\Carbon::parse($kpiData['kpi_reviewed_at'])->format('H:i d/m/Y').($kpiReviewer ? ' · '.$kpiReviewer : '') : 'Chưa đánh giá' }}
                · {{ $kpiCanReview ? 'Trưởng phòng Kỹ thuật / Admin đánh giá trước khi duyệt nghiệm thu' : 'Chỉ Trưởng phòng Kỹ thuật hoặc Admin được đánh giá — bạn chỉ xem' }}
                @if($kpiCanReview && \Illuminate\Support\Facades\Route::has('technical.guides.show'))
                    · <a href="{{ route('technical.guides.show', ['slug' => 'truong-phong-kpi-thang']) }}?return={{ rawurlencode(request()->getRequestUri()) }}" target="_blank" rel="noopener"><i class="bi bi-question-circle"></i> Cách đánh giá</a>
                @endif
            </small>
        </div>
    </header>

    <div class="ewd-document-table">
        <div class="ewd-document-row is-header pword-kpi-row"><span>Tiêu chí</span><span>Đánh giá</span><span>Trạng thái</span></div>

        <div class="ewd-document-row pword-kpi-row">
            <div class="ewd-document-name"><strong>Chất lượng thi công</strong><small>Bị “Trả lại” ở bước Thi công / Nghiệm thu là không đạt lần đầu</small></div>
            <div class="pword-kpi-controls">
                <div class="pword-kpi-inline">
                    <label class="check"><input type="radio" name="kpi_customer_complaint" value="0" @checked((string) $kpiComplaint === '0')>Chủ nhà không phàn nàn</label>
                    <label class="check"><input type="radio" name="kpi_customer_complaint" value="1" @checked((string) $kpiComplaint === '1')>Có phàn nàn</label>
                </div>
                <textarea name="kpi_customer_complaint_note" rows="2" placeholder="Nội dung phàn nàn (bắt buộc nếu có)">{{ old('kpi_customer_complaint_note', $kpiData['kpi_customer_complaint_note'] ?? '') }}</textarea>
            </div>
            <div><span class="ewd-document-status {{ $kpiStatus['quality'][1] }}">{{ $kpiStatus['quality'][0] }}</span></div>
        </div>

        <div class="ewd-document-row pword-kpi-row">
            <div class="ewd-document-name"><strong>An toàn HSE &amp; vệ sinh</strong><small>Đạt khi đủ tất cả các mục</small></div>
            <div class="pword-kpi-controls pword-kpi-checks">
                @foreach(\App\Services\TechnicalKpi\ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS as $key => $label)
                    <label class="check"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $kpiData[$key] ?? false))>{{ $label }}</label>
                @endforeach
            </div>
            <div><span class="ewd-document-status {{ $kpiStatus['hse'][1] }}">{{ $kpiStatus['hse'][0] }}</span></div>
        </div>

        <div class="ewd-document-row pword-kpi-row">
            <div class="ewd-document-name"><strong>EVN &amp; cài App</strong><small>Đóng điện thử chỉ bắt buộc khi công trình cần đấu nối</small></div>
            <div class="pword-kpi-controls pword-kpi-checks">
                <label class="check"><input type="checkbox" name="kpi_evn_grid_tested" value="1" @checked(old('kpi_evn_grid_tested', $kpiData['kpi_evn_grid_tested'] ?? false))>Đã đóng điện thử, hòa lưới EVN</label>
                <label class="check"><input type="checkbox" name="kpi_app_installed" value="1" @checked(old('kpi_app_installed', $kpiData['kpi_app_installed'] ?? false))>Đã cài App giám sát cho chủ nhà</label>
            </div>
            <div><span class="ewd-document-status {{ $kpiStatus['evn'][1] }}">{{ $kpiStatus['evn'][0] }}</span></div>
        </div>

        <div class="ewd-document-row pword-kpi-row">
            <div class="ewd-document-name">
                <strong>Khảo sát &amp; vật tư</strong>
                <small>{{ $kpiSurveyMissing === [] ? 'Hồ sơ khảo sát đầy đủ' : 'Thiếu: '.implode('; ', $kpiSurveyMissing) }}</small>
            </div>
            <div class="pword-kpi-controls">
                <label>% chênh lệch vật tư theo bảng quyết toán
                    <input type="number" step="0.01" min="0" max="1000" name="kpi_material_deviation_percent" value="{{ old('kpi_material_deviation_percent', $kpiData['kpi_material_deviation_percent'] ?? '') }}"
                           placeholder="{{ $kpiAutoDeviation === null ? 'Chưa có đề xuất vật tư ban đầu' : 'Tự tính: '.$kpiFmt($kpiAutoDeviation).'%' }}">
                </label>
                <small class="pword-kpi-help">Để trống = dùng số tự tính (đề xuất bổ sung ÷ ban đầu). Đạt khi dưới 3%.</small>
            </div>
            <div><span class="ewd-document-status {{ $kpiStatus['survey'][1] }}">{{ $kpiStatus['survey'][0] }}</span></div>
        </div>
    </div>
</fieldset>
<style>
    .pword-kpi{border:0;padding:0;min-width:0}
    .pword-kpi .pword-kpi-row{grid-template-columns:minmax(200px,1.3fr) minmax(260px,2fr) minmax(110px,.8fr);align-items:start}
    .pword-kpi .pword-kpi-row.is-header{align-items:center}
    .pword-kpi .pword-kpi-controls{display:grid;gap:8px;min-width:0}
    .pword-kpi .pword-kpi-inline{display:flex;flex-wrap:wrap;gap:6px 18px}
    .pword-kpi .pword-kpi-checks{gap:6px}
    .pword-kpi label.check{font-size:12px;font-weight:600;color:#17314e;gap:8px}
    .pword-kpi input[type=checkbox],.pword-kpi input[type=radio]{min-height:0;width:15px;height:15px;margin:0;flex:0 0 auto;box-shadow:none}
    .pword-kpi textarea,.pword-kpi input[type=number]{font-size:13px}
    .pword-kpi textarea{min-height:56px}
    .pword-kpi .pword-kpi-help{font-size:11px;color:#738499}
    .pword-kpi:disabled .pword-kpi-controls{opacity:.7}
    @media(max-width:760px){.pword-kpi .pword-kpi-row{grid-template-columns:1fr}.pword-kpi .pword-kpi-row.is-header{display:none}}
</style>
