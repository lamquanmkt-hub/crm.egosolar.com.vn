@extends('layouts.app')

@section('title', 'Cài đặt cấu hình KPI Kỹ thuật')

@push('styles')
    @include('technical.partials.plan-styles')
    <link rel="stylesheet"
          href="{{ asset('css/technical-kpi.css') }}?v={{ file_exists(public_path('css/technical-kpi.css')) ? filemtime(public_path('css/technical-kpi.css')) : '1.0.0' }}">
@endpush

@section('content')
@php
    $struct = $config->salary_structure ?? [];
    $criteriaList = $config->criteria_config ?? [];
    $tiers = $config->payout_tiers ?? [];
    $nextVersion = ((int) $versions->max('version')) + 1;
@endphp

<!-- TECHNICAL_KPI_CONFIG_CONTENT_START -->
<div class="container-fluid py-3 tw-wrap">

    {{-- Breadcrumb dùng chung cho khu vực Kỹ thuật --}}
    @include('technical.partials.dashboard-breadcrumb', [
        'trail' => [
            'Kỹ thuật' => route('technical.dashboard'),
            'KPI' => route('ky-thuat.kpis.index'),
            'Cài đặt KPI' => null,
        ],
    ])

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Cài đặt cấu hình KPI Kỹ thuật</h1>
            <p class="tw-head__sub">
                Quản lý phiên bản quy chế lương KPI, tiêu chí đánh giá và bảng quy đổi hiệu suất theo tài liệu Ban lãnh đạo ban hành.
            </p>
        </div>
        <div class="tw-head__actions">
            <a href="{{ route('ky-thuat.kpis.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại Bảng KPI
            </a>
        </div>
    </div>

    {{-- Flash messages chuẩn CRM --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger py-2" role="alert">
            <strong><i class="bi bi-exclamation-octagon me-1"></i> Vui lòng kiểm tra lại dữ liệu:</strong>
            <ul class="mb-0 ps-3 mt-1 small">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Danh sách phiên bản (Dùng tw-chips chuẩn của CRM) --}}
    <div class="tw-card mb-3">
        <div class="tw-card__body py-2">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="small fw-semibold text-muted text-uppercase">Lịch sử các phiên bản:</span>
                <div class="tw-chips">
                    @foreach($versions as $ver)
                        <a href="{{ route('ky-thuat.kpis.config', ['version_id' => $ver->id]) }}" 
                           class="tw-chip {{ $config->id === $ver->id ? 'is-active' : '' }}">
                            <span>v{{ $ver->version }} · {{ Str::limit($ver->version_name, 26) }}</span>
                            @if($ver->is_current)
                                <span class="badge bg-success ms-1">Đang áp dụng</span>
                            @elseif($ver->status === 'draft')
                                <span class="badge bg-warning text-dark ms-1">Nháp</span>
                            @else
                                <span class="badge bg-secondary ms-1">Lưu trữ</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Tóm tắt phiên bản đang chọn --}}
    <div class="tw-card mb-3">
        <div class="tw-card__head">
            <div>
                <h2 class="tw-card__title">
                    Phiên bản v{{ $config->version }}: {{ $config->version_name }}
                    @if($config->is_current)
                        <span class="badge bg-success ms-2">Đang áp dụng</span>
                    @elseif($config->status === 'draft')
                        <span class="badge bg-warning text-dark ms-2">Bản nháp</span>
                    @else
                        <span class="badge bg-secondary ms-2">Đã lưu trữ</span>
                    @endif
                </h2>
                @if($config->notes)
                    <div class="text-muted small mt-1">
                        Ghi chú: {{ $config->notes }}
                    </div>
                @endif
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openPreviewModal({{ $config->id }})">
                    <i class="bi bi-eye"></i> Xem trước tác động bảng lương
                </button>

                @if($config->status === 'draft' && $canApprove)
                    <form method="POST" action="{{ route('ky-thuat.kpis.config.approve', $config->id) }}" onsubmit="return confirm('Bạn có chắc chắn muốn PHÊ DUYỆT và kích hoạt phiên bản KPI v{{ $config->version }} không?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="bi bi-check-lg"></i> Phê duyệt áp dụng
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="tw-card__body">
            <dl class="row small mb-0">
                <dt class="col-sm-3 text-muted">Ngày bắt đầu hiệu lực:</dt>
                <dd class="col-sm-3 fw-semibold">{{ $config->effective_date ? $config->effective_date->format('d/m/Y') : 'Chưa thiết lập' }}</dd>

                <dt class="col-sm-3 text-muted">Người lập phiên bản:</dt>
                <dd class="col-sm-3 fw-semibold">{{ $config->creator->name ?? 'Hệ thống' }}</dd>

                <dt class="col-sm-3 text-muted">Trạng thái phê duyệt:</dt>
                <dd class="col-sm-3 fw-semibold">
                    @if($config->approved_at)
                        Đã duyệt bởi {{ $config->approver->name ?? 'Admin' }} ({{ $config->approved_at->format('d/m/Y') }})
                    @else
                        <span class="text-warning">Chưa phê duyệt (Bản nháp)</span>
                    @endif
                </dd>

                <dt class="col-sm-3 text-muted">Cơ cấu lương:</dt>
                <dd class="col-sm-3 fw-bold text-primary">
                    {{ number_format(($struct['base_salary_rate'] ?? 0.7) * 100, 0) }}% Cố định / {{ number_format(($struct['kpi_salary_rate'] ?? 0.3) * 100, 0) }}% Quỹ KPI
                </dd>
            </dl>
        </div>
    </div>

    {{-- Lương thoả thuận từng kỹ sư (nguồn duy nhất cho cột lương ở Bảng KPI) --}}
    @if($canEditSalary ?? false)
    <div class="tw-card mb-3" id="luong-thoa-thuan">
        <div class="tw-card__head">
            <div>
                <h2 class="tw-card__title">Lương thoả thuận của từng kỹ sư</h2>
                <div class="text-muted small">Bảng KPI lấy lương thoả thuận từ đây (theo tháng áp dụng gần nhất). Phiếu lương đã duyệt vẫn giữ số đã chốt.</div>
            </div>
            <a href="{{ route('ky-thuat.kpis.input') }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil-square"></i> Nhập số liệu KPI tháng
            </a>
        </div>
        <div class="tw-card__body">
            <form method="POST" action="{{ route('ky-thuat.kpis.config.salaries') }}">
                @csrf
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-sm-4 col-md-3">
                        <label class="form-label small fw-semibold" for="salary-month">Áp dụng từ tháng *</label>
                        <input type="month" class="form-control form-control-sm" id="salary-month" name="effective_month" required
                               value="{{ old('effective_month', now()->format('Y-m')) }}">
                    </div>
                    <div class="col text-muted small">Chỉ nhập cho người cần thay đổi; ô để trống sẽ giữ nguyên lương hiện hành.</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-3 tp-table">
                        <thead>
                            <tr>
                                <th>Nhân viên</th>
                                <th class="text-end">Lương đang áp dụng</th>
                                <th style="min-width:180px">Lương thoả thuận mới (đ)</th>
                                <th style="min-width:200px">Ghi chú</th>
                                <th>Lịch sử</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($salaryRows as $row)
                                <tr>
                                    <td>
                                        <span class="fw-semibold">{{ $row['name'] }}</span>
                                        <div class="text-muted small">{{ $row['code'] }} · {{ $row['position'] }}</div>
                                    </td>
                                    <td class="text-end">
                                        @if($row['current'] !== null)
                                            <span class="fw-semibold">{{ number_format($row['current'], 0, ',', '.') }} đ</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning-emphasis border">Chưa nhập</span>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="1000" class="form-control form-control-sm text-end"
                                               name="salaries[{{ $row['id'] }}][amount]" value="{{ old('salaries.'.$row['id'].'.amount') }}"
                                               placeholder="{{ $row['current'] !== null ? number_format($row['current'], 0, '', '') : 'VD: 15000000' }}">
                                    </td>
                                    <td>
                                        <input type="text" maxlength="500" class="form-control form-control-sm"
                                               name="salaries[{{ $row['id'] }}][note]" value="{{ old('salaries.'.$row['id'].'.note') }}"
                                               placeholder="VD: Tăng lương theo HĐLĐ">
                                    </td>
                                    <td class="small text-muted">
                                        @forelse($row['history']->take(3) as $h)
                                            <div>Từ {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $h->effective_month)->format('m/Y') }}: {{ number_format((float) $h->agreed_salary, 0, ',', '.') }} đ</div>
                                        @empty
                                            —
                                        @endforelse
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Lưu lương thoả thuận</button>
            </form>
        </div>
    </div>
    @endif

    {{-- Form tạo bản nháp mới / cấu hình --}}
    @if($canEdit)
    <div class="tw-card mb-3">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Soạn thảo và lưu phiên bản cấu hình mới (v{{ $nextVersion }})</h2>
            <span class="text-muted small">Mọi thay đổi sẽ tạo một phiên bản nháp độc lập, không ghi đè kỳ lương cũ</span>
        </div>

        <div class="tw-card__body">
            <form method="POST" action="{{ route('ky-thuat.kpis.config.draft') }}" id="kpiConfigForm">
                @csrf

                {{-- Thông tin phiên bản --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold" for="cfg-version-name">Tên phiên bản cấu hình mới *</label>
                        <input type="text" class="form-control form-control-sm" id="cfg-version-name" name="version_name" required 
                               value="{{ old('version_name', 'Phiên bản điều chỉnh (Tháng ' . now()->addMonth()->format('m/Y') . ')') }}"
                               placeholder="Ví dụ: Quy chế KPI áp dụng Quý 4/2026">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold" for="cfg-effective-date">Ngày bắt đầu hiệu lực *</label>
                        <input type="date" class="form-control form-control-sm" id="cfg-effective-date" name="effective_date" required 
                               value="{{ old('effective_date', now()->startOfMonth()->toDateString()) }}">
                    </div>
                </div>

                {{-- Cơ cấu lương --}}
                <h3 class="tw-card__title fs-6 mb-2 mt-4">1. Cấu hình cơ cấu lương thỏa thuận</h3>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small" for="cfg-base-rate">Tỷ lệ lương cố định (%) *</label>
                        <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm" 
                               id="cfg-base-rate" name="base_salary_rate" required
                               value="{{ old('base_salary_rate', number_format(($struct['base_salary_rate'] ?? 0.70) * 100, 1)) }}">
                        <div class="form-text">Mặc định: 70%</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="cfg-kpi-rate">Tỷ lệ quỹ KPI (%) *</label>
                        <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm" 
                               id="cfg-kpi-rate" name="kpi_salary_rate" required
                               value="{{ old('kpi_salary_rate', number_format(($struct['kpi_salary_rate'] ?? 0.30) * 100, 1)) }}">
                        <div class="form-text">Mặc định: 30%</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="cfg-round-rule">Quy tắc làm tròn tiền *</label>
                        <select class="form-select form-select-sm" id="cfg-round-rule" name="round_rule">
                            <option value="1000" @selected(old('round_rule', $struct['round_rule'] ?? '1000') === '1000')>Làm tròn đến 1.000 đ</option>
                            <option value="10000" @selected(old('round_rule', $struct['round_rule'] ?? '1000') === '10000')>Làm tròn đến 10.000 đ</option>
                            <option value="ceil_1000" @selected(old('round_rule', $struct['round_rule'] ?? '1000') === 'ceil_1000')>Làm tròn lên 1.000 đ</option>
                            <option value="none" @selected(old('round_rule', $struct['round_rule'] ?? '1000') === 'none')>Không làm tròn</option>
                        </select>
                        <div class="form-text">Tiền KPI & tổng lương</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Hạn mức vượt 100%</label>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="allow_exceed_100" value="1" id="cfg-allow-exceed"
                                   @checked(old('allow_exceed_100', !empty($struct['allow_exceed_100'])))>
                            <label class="form-check-label small" for="cfg-allow-exceed">
                                Cho phép KPI vượt 100%
                            </label>
                        </div>
                        @php
                            $maxRateOld = $struct['max_kpi_rate'] ?? null;
                            $maxRateVal = old('max_kpi_rate', ($maxRateOld !== null && (float) $maxRateOld > 0) ? number_format((float) $maxRateOld * 100, 0, '.', '') : '');
                        @endphp
                        <label class="form-label small mt-2 mb-1" for="cfg-max-kpi-rate">Trần tối đa khi vượt (%)</label>
                        <input type="number" step="1" min="0" class="form-control form-control-sm" id="cfg-max-kpi-rate"
                               name="max_kpi_rate" value="{{ $maxRateVal }}" placeholder="Để trống = không giới hạn">
                        <div class="form-text">Vượt bao nhiêu trả bấy nhiêu nếu để trống.</div>
                    </div>
                </div>

                {{-- Cấu hình 6 tiêu chí --}}
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 mt-4">
                    <h3 class="tw-card__title fs-6 mb-0">2. Cấu hình tiêu chí đánh giá kỹ thuật (Tổng trọng số = 100%)</h3>
                    <div id="cfgWeightCounterBox" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                        Tổng trọng số đang áp dụng: <span id="cfgWeightCounterVal" class="fw-bold">100%</span>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm align-middle tp-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;" class="text-center">STT</th>
                                <th style="width: 200px;">Tên tiêu chí</th>
                                <th style="width: 100px;">Trọng số (%)</th>
                                <th>Ngưỡng đạt & Mục tiêu</th>
                                <th>Công thức đo lường</th>
                                <th>Nguồn dữ liệu CRM</th>
                                <th style="width: 80px;" class="text-center">Tính điểm?</th>
                                <th style="width: 130px;">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($criteriaList as $idx => $crit)
                            @php
                                $isWarr = ($crit['code'] ?? '') === 'warranty';
                                $critWeight = (float) ($crit['weight'] ?? 0);
                            @endphp
                            <tr class="{{ $isWarr ? 'table-light text-muted' : '' }}">
                                <td class="text-center">
                                    <strong>{{ $crit['no'] ?? ($idx + 1) }}</strong>
                                    <input type="hidden" name="criteria[{{ $idx }}][no]" value="{{ $crit['no'] ?? ($idx + 1) }}">
                                    <input type="hidden" name="criteria[{{ $idx }}][code]" value="{{ $crit['code'] }}">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="criteria[{{ $idx }}][name]" 
                                           value="{{ old('criteria.' . $idx . '.name', $crit['name']) }}" required>
                                </td>
                                <td>
                                    <input type="number" step="1" min="0" max="100" class="form-control form-control-sm cfg-weight-input"
                                           name="criteria[{{ $idx }}][weight]"
                                           value="{{ old('criteria.' . $idx . '.weight', number_format($critWeight * 100, 0)) }}"
                                           @if($isWarr) readonly style="background:#e9ecef;" @endif>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="criteria[{{ $idx }}][threshold]" 
                                           value="{{ old('criteria.' . $idx . '.threshold', $crit['threshold'] ?? $crit['target'] ?? '') }}">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="criteria[{{ $idx }}][formula]" 
                                           value="{{ old('criteria.' . $idx . '.formula', $crit['formula'] ?? $crit['measure'] ?? '') }}">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="criteria[{{ $idx }}][source]" 
                                           value="{{ old('criteria.' . $idx . '.source', $crit['source'] ?? '') }}">
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input cfg-calc-chk" name="criteria[{{ $idx }}][is_calculated]" value="1" 
                                           @checked(old('criteria.' . $idx . '.is_calculated', !empty($crit['is_calculated'])))
                                           @if($isWarr) disabled @endif>
                                </td>
                                <td>
                                    @if($isWarr)
                                        <span class="badge bg-secondary">Nháp (Chờ xác nhận)</span>
                                        <input type="hidden" name="criteria[{{ $idx }}][status]" value="draft">
                                    @else
                                        <select class="form-select form-select-sm cfg-status-select" name="criteria[{{ $idx }}][status]">
                                            <option value="applied" @selected(old('criteria.' . $idx . '.status', $crit['status'] ?? 'applied') === 'applied')>Đang áp dụng</option>
                                            <option value="draft" @selected(old('criteria.' . $idx . '.status', $crit['status'] ?? 'applied') === 'draft')>Bản nháp</option>
                                            <option value="disabled" @selected(old('criteria.' . $idx . '.status', $crit['status'] ?? 'applied') === 'disabled')>Ngừng áp dụng</option>
                                        </select>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Quy đổi KPI sang tiền --}}
                <h3 class="tw-card__title fs-6 mb-2 mt-4">3. Quy đổi KPI sang tiền hiệu suất (Hệ số nhân với Quỹ KPI)</h3>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="border rounded p-2 bg-light">
                            <label class="form-label small text-danger fw-semibold">Dưới 75% (Không đạt)</label>
                            <input type="number" step="0.05" min="0" max="2" class="form-control form-control-sm mb-1" 
                                   name="payout_tiers[under_75][rate]" 
                                   value="{{ old('payout_tiers.under_75.rate', $tiers['under_75']['rate'] ?? 0.0) }}">
                            <input type="text" class="form-control form-control-sm" name="payout_tiers[under_75][note]" 
                                   value="{{ old('payout_tiers.under_75.note', $tiers['under_75']['note'] ?? 'Không nhận tiền lương KPI') }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="border rounded p-2 bg-light">
                            <label class="form-label small text-warning fw-semibold">75% đến dưới 90% (Cần cải thiện)</label>
                            <input type="number" step="0.05" min="0" max="2" class="form-control form-control-sm mb-1" 
                                   name="payout_tiers[from_75_to_90][rate]" 
                                   value="{{ old('payout_tiers.from_75_to_90.rate', $tiers['from_75_to_90']['rate'] ?? 0.8) }}">
                            <input type="text" class="form-control form-control-sm" name="payout_tiers[from_75_to_90][note]" 
                                   value="{{ old('payout_tiers.from_75_to_90.note', $tiers['from_75_to_90']['note'] ?? 'Hưởng 80% tiền lương KPI') }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="border rounded p-2 bg-light">
                            <label class="form-label small text-success fw-semibold">90% đến dưới 100% (Đạt)</label>
                            <input type="number" step="0.05" min="0" max="2" class="form-control form-control-sm mb-1" 
                                   name="payout_tiers[from_90_to_100][rate]" 
                                   value="{{ old('payout_tiers.from_90_to_100.rate', $tiers['from_90_to_100']['rate'] ?? 1.0) }}">
                            <input type="text" class="form-control form-control-sm" name="payout_tiers[from_90_to_100][note]" 
                                   value="{{ old('payout_tiers.from_90_to_100.note', $tiers['from_90_to_100']['note'] ?? 'Hưởng 100% tiền lương KPI') }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="border rounded p-2 bg-light">
                            <label class="form-label small text-primary fw-semibold">Từ 100% trở lên (Vượt)</label>
                            <input type="number" step="0.05" min="0" max="2" class="form-control form-control-sm mb-1" 
                                   name="payout_tiers[above_100][rate]" 
                                   value="{{ old('payout_tiers.above_100.rate', $tiers['above_100']['rate'] ?? 1.0) }}">
                            <input type="text" class="form-control form-control-sm" name="payout_tiers[above_100][note]" 
                                   value="{{ old('payout_tiers.above_100.note', $tiers['above_100']['note'] ?? 'Hưởng 100% tiền lương KPI') }}">
                        </div>
                    </div>
                </div>

                {{-- Ghi chú phiên bản --}}
                <div class="mb-3">
                    <label class="form-label small" for="cfg-notes">Lý do điều chỉnh / Căn cứ văn bản của Ban lãnh đạo</label>
                    <textarea class="form-control form-control-sm" id="cfg-notes" name="notes" rows="2" 
                              placeholder="Ví dụ: Theo Quyết định số 15/QĐ-BLĐ ngày 25/09/2026..."></textarea>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save"></i> Lưu thành bản nháp mới (v{{ $nextVersion }})
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
<!-- TECHNICAL_KPI_CONFIG_CONTENT_END -->

