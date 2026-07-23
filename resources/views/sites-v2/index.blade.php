@extends('layouts.app')

@section('title', 'Điều phối công trình · EGO Solar')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/site-workspace-v2.css') }}?v={{ file_exists(public_path('css/site-workspace-v2.css')) ? filemtime(public_path('css/site-workspace-v2.css')) : time() }}">
@endpush

@section('content')
@php
    $selectedStage = (string) request('stage', '');
    $money = fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.').' đ';
    $workflowSteps = [
        ['key' => 'intake', 'label' => 'Sales bàn giao', 'icon' => 'bi-send-check'],
        ['key' => 'survey', 'label' => 'Tiếp nhận & khảo sát', 'icon' => 'bi-rulers'],
        ['key' => 'approval', 'label' => 'Duyệt phương án', 'icon' => 'bi-patch-check'],
        ['key' => 'material', 'label' => 'Chuẩn bị vật tư', 'icon' => 'bi-box-seam'],
        ['key' => 'installing', 'label' => 'Thi công', 'icon' => 'bi-tools'],
        ['key' => 'handover', 'label' => 'Nghiệm thu', 'icon' => 'bi-check2-circle'],
        ['key' => 'warranty', 'label' => 'Bảo hành', 'icon' => 'bi-shield-check'],
    ];
@endphp

