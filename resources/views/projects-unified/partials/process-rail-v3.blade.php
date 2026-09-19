@php
    $epv3Selected = (string) ($workflow['selected_code'] ?? $workflow['current_code'] ?? 'survey');
    $epv3StatusLabels = [
        'not_assigned'=>'Chưa phân công','assigned'=>'Đã phân công','in_progress'=>'Đang thực hiện',
        'submitted'=>'Chờ duyệt','revision'=>'Cần bổ sung','approved'=>'Đã duyệt',
    ];
    $epv3Steps = $workflow['steps'] ?? [];
    $epv3GroupStatus = function(array $codes) use ($epv3Steps, $epv3StatusLabels) {
        $states = collect($codes)->map(fn($code) => (string) data_get($epv3Steps, $code.'.status', 'not_assigned'));
        $state = $states->contains('revision') ? 'revision'
            : ($states->contains('submitted') ? 'submitted'
            : ($states->contains('in_progress') ? 'in_progress'
            : ($states->contains('assigned') ? 'assigned'
            : ($states->every(fn($v) => $v === 'approved') ? 'approved' : 'not_assigned'))));
        return ['code'=>$state, 'label'=>$epv3StatusLabels[$state] ?? $state];
    };
    $surveyPaState = $epv3GroupStatus(['survey','proposal']);
    $contractLegalState = $epv3GroupStatus(['contract','legal']);
    $constructionState = $epv3GroupStatus(['construction']);
    $acceptanceState = $epv3GroupStatus(['acceptance']);
@endphp

<aside class="epv3-rail" aria-label="Quy trình Công trình">
    <div class="epv3-rail-head">
        <span>QUY TRÌNH</span>
        <strong>Công trình</strong>
        <small>Chọn bước bên trái để xử lý nội dung tương ứng.</small>
    </div>

    <button type="button" class="epv3-step epv3-step-overview" data-open-tab="overview">
        <span class="epv3-step-icon"><i class="bi bi-grid"></i></span>
        <span><strong>Tổng quan</strong><small>Thông tin &amp; tiến độ</small></span>
        <i class="bi bi-chevron-right"></i>
    </button>

    <div class="epv3-group {{ in_array($epv3Selected,['survey','proposal'],true) ? 'active' : '' }}">
        <a class="epv3-step" href="{{ route('projects-unified.show', ['site'=>$site->id,'step'=>'survey']) }}#workflow">
            <span class="epv3-step-no">1</span>
            <span><strong>Khảo sát &amp; Phương án</strong><small class="state-{{ $surveyPaState['code'] }}">{{ $surveyPaState['label'] }}</small></span>
            <i class="bi bi-chevron-right"></i>
        </a>
        <div class="epv3-substeps">
            <a class="{{ $epv3Selected === 'survey' ? 'active' : '' }}" href="{{ route('projects-unified.show', ['site'=>$site->id,'step'=>'survey']) }}#workflow">Khảo sát</a>
            <a class="{{ $epv3Selected === 'proposal' ? 'active' : '' }}" href="{{ route('projects-unified.show', ['site'=>$site->id,'step'=>'proposal']) }}#workflow">Phương án</a>
        </div>
    </div>

    <div class="epv3-group {{ in_array($epv3Selected,['contract','legal'],true) ? 'active' : '' }}">
        <a class="epv3-step" href="{{ route('projects-unified.show', ['site'=>$site->id,'step'=>'contract']) }}#workflow">
            <span class="epv3-step-no">2</span>
            <span><strong>HĐ &amp; Pháp lý</strong><small class="state-{{ $contractLegalState['code'] }}">{{ $contractLegalState['label'] }}</small></span>
            <i class="bi bi-chevron-right"></i>
        </a>
        <div class="epv3-substeps">
            <a class="{{ $epv3Selected === 'contract' ? 'active' : '' }}" href="{{ route('projects-unified.show', ['site'=>$site->id,'step'=>'contract']) }}#workflow">Hợp đồng</a>
            <a class="{{ $epv3Selected === 'legal' ? 'active' : '' }}" href="{{ route('projects-unified.show', ['site'=>$site->id,'step'=>'legal']) }}#workflow">Pháp lý</a>
        </div>
    </div>

    <button type="button" class="epv3-step" data-open-tab="materials">
        <span class="epv3-step-no">3</span>
        <span><strong>Đề xuất vật tư</strong><small>{{ $materials->count() > 0 ? $materials->count().' phiếu/đơn liên kết' : 'Chưa có đề xuất' }}</small></span>
        <i class="bi bi-chevron-right"></i>
    </button>

    <a class="epv3-step {{ $epv3Selected === 'construction' ? 'active' : '' }}" href="{{ route('projects-unified.show', ['site'=>$site->id,'step'=>'construction']) }}#workflow">
        <span class="epv3-step-no">4</span>
        <span><strong>Thi công</strong><small class="state-{{ $constructionState['code'] }}">{{ $constructionState['label'] }}</small></span>
        <i class="bi bi-chevron-right"></i>
    </a>

    <a class="epv3-step {{ $epv3Selected === 'acceptance' ? 'active' : '' }}" href="{{ route('projects-unified.show', ['site'=>$site->id,'step'=>'acceptance']) }}#workflow">
        <span class="epv3-step-no">5</span>
        <span><strong>Nghiệm thu</strong><small class="state-{{ $acceptanceState['code'] }}">{{ $acceptanceState['label'] }}</small></span>
        <i class="bi bi-chevron-right"></i>
    </a>

    @if(Route::has('projects-unified.maintenance.index'))
        <a class="epv3-maintenance" href="{{ route('projects-unified.maintenance.index', ['site_id'=>$site->id]) }}">
            <span><i class="bi bi-shield-check"></i></span>
            <div><strong>Bảo trì / Bảo hành</strong><small>Module riêng sau Nghiệm thu</small></div>
            <i class="bi bi-box-arrow-up-right"></i>
        </a>
    @endif
</aside>
