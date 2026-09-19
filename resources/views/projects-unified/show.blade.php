@extends('layouts.app')

@section('title', ($project['name'] ?? 'Công trình').' · EGO Solar')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/projects-detail-word-v1.css') }}?v={{ file_exists(public_path('css/projects-detail-word-v1.css')) ? filemtime(public_path('css/projects-detail-word-v1.css')) : time() }}">
@if(in_array(request('step'), ['survey', 'contract', 'construction', 'acceptance'], true))
<link rel="stylesheet" href="{{ asset('css/ego-project-workflow-documents.css') }}?v={{ file_exists(public_path('css/ego-project-workflow-documents.css')) ? filemtime(public_path('css/ego-project-workflow-documents.css')) : time() }}">
@endif
@if(request('step') === 'materials')
<link rel="stylesheet" href="{{ asset('css/ego-project-material-proposals.css') }}?v={{ file_exists(public_path('css/ego-project-material-proposals.css')) ? filemtime(public_path('css/ego-project-material-proposals.css')) : time() }}">
@endif
@if(request('step') === 'finance' && $canSeeFinance)
<link rel="stylesheet" href="{{ asset('css/ego-project-admin-finance.css') }}?v={{ file_exists(public_path('css/ego-project-admin-finance.css')) ? filemtime(public_path('css/ego-project-admin-finance.css')) : time() }}">
@endif
<link rel="stylesheet" href="{{ asset('css/project-unification-20260906.css') }}?v=1">
@endpush

@section('content')
@php
    $money = fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.').' đ';
    $uiStep = (string) request('step', 'overview');
    $allowedSteps = ['overview','survey','contract','materials','construction','acceptance'];
    if ($canSeeFinance) {
        $allowedSteps[] = 'finance';
    }
    $uiStep = in_array($uiStep, $allowedSteps, true) ? $uiStep : 'overview';
    $deploymentProgress = (int) ($workflow['progress'] ?? $progressEngine['calculated'] ?? $project['progress'] ?? 0);
    $customerName = data_get($site, 'customer.name')
        ?? data_get($site, 'client.name')
        ?? ($site->customer_name ?? $site->client_name ?? 'Chưa cập nhật');
    $projectStatus = $project['phase_info']['label'] ?? 'Đang thực hiện';
    $rail = [
        'overview' => ['Tổng quan', null],
        'survey' => ['Khảo sát & PA', 'Khảo sát & Phương án'],
        'contract' => ['HĐ & Pháp lý', 'Hợp đồng & Pháp lý'],
        'materials' => ['Đề xuất vật tư', 'Đề xuất vật tư'],
        'construction' => ['Thi công', 'Thi công'],
        'acceptance' => ['Nghiệm thu', 'Nghiệm thu'],
    ];
    if ($canSeeFinance) {
        $rail['finance'] = ['Tài chính công trình', 'Tài chính công trình'];
    }
@endphp

