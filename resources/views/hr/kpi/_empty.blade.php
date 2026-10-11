@php
    $departmentLabel = config('hr_payroll_departments.'.$template->code.'.label', $template->name);
    $settingsUrl = route('hr.kpi.index', ['template' => $template->code, 'tab' => 'settings', 'month' => $month]);
@endphp
<section class="at-panel pk-empty-panel" aria-label="Bắt đầu chấm KPI">
    <header class="pk-content-heading">
        <div><h2>Chấm KPI · {{ $departmentLabel }}</h2><p>Tháng {{ $monthLabel }} · Chưa có nhân viên được gán bộ KPI</p></div>
        <span class="pk-neutral-badge">0 nhân viên</span>
    </header>
    <div class="pk-empty-layout">
        <div class="pk-empty-main">
            <span class="pk-empty-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
            <h3>Gán bộ KPI để bắt đầu chấm điểm</h3>
            <p>Bộ KPI <strong>{{ $departmentLabel }}</strong> chưa được gán trong hồ sơ lương có hiệu lực tháng {{ $monthLabel }}. Gán bộ KPI cho nhân viên, sau đó quay lại đây để xem và chấm điểm.</p>
            <a class="at-btn at-btn--primary pk-empty-action" href="{{ route('hr.payroll.settings', ['tab' => 'profiles', 'month' => $month]) }}"><i class="bi bi-person-plus"></i>Gán bộ KPI cho nhân viên</a>
            <div class="pk-empty-tip">Trong Hồ sơ nhân viên: bấm <strong>Nhập / Sửa</strong> → chọn <strong>Bộ KPI {{ $departmentLabel }}</strong> → lưu với tháng hiệu lực phù hợp.</div>
        </div>
        <aside class="pk-criteria-preview" aria-label="Tiêu chí của bộ KPI">
            <div class="pk-preview-title"><h3>Bộ tiêu chí {{ $departmentLabel }}</h3><span>{{ $criteria->count() }} tiêu chí</span></div>
            @if($criteria->isEmpty())
                <p>Bộ KPI chưa có tiêu chí để chấm. Cần bổ sung tiêu chí trước khi tính điểm.</p>
            @else
                <ul>
                    @foreach($criteria->take(4) as $criterion)
                        <li><span>{{ $criterion->name }}</span><small>{{ $criterion->weight !== null ? 'Trọng số '.$num($criterion->weight) : 'Chưa có trọng số' }}</small></li>
                    @endforeach
                </ul>
                @if($criteria->count() > 4)<p class="pk-preview-more">Còn {{ $criteria->count() - 4 }} tiêu chí trong bộ KPI.</p>@endif
            @endif
            <a class="pk-text-link" href="{{ $settingsUrl }}">Xem và cài đặt tiêu chí <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </aside>
    </div>
    <ol class="pk-next-steps" aria-label="Các bước để có điểm KPI">
        <li><span>1</span><div><strong>Kiểm tra bộ tiêu chí</strong><small>Chỉ tiêu và trọng số cần đầy đủ</small></div></li>
        <li class="is-current"><span>2</span><div><strong>Gán bộ KPI cho nhân viên</strong><small>Thực hiện trong hồ sơ lương</small></div></li>
        <li><span>3</span><div><strong>Chấm và đối soát điểm</strong><small>Xem dữ liệu từng người trong kỳ</small></div></li>
    </ol>
</section>