{{-- Modal Xem trước kết quả tính lương (Bootstrap 5 chuẩn CRM) --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="previewModalLabel">Xem trước tác động bảng lương</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body" id="previewModalBody">
                <div class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Đang tính toán mô phỏng tác động bảng lương...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function updateConfigWeightTotal() {
        let total = 0;
        document.querySelectorAll('.cfg-weight-input').forEach((input, idx) => {
            const chk = document.querySelectorAll('.cfg-calc-chk')[idx];
            const sel = document.querySelectorAll('.cfg-status-select')[idx];
            const isCalc = chk ? chk.checked : false;
            const isApplied = sel ? (sel.value === 'applied') : true;

            if (isCalc && isApplied) {
                total += parseFloat(input.value || 0);
            }
        });

        const box = document.getElementById('cfgWeightCounterBox');
        const val = document.getElementById('cfgWeightCounterVal');
        if (val) {
            val.textContent = total.toFixed(1) + '%';
        }
        if (box) {
            if (Math.abs(total - 100) < 0.1) {
                box.className = 'badge bg-success-subtle text-success border border-success-subtle px-2 py-1';
            } else {
                box.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1';
            }
        }
    }

    document.querySelectorAll('.cfg-weight-input, .cfg-calc-chk, .cfg-status-select').forEach(el => {
        el.addEventListener('input', updateConfigWeightTotal);
        el.addEventListener('change', updateConfigWeightTotal);
    });

    const form = document.getElementById('kpiConfigForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            let total = 0;
            document.querySelectorAll('.cfg-weight-input').forEach((input, idx) => {
                const chk = document.querySelectorAll('.cfg-calc-chk')[idx];
                const sel = document.querySelectorAll('.cfg-status-select')[idx];
                const isCalc = chk ? chk.checked : false;
                const isApplied = sel ? (sel.value === 'applied') : true;
                if (isCalc && isApplied) {
                    total += parseFloat(input.value || 0);
                }
            });

            if (Math.abs(total - 100) > 0.1) {
                e.preventDefault();
                alert('Tổng trọng số các tiêu chí đang áp dụng phải bằng chính xác 100% (Hiện tại là: ' + total.toFixed(1) + '%). Vui lòng điều chỉnh lại.');
            }
        });
    }

    function openPreviewModal(configId) {
        const modalEl = document.getElementById('previewModal');
        const body = document.getElementById('previewModalBody');
        
        let bsModal;
        if (window.bootstrap && window.bootstrap.Modal) {
            bsModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        } else {
            $(modalEl).modal('show');
        }

        body.innerHTML = '<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Đang tải dữ liệu mô phỏng...</div>';

        fetch('{{ url("ky-thuat/kpis/cai-dat/xem-truoc") }}/' + configId)
            .then(res => res.json())
            .then(data => {
                let html = '<div class="small text-muted mb-2">Mô phỏng bảng lương theo <strong>v' + data.version + ' (' + data.version_name + ')</strong>:</div>';
                html += '<div class="table-responsive"><table class="table table-sm table-bordered align-middle tp-table mb-0">';
                html += '<thead class="table-light"><tr>';
                html += '<th>Nhân sự mẫu</th>';
                html += '<th class="text-end">Lương thỏa thuận</th>';
                html += '<th class="text-end">Lương cố định</th>';
                html += '<th class="text-end">Quỹ KPI</th>';
                html += '<th class="text-center">% Đạt</th>';
                html += '<th class="text-end">Tiền KPI hưởng</th>';
                html += '<th class="text-end">Tổng lương dự kiến</th>';
                html += '</tr></thead><tbody>';

                data.preview_results.forEach(item => {
                    const fmt = (n) => n !== null ? Number(n).toLocaleString('vi-VN') + ' đ' : 'Chưa thể tính';
                    html += '<tr>';
                    html += '<td class="fw-semibold">' + item.name + '</td>';
                    html += '<td class="text-end">' + fmt(item.agreed_salary) + '</td>';
                    html += '<td class="text-end text-muted">' + fmt(item.base_salary) + '</td>';
                    html += '<td class="text-end text-primary">' + fmt(item.kpi_base_salary) + '</td>';
                    html += '<td class="text-center fw-bold">' + (item.kpi_percent * 100).toFixed(1) + '%</td>';
                    html += '<td class="text-end fw-semibold text-success">' + fmt(item.real_kpi_salary) + '</td>';
                    html += '<td class="text-end fw-bold">' + fmt(item.total_income) + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table></div>';
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = '<div class="alert alert-danger py-2 mb-0">Lỗi khi tải dữ liệu mô phỏng: ' + err.message + '</div>';
            });
    }
</script>
@endpush
