@php
    $mkService = app(\App\Services\Projects\ManualMaterialKpiService::class);
    $mkState = $mkService->state($wfRow);
    $mkResult = $mkService->result($wfRow);
    $mkDocuments = $mkService->documents($wfRow);
    $mkLock = app(\App\Services\Projects\AcceptanceKpiService::class)->kpiSourceLock((int) $site->id);
    $mkCanManage = $mkService->canManage(auth()->user()) && $mkLock === null;
    $mkLegacy = !$mkService->requiresConfirmation($wfRow) && isset($wfStepData['kpi_material_deviation_percent']);
    $mkNames = \App\Models\User::query()->whereIn('id', array_filter([$mkState['updated_by'], $mkState['confirmation']['by'] ?? null]))->pluck('name', 'id');
    $mkName = fn ($id) => $mkNames[$id] ?? 'Người dùng đã xóa';
@endphp
<style>#material-kpi .mk-note{margin:8px 0;text-align:left;font-size:13px;line-height:1.5;overflow-wrap:anywhere}#material-kpi .pword-form{margin-top:12px}#material-kpi input[type=file]{max-width:100%}#material-kpi .ewd-doc-section-head{flex-wrap:wrap;gap:8px}</style>
<section class="ewd-doc-section" id="material-kpi">
    <header class="ewd-doc-section-head">
        <div><h3>ĐỐI CHIẾU VẬT TƯ TÍNH KPI</h3>
            <small>Trưởng phòng Kỹ thuật tính tay, nhập % sai lệch và xác nhận. Lưu nháp chưa tính KPI. Dưới 3% đạt ngưỡng; từ 3% không đạt.</small>
        </div>
        <span class="ewd-document-status {{ $mkResult['status'] === 'confirmed' ? 'is-done' : 'is-pending' }}">{{ $mkResult['status'] === 'confirmed' ? 'Đã xác nhận' : 'Chưa xác nhận' }}</span>
    </header>
    <p class="mk-note">Nguồn: <strong>Manager nhập tay</strong>. Kết quả còn cần hồ sơ khảo sát đầy đủ và Admin duyệt nghiệm thu mới vào KPI; không thay bước duyệt nghiệm thu.</p>
    @if($mkLegacy)
        <p class="mk-note">Nguồn cũ đang ghi {{ $wfStepData['kpi_material_deviation_percent'] }}%. Khi lưu bằng form mới, kết quả chuyển sang nháp và phải xác nhận lại.</p>
    @endif
    @if($mkState['updated_at'])
        <p class="mk-note">Bản {{ $mkState['version'] }} · Nhập bởi {{ $mkName($mkState['updated_by']) }} · {{ $mkState['updated_at'] }}.</p>
    @endif
    @if($mkResult['status'] === 'confirmed')
        <p class="mk-note"><strong>Sai lệch: {{ $mkState['percent'] }}%</strong> · {{ $mkResult['confirmed'] }}</p>
    @else
        <p class="mk-note">{{ $mkResult['missing'] }}</p>
    @endif
    <div class="mk-note">Bảng quyết toán vật tư:
        @forelse($mkDocuments as $mkDoc)
            <a href="{{ asset('storage/'.$mkDoc->path) }}" target="_blank" rel="noopener">{{ $mkDoc->original_name ?: $mkDoc->title }} (v{{ $mkDoc->version }})</a>
        @empty
            Chưa có. Tải bảng đối chiếu vào mục “Bảng quyết toán vật tư” ở Hồ sơ nghiệm thu phía trên.
        @endforelse
    </div>
    @if($mkCanManage)
        <form method="POST" enctype="multipart/form-data" action="{{ route('projects-unified.workflow.documents.upload', [$site, 'acceptance']) }}" class="pword-form">
            @csrf<input type="hidden" name="document_code" value="material_reconciliation">
            <label>Đính kèm bảng đối chiếu / quyết toán vật tư<input type="file" name="file" accept=".xlsx,.xls,.csv,.pdf" required></label>
            <button class="pword-btn light" type="submit">Tải bảng đối chiếu</button>
        </form>
        <form method="POST" action="{{ route('projects-unified.workflow.material-kpi.save', [$site, 'acceptance']) }}" class="pword-form">
            @csrf
            <input type="hidden" name="version" value="{{ $mkState['version'] }}">
            <input type="hidden" name="fingerprint" value="{{ $mkService->fingerprint($wfRow) }}">
            <label>% sai lệch vật tư
                <input type="number" name="percent" step="any" min="0" max="1000" inputmode="decimal" value="{{ old('percent', $mkState['percent']) }}" placeholder="Ví dụ: 2.5; bỏ trống là chưa đủ dữ liệu">
            </label>
            <label>Cách tính / căn cứ đối chiếu
                <textarea name="note" rows="3" maxlength="5000" placeholder="Ghi số liệu bóc tách, thực tế sử dụng và cách ra % theo bảng đính kèm">{{ old('note', $mkState['note']) }}</textarea>
            </label>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
                <button class="pword-btn light" type="submit" name="intent" value="draft">Lưu nháp vật tư</button>
                <button class="pword-btn primary" type="submit" name="intent" value="confirm">Xác nhận kết quả vật tư</button>
            </div>
            <small>Sửa số liệu hoặc thêm / thay bảng đối chiếu sẽ làm xác nhận cũ hết hiệu lực. Bỏ trống không tự thành 0% hoặc đạt.</small>
        </form>
    @else
        @if($mkState['note'])<p class="mk-note">Cách tính: {{ $mkState['note'] }}</p>@endif
        <p class="mk-note">{{ $mkLock ? 'Kỳ KPI / lương đã duyệt: khóa sửa vật tư, kể cả Admin.' : 'Chỉ Trưởng phòng Kỹ thuật hoặc Admin được nhập và xác nhận kết quả vật tư.' }}</p>
    @endif
</section>