<div class="site-v2-page">
    <section class="site-v2-hero">
        <div class="site-v2-hero__glow site-v2-hero__glow--one"></div>
        <div class="site-v2-hero__glow site-v2-hero__glow--two"></div>

        <div class="site-v2-hero__content">
            <div class="site-v2-heading">
                <div class="site-v2-title-line">
                    <span class="site-v2-title-icon"><i class="bi bi-diagram-3"></i></span>
                    <div>
                        <div class="site-v2-eyebrow">
                            <span class="site-v2-beta">BETA</span>
                            <span>Workspace liên phòng ban</span>
                        </div>
                        <h1>Điều phối công trình</h1>
                    </div>
                </div>
                <p>
                    Một công trình, một hồ sơ trung tâm. Theo dõi rõ công việc đang ở bước nào,
                    ai đang giữ việc và cần phòng ban nào xử lý tiếp.
                </p>
            </div>

            <div class="site-v2-hero__actions">
                <span class="site-v2-scope" title="Phạm vi dữ liệu của tài khoản hiện tại">
                    <i class="bi bi-shield-lock"></i>
                    {{ $scopeLabel }}
                </span>

                @if(
                    $canOpenLegacy
                    && auth()->user()?->hasAnyRole(['admin', 'management', 'technical_manager', 'sales', 'sales_manager'])
                    && \Illuminate\Support\Facades\Route::has('sites.create')
                )
                    <a href="{{ route('sites.create') }}" class="site-v2-btn site-v2-btn--primary">
                        <i class="bi bi-plus-lg"></i>
                        Bàn giao công trình
                    </a>
                @endif

                @if(\Illuminate\Support\Facades\Route::has('sites.index'))
                    <a href="{{ route('sites.index') }}" class="site-v2-btn site-v2-btn--ghost">
                        <i class="bi bi-box-arrow-up-right"></i>
                        Trang cũ
                    </a>
                @endif
            </div>
        </div>

        <div class="site-v2-data-note">
            <i class="bi bi-database-check"></i>
            <span>Dùng trực tiếp dữ liệu hiện tại, không tạo bản sao và không đổi ID công trình.</span>
        </div>
    </section>

    <section class="site-v2-workflow" aria-label="Quy trình công trình">
        @foreach($workflowSteps as $index => $step)
            @php
                $count = (int) ($stageCounts[$step['key']] ?? 0);
                if ($step['key'] === 'material') {
                    $count += (int) ($stageCounts['ready'] ?? 0);
                }
            @endphp
            <a href="{{ request()->fullUrlWithQuery(['stage' => $step['key'], 'page' => null]) }}"
               class="site-v2-workflow__step {{ $selectedStage === $step['key'] ? 'is-active' : '' }}">
                <span class="site-v2-workflow__number">{{ $index + 1 }}</span>
                <span class="site-v2-workflow__icon"><i class="bi {{ $step['icon'] }}"></i></span>
                <span class="site-v2-workflow__text">
                    <strong>{{ $step['label'] }}</strong>
                    <small>{{ $count }} công trình</small>
                </span>
                @if(!$loop->last)
                    <span class="site-v2-workflow__connector"><i class="bi bi-chevron-right"></i></span>
                @endif
            </a>
        @endforeach
    </section>

    <section class="site-v2-kpis">
        <article class="site-v2-kpi" data-tone="blue">
            <span class="site-v2-kpi__icon"><i class="bi bi-kanban"></i></span>
            <div><small>Tổng công trình</small><strong data-count="{{ $kpis['total'] }}">{{ $kpis['total'] }}</strong></div>
            <span class="site-v2-kpi__hint">Trong phạm vi của bạn</span>
        </article>
        <article class="site-v2-kpi" data-tone="amber">
            <span class="site-v2-kpi__icon"><i class="bi bi-lightning-charge"></i></span>
            <div><small>Cần xử lý</small><strong data-count="{{ $kpis['waiting'] }}">{{ $kpis['waiting'] }}</strong></div>
            <span class="site-v2-kpi__hint">Chưa qua bước thi công</span>
        </article>
        <article class="site-v2-kpi" data-tone="purple">
            <span class="site-v2-kpi__icon"><i class="bi bi-box-seam"></i></span>
            <div><small>Chờ vật tư</small><strong data-count="{{ $kpis['material'] }}">{{ $kpis['material'] }}</strong></div>
            <span class="site-v2-kpi__hint">Cần Kho phối hợp</span>
        </article>
        <article class="site-v2-kpi" data-tone="green">
            <span class="site-v2-kpi__icon"><i class="bi bi-tools"></i></span>
            <div><small>Đang thi công</small><strong data-count="{{ $kpis['installing'] }}">{{ $kpis['installing'] }}</strong></div>
            <span class="site-v2-kpi__hint">Đang triển khai thực tế</span>
        </article>
        <article class="site-v2-kpi" data-tone="red">
            <span class="site-v2-kpi__icon"><i class="bi bi-exclamation-triangle"></i></span>
            <div><small>Trễ lịch</small><strong data-count="{{ $kpis['overdue'] }}">{{ $kpis['overdue'] }}</strong></div>
            <span class="site-v2-kpi__hint">Cần ưu tiên kiểm tra</span>
        </article>
    </section>

    @if($canSeeFinance && $financeSummary)
        <section class="site-v2-finance collapse" id="siteV2FinancePanel">
            <div class="site-v2-finance__head">
                <div>
                    <span class="site-v2-finance__eyebrow">Chỉ Admin / Ban giám đốc / Kế toán</span>
                    <h2>Tài chính theo phạm vi đang xem</h2>
                </div>
                <button type="button" class="site-v2-icon-btn" data-bs-toggle="collapse" data-bs-target="#siteV2FinancePanel">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="site-v2-finance__grid">
                <div><small>Giá trị hợp đồng</small><strong>{{ $money($financeSummary['contract']) }}</strong></div>
                <div><small>Đã thu</small><strong>{{ $money($financeSummary['received']) }}</strong></div>
                <div><small>Công nợ còn lại</small><strong>{{ $money($financeSummary['debt']) }}</strong></div>
            </div>
        </section>
    @endif

    <section class="site-v2-toolbar">
        <form method="GET" action="{{ route('sites-v2.index') }}" class="site-v2-filter" id="siteV2Filter">
            <div class="site-v2-search">
                <i class="bi bi-search"></i>
                <input type="search" name="q" value="{{ request('q') }}"
                       placeholder="Tìm mã, tên, địa chỉ, người liên hệ, kỹ thuật..."
                       aria-label="Tìm công trình">
                <kbd>/</kbd>
            </div>

            @if($companies->isNotEmpty())
                <select name="company_id" class="site-v2-select" aria-label="Lọc công ty">
                    <option value="">Công ty đang chọn</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) request('company_id', $activeCompanyId) === (string) $company->id)>
                            {{ trim(($company->code ?? '').' - '.($company->name ?? '')) }}
                        </option>
                    @endforeach
                </select>
            @endif

            @if($selectedStage !== '')
                <input type="hidden" name="stage" value="{{ $selectedStage }}">
            @endif

            <button class="site-v2-btn site-v2-btn--filter" type="submit">
                <i class="bi bi-funnel"></i> Lọc
            </button>

            @if(request()->hasAny(['q', 'company_id', 'stage']))
                <a href="{{ route('sites-v2.index') }}" class="site-v2-icon-btn" title="Xóa bộ lọc">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            @endif
        </form>

        <div class="site-v2-toolbar__right">
            @if($canSeeFinance && $financeSummary)
                <button type="button" class="site-v2-btn site-v2-btn--soft"
                        data-bs-toggle="collapse" data-bs-target="#siteV2FinancePanel">
                    <i class="bi bi-lock"></i> Tài chính
                </button>
            @endif
            <div class="site-v2-view-toggle" role="group" aria-label="Kiểu hiển thị">
                <button type="button" class="is-active" data-site-view="list" title="Danh sách">
                    <i class="bi bi-list-ul"></i>
                </button>
                <button type="button" data-site-view="board" title="Theo luồng">
                    <i class="bi bi-columns-gap"></i>
                </button>
            </div>
        </div>
    </section>

    <nav class="site-v2-stage-tabs" aria-label="Lọc trạng thái">
        <a href="{{ request()->fullUrlWithQuery(['stage' => null, 'page' => null]) }}"
           class="{{ $selectedStage === '' ? 'is-active' : '' }}">
            Tất cả <span>{{ $kpis['total'] }}</span>
        </a>
        @foreach($stageDefinitions as $stageKey => $definition)
            <a href="{{ request()->fullUrlWithQuery(['stage' => $stageKey, 'page' => null]) }}"
               class="{{ $selectedStage === $stageKey ? 'is-active' : '' }}">
                {{ $definition['short'] }}
                <span>{{ (int) ($stageCounts[$stageKey] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="site-v2-list" data-site-panel="list">
        <div class="site-v2-list__head">
            <span>Công trình</span>
            <span>Giai đoạn & tiến độ</span>
            <span>Phụ trách</span>
            <span>Phối hợp</span>
            <span>Việc tiếp theo</span>
            <span></span>
        </div>

        @forelse($sites as $site)
            <article class="site-v2-row js-site-card {{ $site->is_overdue ? 'is-overdue' : '' }}"
                     data-site-id="{{ $site->id }}"
                     data-site-code="{{ $site->site_code }}"
                     data-site-name="{{ $site->name }}"
                     data-site-address="{{ $site->address }}"
                     data-site-contact="{{ trim(($site->contact_name ?? '').' '.($site->contact_phone ?? '')) }}"
                     data-site-stage="{{ $site->workflow_label }}"
                     data-site-owner="{{ $site->owner_display }}"
                     data-site-system="{{ $site->system_summary }}"
                     data-site-date="{{ $site->schedule_date_display }}"
                     data-site-next="{{ $site->next_action }}"
                     data-site-material="{{ $site->material_total }} đơn · {{ $site->material_pending }} đang chờ"
                     data-site-url="{{ $canOpenLegacy ? route('sites.show', $site->id) : '' }}">
                <div class="site-v2-project">
                    <div class="site-v2-project__top">
                        <span class="site-v2-code">{{ $site->site_code }}</span>
                        @if($site->is_overdue)
                            <span class="site-v2-alert"><i class="bi bi-exclamation-circle"></i> Trễ lịch</span>
                        @endif
                    </div>
                    @if($canOpenLegacy)
                        <a class="site-v2-project__name" href="{{ route('sites.show', $site->id) }}">{{ $site->name }}</a>
                    @else
                        <button type="button" class="site-v2-project__name site-v2-project__name--button js-site-detail">{{ $site->name }}</button>
                    @endif
                    <p><i class="bi bi-geo-alt"></i> {{ $site->address ?: 'Chưa cập nhật địa chỉ' }}</p>
                    <small>{{ $site->system_summary }}</small>
                </div>

                <div class="site-v2-progress-cell">
                    <span class="site-v2-stage" data-tone="{{ $site->workflow_tone }}">
                        <i class="bi {{ $site->workflow_icon }}"></i>
                        {{ $site->workflow_label }}
                    </span>
                    <div class="site-v2-progress">
                        <span style="--progress: {{ $site->workflow_progress }}%"></span>
                    </div>
                    <small>{{ $site->workflow_progress }}% quy trình</small>
                </div>

                <div class="site-v2-owner">
                    <span class="site-v2-avatar">{{ mb_strtoupper(mb_substr($site->owner_display, 0, 1)) }}</span>
                    <div>
                        <strong>{{ $site->owner_display }}</strong>
                        <small><i class="bi bi-calendar3"></i> {{ $site->schedule_date_display }}</small>
                    </div>
                </div>

                <div class="site-v2-collab">
                    @if($site->material_total > 0 && $canOpenLegacy)
                        <a href="{{ url('/don-vat-tu?site_id='.$site->id) }}" class="site-v2-collab__item">
                            <i class="bi bi-box-seam"></i>
                            <span><strong>{{ $site->material_total }} đơn vật tư</strong><small>{{ $site->material_pending }} đang chờ</small></span>
                        </a>
                    @elseif($canOpenLegacy)
                        <a href="{{ url('/don-vat-tu/tao?site_id='.$site->id) }}" class="site-v2-collab__item is-empty">
                            <i class="bi bi-plus-circle"></i>
                            <span><strong>Chưa có đơn vật tư</strong><small>Tạo trực tiếp cho công trình</small></span>
                        </a>
                    @else
                        <div class="site-v2-collab__item is-empty">
                            <i class="bi bi-shield-lock"></i>
                            <span><strong>{{ $site->material_total }} đơn vật tư</strong><small>Chỉ xem tổng quan</small></span>
                        </div>
                    @endif
                </div>

                <div class="site-v2-next">
                    <span>Việc tiếp theo</span>
                    <strong>{{ $site->next_action }}</strong>
                </div>

                <div class="site-v2-actions">
                    <button type="button" class="site-v2-icon-btn js-site-detail" title="Xem nhanh">
                        <i class="bi bi-eye"></i>
                    </button>
                    @if($canOpenLegacy)
                        <a href="{{ route('sites.show', $site->id) }}" class="site-v2-icon-btn" title="Mở hồ sơ">
                            <i class="bi bi-arrow-up-right"></i>
                        </a>
                    @endif
                </div>
            </article>
        @empty
            <div class="site-v2-empty">
                <span><i class="bi bi-inbox"></i></span>
                <h3>Chưa có công trình phù hợp</h3>
                <p>Thử xóa bộ lọc hoặc kiểm tra phạm vi dữ liệu của tài khoản.</p>
            </div>
        @endforelse
    </section>

    <section class="site-v2-board" data-site-panel="board" hidden>
        @php
            $boardGroups = [
                'waiting' => ['title' => 'Chờ xử lý', 'icon' => 'bi-inbox', 'tone' => 'slate'],
                'preparing' => ['title' => 'Chuẩn bị triển khai', 'icon' => 'bi-box-seam', 'tone' => 'amber'],
                'working' => ['title' => 'Đang thi công', 'icon' => 'bi-tools', 'tone' => 'green'],
                'completed' => ['title' => 'Bàn giao & bảo hành', 'icon' => 'bi-check2-circle', 'tone' => 'blue'],
            ];
        @endphp
        @foreach($boardGroups as $groupKey => $group)
            <div class="site-v2-board__column" data-tone="{{ $group['tone'] }}">
                <header>
                    <span><i class="bi {{ $group['icon'] }}"></i> {{ $group['title'] }}</span>
                    <b>{{ $sites->getCollection()->where('workflow_group', $groupKey)->count() }}</b>
                </header>
                <div class="site-v2-board__cards">
                    @forelse($sites->getCollection()->where('workflow_group', $groupKey) as $site)
                        <article class="site-v2-board-card js-site-card {{ $site->is_overdue ? 'is-overdue' : '' }}"
                                 data-site-id="{{ $site->id }}"
                                 data-site-code="{{ $site->site_code }}"
                                 data-site-name="{{ $site->name }}"
                                 data-site-address="{{ $site->address }}"
                                 data-site-contact="{{ trim(($site->contact_name ?? '').' '.($site->contact_phone ?? '')) }}"
                                 data-site-stage="{{ $site->workflow_label }}"
                                 data-site-owner="{{ $site->owner_display }}"
                                 data-site-system="{{ $site->system_summary }}"
                                 data-site-date="{{ $site->schedule_date_display }}"
                                 data-site-next="{{ $site->next_action }}"
                                 data-site-material="{{ $site->material_total }} đơn · {{ $site->material_pending }} đang chờ"
                                 data-site-url="{{ $canOpenLegacy ? route('sites.show', $site->id) : '' }}">
                            <div class="site-v2-board-card__meta">
                                <span>{{ $site->site_code }}</span>
                                @if($site->is_overdue)<b><i class="bi bi-exclamation-circle"></i></b>@endif
                            </div>
                            @if($canOpenLegacy)
                                <a href="{{ route('sites.show', $site->id) }}">{{ $site->name }}</a>
                            @else
                                <button type="button" class="site-v2-board-card__title js-site-detail">{{ $site->name }}</button>
                            @endif
                            <p>{{ $site->address ?: 'Chưa cập nhật địa chỉ' }}</p>
                            <span class="site-v2-stage" data-tone="{{ $site->workflow_tone }}">
                                <i class="bi {{ $site->workflow_icon }}"></i> {{ $site->workflow_label }}
                            </span>
                            <div class="site-v2-progress"><span style="--progress: {{ $site->workflow_progress }}%"></span></div>
                            <div class="site-v2-board-card__footer">
                                <span><i class="bi bi-person"></i> {{ $site->owner_display }}</span>
                                <button type="button" class="js-site-detail">Chi tiết <i class="bi bi-arrow-right"></i></button>
                            </div>
                        </article>
                    @empty
                        <div class="site-v2-board__empty">Không có công trình</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </section>

    @if($sites->hasPages())
        <div class="site-v2-pagination">
            {{ $sites->links() }}
        </div>
    @endif
</div>

<div class="offcanvas offcanvas-end site-v2-drawer" tabindex="-1" id="siteV2Drawer" aria-labelledby="siteV2DrawerLabel">
    <div class="offcanvas-header">
        <div>
            <span class="site-v2-beta">XEM NHANH</span>
            <h2 id="siteV2DrawerLabel">Hồ sơ công trình</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
    </div>
    <div class="offcanvas-body">
        <div class="site-v2-drawer__hero">
            <span id="siteV2DrawerCode">CT-00000</span>
            <h3 id="siteV2DrawerName">Tên công trình</h3>
            <p id="siteV2DrawerAddress">Địa chỉ</p>
        </div>
        <div class="site-v2-drawer__grid">
            <div><small>Giai đoạn</small><strong id="siteV2DrawerStage">—</strong></div>
            <div><small>Phụ trách</small><strong id="siteV2DrawerOwner">—</strong></div>
            <div><small>Lịch triển khai</small><strong id="siteV2DrawerDate">—</strong></div>
            <div><small>Phối hợp vật tư</small><strong id="siteV2DrawerMaterial">—</strong></div>
        </div>
        <div class="site-v2-drawer__section">
            <small>Cấu hình hệ</small>
            <p id="siteV2DrawerSystem">—</p>
        </div>
        <div class="site-v2-drawer__section">
            <small>Người liên hệ</small>
            <p id="siteV2DrawerContact">—</p>
        </div>
        <div class="site-v2-drawer__next">
            <span><i class="bi bi-lightning-charge"></i> Việc tiếp theo</span>
            <strong id="siteV2DrawerNext">—</strong>
        </div>
        <a href="#" id="siteV2DrawerOpen" class="site-v2-btn site-v2-btn--primary site-v2-drawer__open">
            Mở toàn bộ hồ sơ <i class="bi bi-arrow-up-right"></i>
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/site-workspace-v2.js') }}?v={{ file_exists(public_path('js/site-workspace-v2.js')) ? filemtime(public_path('js/site-workspace-v2.js')) : time() }}"></script>
@endpush
