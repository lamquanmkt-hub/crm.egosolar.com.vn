@php
    $epv4Selected = (string) ($workflow['selected_code'] ?? $workflow['current_code'] ?? 'survey');
    $epv4Steps = $workflow['steps'] ?? [];
    $epv4StatusLabels = [
        'not_assigned' => 'Chưa phân công',
        'assigned' => 'Đã phân công',
        'accepted' => 'Đã nhận việc',
        'in_progress' => 'Đang thực hiện',
        'submitted' => 'Chờ duyệt',
        'revision' => 'Cần bổ sung',
        'approved' => 'Đã duyệt',
    ];
    $epv4GroupState = function (array $codes) use ($epv4Steps, $epv4StatusLabels): array {
        $states = collect($codes)->map(fn ($code) => (string) data_get($epv4Steps, $code.'.status', 'not_assigned'));
        $state = $states->contains('revision') ? 'revision'
            : ($states->contains('submitted') ? 'submitted'
            : ($states->contains('in_progress') ? 'in_progress'
            : ($states->contains('accepted') ? 'accepted'
            : ($states->contains('assigned') ? 'assigned'
            : ($states->every(fn ($value) => $value === 'approved') ? 'approved' : 'not_assigned')))));

        return ['code' => $state, 'label' => $epv4StatusLabels[$state] ?? $state];
    };

    $surveyPaState = $epv4GroupState(['survey', 'proposal']);
    $contractLegalState = $epv4GroupState(['contract', 'legal']);
    $constructionState = $epv4GroupState(['construction']);
    $acceptanceState = $epv4GroupState(['acceptance']);
    $acceptanceApproved = ($acceptanceState['code'] ?? '') === 'approved';
    $hasMaintenanceData = isset($maintenance) && $maintenance->isNotEmpty();
    $materialCount = isset($materialProposals) ? $materialProposals->count() : (isset($materials) ? $materials->count() : 0);
@endphp

<aside class="epv4-rail" aria-label="Quy trình Công trình">
    <div class="epv4-rail-head">
        <span>QUY TRÌNH</span>
        <strong>Công trình</strong>
        <small>Bấm bước nào, bên phải chỉ hiện nội dung bước đó.</small>
    </div>

    <button type="button" class="epv4-step is-overview" data-open-tab="overview" data-epv4-pane-link="overview">
        <span class="epv4-step-icon"><i class="bi bi-grid-1x2"></i></span>
        <span class="epv4-step-copy"><strong>Tổng quan</strong><small>Thông tin công trình</small></span>
        <i class="bi bi-chevron-right"></i>
    </button>

    <a class="epv4-step {{ in_array($epv4Selected, ['survey','proposal'], true) ? 'is-active' : '' }}"
       href="{{ route('projects-unified.show', ['site' => $site->id, 'step' => in_array($epv4Selected, ['survey','proposal'], true) ? $epv4Selected : 'survey']) }}#workflow">
        <span class="epv4-step-no">1</span>
        <span class="epv4-step-copy"><strong>Khảo sát &amp; Phương án</strong><small class="state-{{ $surveyPaState['code'] }}">{{ $surveyPaState['label'] }}</small></span>
        <i class="bi bi-chevron-right"></i>
    </a>

    <a class="epv4-step {{ in_array($epv4Selected, ['contract','legal'], true) ? 'is-active' : '' }}"
       href="{{ route('projects-unified.show', ['site' => $site->id, 'step' => in_array($epv4Selected, ['contract','legal'], true) ? $epv4Selected : 'contract']) }}#workflow">
        <span class="epv4-step-no">2</span>
        <span class="epv4-step-copy"><strong>HĐ &amp; Pháp lý</strong><small class="state-{{ $contractLegalState['code'] }}">{{ $contractLegalState['label'] }}</small></span>
        <i class="bi bi-chevron-right"></i>
    </a>

    <button type="button" class="epv4-step" data-open-tab="materials" data-epv4-pane-link="materials">
        <span class="epv4-step-no">3</span>
        <span class="epv4-step-copy"><strong>Đề xuất vật tư</strong><small>{{ $materialCount > 0 ? $materialCount.' đề xuất' : 'Chưa có đề xuất' }}</small></span>
        <i class="bi bi-chevron-right"></i>
    </button>

    <a class="epv4-step {{ $epv4Selected === 'construction' ? 'is-active' : '' }}"
       href="{{ route('projects-unified.show', ['site' => $site->id, 'step' => 'construction']) }}#workflow">
        <span class="epv4-step-no">4</span>
        <span class="epv4-step-copy"><strong>Thi công</strong><small class="state-{{ $constructionState['code'] }}">{{ $constructionState['label'] }}</small></span>
        <i class="bi bi-chevron-right"></i>
    </a>

    <a class="epv4-step {{ $epv4Selected === 'acceptance' ? 'is-active' : '' }}"
       href="{{ route('projects-unified.show', ['site' => $site->id, 'step' => 'acceptance']) }}#workflow">
        <span class="epv4-step-no">5</span>
        <span class="epv4-step-copy"><strong>Nghiệm thu</strong><small class="state-{{ $acceptanceState['code'] }}">{{ $acceptanceState['label'] }}</small></span>
        <i class="bi bi-chevron-right"></i>
    </a>

    <div class="epv4-rail-divider"></div>

    @if(Route::has('projects-unified.maintenance.index'))
        @if($acceptanceApproved || $hasMaintenanceData)
            <a class="epv4-maintenance" href="{{ route('projects-unified.maintenance.index', ['site_id' => $site->id]) }}">
                <span class="epv4-maintenance-icon"><i class="bi bi-shield-check"></i></span>
                <span><strong>Bảo trì / Bảo hành</strong><small>{{ $hasMaintenanceData ? 'Đã có hồ sơ sau nghiệm thu' : 'Mở module sau nghiệm thu' }}</small></span>
                <i class="bi bi-box-arrow-up-right"></i>
            </a>
        @else
            <div class="epv4-maintenance is-locked" title="Hoàn tất Nghiệm thu để kích hoạt Bảo trì / Bảo hành">
                <span class="epv4-maintenance-icon"><i class="bi bi-lock"></i></span>
                <span><strong>Bảo trì / Bảo hành</strong><small>Kích hoạt sau Nghiệm thu</small></span>
            </div>
        @endif
    @endif

    <div class="epv4-rail-tools">
        <button type="button" data-open-tab="documents" data-epv4-pane-link="documents"><i class="bi bi-folder2-open"></i>Hồ sơ công trình</button>
        <button type="button" data-open-tab="technical" data-epv4-pane-link="technical"><i class="bi bi-list-check"></i>Công việc &amp; báo cáo</button>
    </div>
</aside>