<div class="pword-page">
    @if(session('success'))
        <div class="pword-flash success"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="pword-flash danger"><i class="bi bi-exclamation-octagon-fill"></i>{{ $errors->first() }}</div>
    @endif

    <header class="pword-hero">
        <div>
            <div class="pword-hero-kicker">{{ $project['code'] }} <i class="bi bi-chevron-right"></i> {{ $rail[$uiStep][1] ?? 'Tổng quan' }}</div>
            <h1>{{ $project['name'] }}</h1>
            <div class="pword-hero-meta">
                @if($project['address'])<span><i class="bi bi-geo-alt"></i>{{ $project['address'] }}</span>@endif
                <span><i class="bi bi-person"></i>{{ $project['lead_engineer'] }}</span>
                <span><i class="bi bi-lightning-charge"></i>{{ rtrim(rtrim(number_format((float) ($site->system_kwp ?? 0), 2, ',', '.'), '0'), ',') }} kWp</span>
                @if($project['target_date'])<span><i class="bi bi-calendar-event"></i>Hạn {{ $project['target_date']->format('d/m/Y') }}</span>@endif
            </div>
        </div>
        <div class="pword-hero-actions">
            <a href="{{ route('projects-unified.index') }}" class="pword-btn ghost"><i class="bi bi-arrow-left"></i>Danh sách</a>
            <a href="{{ route('projects-unified.show', ['site'=>$site->id]).'#project-files' }}" class="pword-btn light"><i class="bi bi-folder2-open"></i>Hồ sơ</a>
            @if(Route::has('ky-thuat.kpis.project'))
                <a href="{{ route('ky-thuat.kpis.project', ['site'=>$site->id, 'month'=>now()->format('Y-m')]) }}" class="pword-btn light"><i class="bi bi-bar-chart-line"></i>KPI kỹ thuật</a>
            @endif
            @if(Route::has('tasks.create'))
                <a href="{{ route('tasks.create', ['site_id'=>$site->id, 'title'=>'['.$project['code'].'] '.$project['name']]) }}" class="pword-btn primary"><i class="bi bi-check2-square"></i>Giao việc</a>
            @endif
        </div>
        <div class="pword-progress" style="--p:{{ $deploymentProgress }}"><strong>{{ $deploymentProgress }}%</strong><span>TIẾN ĐỘ CÔNG TRÌNH</span></div>
    </header>

    @if(!empty($salesRevenue))
        <section class="pu-sales-revenue-summary" aria-label="Tài chính công trình">
            <div><small>Giá trị hợp đồng</small><strong>{{ $money($salesRevenue['contract']) }}</strong></div>
            <div><small>Đã thu</small><strong>{{ $money($salesRevenue['received']) }}</strong></div>
            <div><small>Còn phải thu</small><strong>{{ $money($salesRevenue['debt']) }}</strong></div>
            <p>Số liệu từ tài chính Công trình · Chỉ xem</p>
        </section>
    @endif

    <div class="pword-layout">
        <aside class="pword-rail">
            <small>QUY TRÌNH</small>
            <h2>Công trình</h2>
            <nav>
                @foreach($rail as $code => $item)
                    @php
                        $href = $code === 'overview'
                            ? route('projects-unified.show', $site)
                            : route('projects-unified.show', ['site'=>$site->id, 'step'=>$code]);
                        $row = in_array($code, ['materials', 'finance'], true) ? null : ($workflow['steps'][$code] ?? null);
                        $done = $row && (($row['status'] ?? '') === 'approved');
                        $railIcon = '○';
                        $railTone = 'muted';
                        $railStatus = 'Chưa bắt đầu';

                        if ($code === 'overview') {
                            $railIcon = '•';
                            $railTone = 'working';
                            $railStatus = 'Tiến độ '.$deploymentProgress.'%';
                        } elseif ($code === 'finance') {
                            $railIcon = '₫';
                            $railTone = 'working';
                            $railStatus = 'Chỉ Admin · Thu chi & giá vốn';
                        } elseif ($code === 'materials') {
                            $materialCount = $materialProposals->count();
                            if ($materialCount > 0) {
                                $latestMaterialStatus = (string) ($materialProposals->first()->status ?? 'SUBMITTED');
                                $railIcon = $latestMaterialStatus === 'EXPORTED' ? '✓' : '•';
                                $railTone = $latestMaterialStatus === 'EXPORTED' ? 'complete' : 'working';
                                $railStatus = $materialCount.' đề xuất · '.(['SUBMITTED'=>'chờ Admin','NEEDS_REVISION'=>'cần sửa','ADMIN_APPROVED'=>'chờ Kho','PARTIALLY_ALLOCATED'=>'Kho đang soạn','WAREHOUSE_ALLOCATED'=>'Kho đã soạn','READY_FOR_EXPORT'=>'chờ xuất kho','EXPORTED'=>'đã xuất kho'][$latestMaterialStatus] ?? 'đang xử lý');
                            } else {
                                $railStatus = 'Chưa có đề xuất';
                            }
                        } elseif($row) {
                            $stepStatus = (string) ($row['status'] ?? 'not_assigned');
                            $missingCount = count($row['document_state']['file_missing'] ?? $row['document_state']['missing'] ?? []);
                            $overdueDays = (int) ($row['overdue_days'] ?? 0);
                            $stepRow = $row['row'] ?? null;

                            if ($overdueDays > 0 && $stepStatus !== 'approved') {
                                $railIcon = '!';
                                $railTone = 'danger';
                                $railStatus = 'Quá hạn '.$overdueDays.' ngày';
                            } elseif ($stepStatus === 'approved') {
                                $approvedAt = $stepRow->approved_at ?? $stepRow->updated_at ?? null;
                                $railIcon = '✓';
                                $railTone = 'complete';
                                $railStatus = 'Đã duyệt'.($missingCount > 0
                                    ? ' · còn thiếu '.$missingCount.' hồ sơ'
                                    : ($approvedAt ? ' · '.\Illuminate\Support\Carbon::parse($approvedAt)->format('d/m/Y') : ''));
                            } elseif ($stepStatus === 'revision') {
                                $railIcon = '!';
                                $railTone = 'danger';
                                $railStatus = 'Cần bổ sung'.($missingCount > 0 ? ' · thiếu '.$missingCount.' hồ sơ' : '');
                            } elseif ($stepStatus === 'submitted') {
                                $railIcon = '•';
                                $railTone = 'pending';
                                $railStatus = 'Đang chờ duyệt';
                            } elseif (in_array($stepStatus, ['assigned','in_progress'], true)) {
                                $railIcon = '•';
                                $railTone = $missingCount > 0 ? 'warning' : 'working';
                                $railStatus = 'Đang làm'.($missingCount > 0 ? ' · còn thiếu '.$missingCount.' hồ sơ' : ' · đủ hồ sơ');
                            } else {
                                $railStatus = 'Chưa phân công';
                            }
                        }
                    @endphp
                    <a href="{{ $href }}" class="{{ $uiStep === $code ? 'active' : '' }} {{ $done ? 'done' : '' }} rail-{{ $railTone }}">
                        <span class="pword-rail-icon">{{ $railIcon }}</span>
                        <div class="pword-rail-copy"><strong>{{ $item[0] }}</strong><small>{{ $railStatus }}</small></div>
                    </a>
                @endforeach
            </nav>
        </aside>

        <main class="pword-content">
            @if($uiStep === 'overview')
                <section class="pword-panel">
                    <div class="pword-panel-head">
                        <div><small>CÔNG TRÌNH</small><h2>CÔNG TRÌNH - TỔNG QUAN</h2></div>
                        <span class="pword-status">{{ $projectStatus }}</span>
                    </div>
                    <h3>THÔNG TIN CÔNG TRÌNH</h3>
                    <dl class="pword-summary">
                        <div><dt>Tên công trình</dt><dd>{{ $project['name'] }}</dd></div>
                        <div><dt>Mã công trình</dt><dd>{{ $project['code'] }}</dd></div>
                        <div><dt>Khách hàng</dt><dd>{{ $customerName }}</dd></div>
                        <div><dt>Công suất</dt><dd>{{ rtrim(rtrim(number_format((float) ($site->system_kwp ?? 0), 2, ',', '.'), '0'), ',') }} kWp</dd></div>
                        <div><dt>Trạng thái</dt><dd>{{ $projectStatus }}</dd></div>
                        <div><dt>Người phụ trách</dt><dd>{{ $project['lead_engineer'] }}</dd></div>
                        <div><dt>Tiến độ</dt><dd>{{ $deploymentProgress }}%</dd></div>
                    </dl>

                    <h3 class="pword-section-title">THAO TÁC NHANH</h3>
                    <div class="pword-actions">
                        <a href="#project-files" class="pword-btn light"><i class="bi bi-folder2-open"></i>Hồ sơ công trình</a>
                        <a href="#project-history" class="pword-btn light"><i class="bi bi-clock-history"></i>Lịch sử</a>
                        @if(Route::has('ky-thuat.kpis.project'))
                            <a href="{{ route('ky-thuat.kpis.project', ['site'=>$site->id, 'month'=>now()->format('Y-m')]) }}" class="pword-btn light"><i class="bi bi-bar-chart-line"></i>KPI kỹ thuật</a>
                        @endif
                        @if($canSeeFinance)
                            <a href="{{ route('projects-unified.show', ['site' => $site->id, 'step' => 'finance']) }}" class="pword-btn light"><i class="bi bi-cash-coin"></i>Tài chính công trình</a>
                        @endif
                        @if($canManage)
                            <details class="pword-inline-edit">
                                <summary class="pword-btn light"><i class="bi bi-pencil-square"></i>Chỉnh sửa</summary>
                                <form method="POST" action="{{ route('projects-unified.engineer.update', $site) }}">
                                    @csrf
                                    <label>Người phụ trách
                                        <select name="lead_engineer_id" required>
                                            @foreach($engineers as $engineer)
                                                <option value="{{ $engineer->id }}" @selected((int)$project['lead_engineer_id']===(int)$engineer->id)>{{ $engineer->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Ghi chú<textarea name="note" rows="2"></textarea></label>
                                    <button class="pword-btn primary" type="submit">Lưu thay đổi</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </section>

                <details class="pword-secondary" id="project-files">
                    <summary>Hồ sơ công trình <span>{{ $documents->count() }} file</span></summary>
                    <div class="pword-file-list">
                        @forelse($documents->take(12) as $document)
                            <a href="{{ asset('storage/'.$document->path) }}" target="_blank"><i class="bi bi-file-earmark-check"></i><span><strong>{{ $document->original_name ?? $document->title }}</strong><small>{{ $document->uploader_name ?: 'Hệ thống' }}</small></span></a>
                        @empty
                            <p>Chưa có hồ sơ.</p>
                        @endforelse
                    </div>
                </details>

                <details class="pword-secondary" id="project-history">
                    <summary>Lịch sử công trình <span>{{ $history->count() }} hoạt động</span></summary>
                    <div class="pword-history">
                        @forelse($history->take(12) as $item)
                            <div><i class="bi bi-clock-history"></i><span><strong>{{ $item->note ?: str_replace('_', ' ', (string) $item->action) }}</strong><small>{{ optional($item->created_at)->format('d/m/Y H:i') }}</small></span></div>
                        @empty
                            <p>Chưa có hoạt động.</p>
                        @endforelse
                    </div>
                </details>
            @elseif($uiStep === 'finance' && $canSeeFinance)
                @include('projects-unified.partials.admin-finance')
            @elseif($uiStep === 'materials')
                @php
                    $proposalStatuses = ['SUBMITTED' => 'Chờ Admin duyệt', 'NEEDS_REVISION' => 'Cần chỉnh sửa', 'ADMIN_APPROVED' => 'Đã duyệt · Chờ Kho', 'PARTIALLY_ALLOCATED' => 'Kho đang soạn', 'WAREHOUSE_ALLOCATED' => 'Kho đã soạn', 'READY_FOR_EXPORT' => 'Đã chuyển Kho', 'EXPORTED' => 'Đã xuất kho'];
                    $proposalTone = fn ($status) => match ((string) $status) { 'SUBMITTED' => 'pending', 'NEEDS_REVISION' => 'revision', 'EXPORTED' => 'complete', default => 'warehouse' };
                    $waitingCount = $materialProposals->whereIn('status', ['SUBMITTED', 'NEEDS_REVISION'])->count();
                    $warehouseCount = $materialProposals->whereIn('status', ['ADMIN_APPROVED', 'PARTIALLY_ALLOCATED', 'WAREHOUSE_ALLOCATED', 'READY_FOR_EXPORT'])->count();
                    $exportedCount = $materialProposals->where('status', 'EXPORTED')->count();
                    $formatProposalQty = fn ($quantity) => rtrim(rtrim(number_format((float) $quantity, 2, ',', '.'), '0'), ',');
                @endphp
                <section class="pword-panel epm-panel">
                    <header class="epm-header"><div><small>CÔNG TRÌNH · BƯỚC 3</small><h2>Đề xuất vật tư</h2><p>Kỹ thuật lập nhu cầu, Admin duyệt rồi mới chuyển sang Kho.</p></div>
                        @if($canProposeMaterials)
                            <button class="pword-btn primary" type="button" data-pword-open-dialog="material-create-dialog"><i class="bi bi-plus-lg"></i> Tạo đề xuất</button>
                        @endif
                    </header>

                    <div class="epm-summary"><div><span>Tổng đề xuất</span><strong>{{ $materialProposals->count() }}</strong></div><div class="pending"><span>Chờ duyệt</span><strong>{{ $waitingCount }}</strong></div><div class="warehouse"><span>Kho xử lý</span><strong>{{ $warehouseCount }}</strong></div><div class="complete"><span>Đã xuất</span><strong>{{ $exportedCount }}</strong></div></div>

                    <div class="epm-list"><div class="epm-list-head"><span>Phiếu / Người đề xuất</span><span>Vật tư</span><span>Trạng thái</span><span>Thao tác</span></div>
                        @forelse($materialProposals as $proposal)
                            @php
                                $proposalItems = $materialProposalItems[$proposal->id] ?? collect();
                                $linkedMaterialRequestId = $proposalItems->pluck('material_request_id')->filter()->first();
                                $proposalStatus = (string) $proposal->status;
                            @endphp
                            <details class="epm-proposal">
                                <summary class="epm-proposal-summary"><div><strong>DX-{{ str_pad((string) $proposal->id, 5, '0', STR_PAD_LEFT) }}</strong><small>{{ $proposal->creator_name ?: 'Người lập' }} · {{ \Illuminate\Support\Carbon::parse($proposal->created_at)->format('d/m/Y H:i') }}</small></div><div><strong>{{ $proposalItems->count() }} dòng</strong><small>{{ $formatProposalQty($proposalItems->sum('requested_qty')) }} tổng số lượng</small></div><div><span class="epm-status {{ $proposalTone($proposalStatus) }}">{{ $proposalStatuses[$proposalStatus] ?? $proposalStatus }}</span></div><div class="epm-expand-label"><span>Xem chi tiết</span><i class="bi bi-chevron-down"></i></div></summary>

                                <div class="epm-proposal-body"><header class="epm-popup-header"><div><small>CHI TIẾT ĐỀ XUẤT</small><strong>DX-{{ str_pad((string) $proposal->id, 5, '0', STR_PAD_LEFT) }}</strong></div><button type="button" class="epm-popup-close" onclick="this.closest('details').removeAttribute('open')"><i class="bi bi-x-lg"></i></button></header><table class="epm-lines"><thead><tr><th>Tên vật tư đề xuất</th><th>Số lượng</th><th>Ghi chú</th><th>Sản phẩm Kho đã chọn</th></tr></thead><tbody>
                                    @foreach($proposalItems as $item)
                                        <tr><td><strong>{{ $item->requested_name }}</strong></td><td>{{ $formatProposalQty($item->requested_qty) }}</td><td>{{ $item->request_note ?: '—' }}</td><td>@if($item->selected_product_name)<strong>{{ $item->selected_product_name }}</strong><small>{{ $item->selected_warehouse_name ?: 'Kho đã ghép' }}</small>@elseif($linkedMaterialRequestId)<span class="epm-muted">Kho chưa ghép sản phẩm</span>@else<span class="epm-muted">Chưa chuyển Kho</span>@endif</td></tr>
                                    @endforeach
                                </tbody></table>

                                    @if($proposalStatus === 'NEEDS_REVISION' && !empty($proposal->approval_note))
                                        <div class="epm-revision-note"><i class="bi bi-exclamation-circle"></i> {{ $proposal->approval_note }}</div>
                                    @endif

                                    <footer class="epm-proposal-actions">
                                        @if($canAdminApproveMaterials && in_array($proposalStatus, ['SUBMITTED', 'NEEDS_REVISION'], true))
                                            <button class="pword-btn primary" type="button" data-pword-open-dialog="material-approve-{{ $proposal->id }}"><i class="bi bi-check2-circle"></i> Duyệt &amp; chuyển Kho</button>
                                            <button class="pword-btn danger" type="button" data-pword-open-dialog="material-return-{{ $proposal->id }}">Trả chỉnh sửa</button>
                                            <dialog class="pword-assign-dialog" id="material-approve-{{ $proposal->id }}"><div class="pword-assign-dialog-card"><header><div><small>PHÊ DUYỆT VẬT TƯ</small><h3>Duyệt đề xuất #{{ $proposal->id }}</h3></div><button type="button" data-pword-close-dialog><i class="bi bi-x-lg"></i></button></header><form method="POST" action="{{ route('projects-unified.materials.proposal.approve', [$site, $proposal->id]) }}" class="pword-form">@csrf<label>Ý kiến Admin<textarea name="approval_note" rows="3" placeholder="Lưu ý cho Kho nếu cần..."></textarea></label><div class="pword-dialog-actions"><button class="pword-btn light" type="button" data-pword-close-dialog>Hủy</button><button class="pword-btn primary" type="submit">Duyệt &amp; chuyển Kho</button></div></form></div></dialog>
                                            <dialog class="pword-assign-dialog" id="material-return-{{ $proposal->id }}"><div class="pword-assign-dialog-card"><header><div><small>PHẢN HỒI ĐỀ XUẤT</small><h3>Trả đề xuất #{{ $proposal->id }}</h3></div><button type="button" data-pword-close-dialog><i class="bi bi-x-lg"></i></button></header><form method="POST" action="{{ route('projects-unified.materials.proposal.return', [$site, $proposal->id]) }}" class="pword-form">@csrf<label>Lý do cần chỉnh sửa<textarea name="revision_note" rows="4" required></textarea></label><div class="pword-dialog-actions"><button class="pword-btn light" type="button" data-pword-close-dialog>Hủy</button><button class="pword-btn danger" type="submit">Trả chỉnh sửa</button></div></form></div></dialog>
                                        @endif
                                        @if($linkedMaterialRequestId && Route::has('material-requests.show'))
                                            <a class="pword-btn light" href="{{ route('material-requests.show', $linkedMaterialRequestId) }}"><i class="bi bi-box-seam"></i> Xem phiếu Kho #{{ $linkedMaterialRequestId }}</a>
                                        @endif
                                        @if($proposal->attachment_path)
                                            <a class="epm-original-file" href="{{ asset('storage/'.$proposal->attachment_path) }}" target="_blank"><i class="bi bi-file-earmark-excel"></i> File gốc</a>
                                        @endif
                                    </footer>
                                </div>
                            </details>
                        @empty
                            <div class="epm-empty"><i class="bi bi-inboxes"></i><strong>Chưa có đề xuất vật tư</strong><span>Tạo đề xuất thủ công hoặc tải danh sách từ Excel.</span></div>
                        @endforelse
                    </div>

                    @if($canProposeMaterials)
                        <dialog class="pword-assign-dialog epm-create-dialog" id="material-create-dialog"><div class="pword-assign-dialog-card"><header><div><small>ĐỀ XUẤT VẬT TƯ</small><h3>Tạo đề xuất mới</h3></div><button type="button" data-pword-close-dialog><i class="bi bi-x-lg"></i></button></header>
                            <form method="POST" enctype="multipart/form-data" action="{{ route('projects-unified.materials.proposal.store', $site) }}" class="epm-create-form" data-material-form data-excel-preview-url="{{ route('projects-unified.materials.proposal.excel-preview', $site) }}">
                                @csrf
                                <input type="hidden" name="proposal_type" value="INITIAL">
                                <input type="hidden" name="priority" value="normal">
                                <div class="epm-import-bar"><div><strong>Nhập tay hoặc tải file Excel</strong><small>Chỉ cần ba cột: Tên vật tư, Số lượng, Ghi chú.</small></div><div><a class="pword-btn light" href="{{ route('projects-unified.materials.proposal.excel-template', $site) }}"><i class="bi bi-download"></i> File mẫu</a><label class="pword-btn light epm-upload-button"><i class="bi bi-file-earmark-excel"></i> Nhập Excel<input type="file" name="attachment" accept=".xlsx,.xls,.csv" data-material-excel></label></div></div>
                                <div class="epm-import-feedback" data-excel-feedback hidden></div>

                                <div class="epm-entry-head"><span>Tên vật tư</span><span>Số lượng</span><span>Ghi chú</span><span></span></div>
                                <div class="epm-entry-list" data-material-rows>
                                    <div class="epm-entry-row" data-material-row><input name="items[0][name]" required placeholder="Nhập tên vật tư"><input type="number" name="items[0][qty]" min="0.01" step="0.01" required placeholder="0"><input name="items[0][note]" placeholder="Ghi chú nếu có"><button type="button" class="pword-row-remove" data-remove-material aria-label="Xóa dòng"><i class="bi bi-x-lg"></i></button></div>
                                </div>
                                <button type="button" class="epm-add-row" data-add-material><i class="bi bi-plus-lg"></i> Thêm vật tư</button>
                                <div class="epm-create-footer"><span><b data-material-count>1</b> vật tư · Admin duyệt xong mới chuyển Kho.</span><button class="pword-btn primary" type="submit"><i class="bi bi-send-check"></i> Gửi Admin duyệt</button></div>
                            </form>
                        </div></dialog>
                    @endif
                </section>
            @else
                @include('projects-unified.partials.workflow-word')
            @endif
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    document.addEventListener('click', event => {
        const opener = event.target.closest('[data-pword-open-dialog]');
        if (opener) {
            const dialog = document.getElementById(opener.dataset.pwordOpenDialog);
            if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
            return;
        }
        const closer = event.target.closest('[data-pword-close-dialog]');
        if (closer) closer.closest('dialog')?.close();
    });
    document.addEventListener('click', event => {
        if (event.target.matches('dialog.pword-assign-dialog')) event.target.close();
    });

    const host = document.querySelector('[data-material-rows]');
    const add = document.querySelector('[data-add-material]');
    if (!host || !add) return;
    const form = document.querySelector('[data-material-form]');
    const fileInput = document.querySelector('[data-material-excel]');
    const feedback = document.querySelector('[data-excel-feedback]');
    const counter = document.querySelector('[data-material-count]');
    const renumber = () => [...host.querySelectorAll('[data-material-row]')].forEach((row, index) => {
        row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`));
        if (counter) counter.textContent = host.querySelectorAll('[data-material-row]').length;
    });
    add.addEventListener('click', () => {
        const row = host.querySelector('[data-material-row]')?.cloneNode(true);
        if (!row) return;
        row.querySelectorAll('input').forEach(input => { if (input.type !== 'hidden') input.value = ''; });
        host.appendChild(row);
        renumber();
    });
    host.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-material]');
        if (!button) return;
        if (host.querySelectorAll('[data-material-row]').length > 1) button.closest('[data-material-row]').remove();
        renumber();
    });

    fileInput?.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        if (!file || !form || !feedback) return;

        feedback.hidden = false;
        feedback.className = 'epm-import-feedback loading';
        feedback.textContent = 'Đang đọc và kiểm tra file Excel...';

        const payload = new FormData();
        payload.append('spreadsheet', file);
        payload.append('_token', form.querySelector('[name="_token"]').value);

        try {
            const response = await fetch(form.dataset.excelPreviewUrl, {
                method: 'POST',
                headers: {'Accept': 'application/json'},
                body: payload,
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Không đọc được file Excel.');
            }

            const template = host.querySelector('[data-material-row]')?.cloneNode(true);
            if (!template) throw new Error('Không khởi tạo được bảng vật tư.');

            host.innerHTML = '';
            (result.data || []).forEach(item => {
                const row = template.cloneNode(true);
                row.querySelector('[name$="[name]"]').value = item.name || '';
                row.querySelector('[name$="[qty]"]').value = item.qty || '';
                row.querySelector('[name$="[note]"]').value = item.note || '';
                row.classList.toggle('duplicate', Boolean(item.duplicate));
                host.appendChild(row);
            });
            renumber();

            const warnings = result.errors || [];
            const duplicateCount = Number(result.duplicates || 0);
            feedback.className = 'epm-import-feedback ' + (warnings.length || duplicateCount ? 'warning' : 'success');
            feedback.textContent = `Đã nhập ${result.data.length} vật tư` + (duplicateCount ? ` · ${duplicateCount} dòng có tên trùng` : '') + (warnings.length ? ` · ${warnings.length} dòng lỗi: ${warnings.join(' ')}` : '') + '. Có thể chỉnh trực tiếp trước khi gửi.';
        } catch (error) {
            feedback.className = 'epm-import-feedback danger';
            feedback.textContent = error.message || 'Không đọc được file Excel.';
            fileInput.value = '';
        }
    });
})();
</script>
@endpush
