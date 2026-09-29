@extends('layouts.app')

@section('title', 'Nhập số liệu KPI tháng')

@push('styles')
    @include('technical.partials.plan-styles')
    <link rel="stylesheet"
          href="{{ asset('css/technical-kpi.css') }}?v={{ file_exists(public_path('css/technical-kpi.css')) ? filemtime(public_path('css/technical-kpi.css')) : '1.0.0' }}">
    <style>
        .kpi-in-table th, .kpi-in-table td { vertical-align: top; }
        .kpi-in-cell { min-width: 150px; }
        .kpi-in-pair { display: flex; gap: 4px; }
        .kpi-in-pair input { width: 64px; text-align: right; }
        .kpi-in-hint { font-size: 11.5px; color: #6b7a88; margin-top: 3px; line-height: 1.35; }
        .kpi-in-hint b { color: #0e8ea6; }
        .kpi-in-rate { font-size: 11.5px; font-weight: 600; margin-top: 2px; }
        .kpi-in-rate.is-over { color: #0d8a5f; }
        .kpi-in-rate.is-under { color: #b45309; }
        .kpi-in-adj { font-size: 12.5px; }
        .kpi-in-adj .plus { color: #0d8a5f; font-weight: 700; }
        .kpi-in-adj .minus { color: #b42318; font-weight: 700; }
    </style>
@endpush

@section('content')
@php
    $fmt = static fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $monthLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('m/Y');
@endphp
<div class="container-fluid py-3 tw-wrap">
    @include('technical.partials.dashboard-breadcrumb', [
        'trail' => [
            'Kỹ thuật' => route('technical.dashboard'),
            'KPI' => route('ky-thuat.kpis.index', ['period' => 'month', 'month' => $month]),
            'Nhập số liệu tháng' => null,
        ],
    ])

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Nhập số liệu KPI tháng {{ $monthLabel }}</h1>
            <p class="tw-head__sub">
                Mỗi tiêu chí nhập <b>Kế hoạch</b> và <b>Thực tế</b> (số công trình/hệ thống). Tỷ lệ đạt = Thực tế ÷ Kế hoạch,
                <b>vượt kế hoạch thì được vượt 100%</b>. Số tự động lấy từ dự án (/du-an) chỉ để tham khảo — ô đã nhập sẽ được ưu tiên.
            </p>
        </div>
        <div class="tw-head__actions d-flex gap-2">
            @include('technical.guides.partials.help-button', ['slug' => 'truong-phong-kpi-thang'])
            <form method="GET" action="{{ route('ky-thuat.kpis.input') }}" class="d-flex gap-2">
                <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm" onchange="this.form.submit()">
            </form>
            <a href="{{ route('ky-thuat.kpis.index', ['period' => 'month', 'month' => $month]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-table"></i> Xem bảng KPI
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i> {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2">
            <strong><i class="bi bi-exclamation-octagon me-1"></i> Vui lòng kiểm tra lại:</strong>
            <ul class="mb-0 ps-3 mt-1 small">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif
    @if(! $config)
        <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i> Chưa có cấu hình KPI được duyệt cho tháng này — đang dùng tiêu chí theo tài liệu chuẩn.</div>
    @endif

    <form method="POST" action="{{ route('ky-thuat.kpis.input.store') }}">
        @csrf
        <input type="hidden" name="month" value="{{ $month }}">
        <div class="tw-card mb-3">
            <div class="tw-card__head">
                <h2 class="tw-card__title">Số liệu theo tiêu chí</h2>
                <span class="text-muted small">{{ $rows->count() }} nhân viên · {{ $criteria->count() }} tiêu chí có trọng số</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 tp-table kpi-in-table">
                    <thead>
                        <tr>
                            <th style="min-width:180px">Nhân viên</th>
                            @foreach($criteria as $c)
                                <th class="kpi-in-cell">
                                    {{ $c['name'] }}
                                    <div class="text-muted small fw-normal">{{ number_format($c['weight'] * 100, 0) }}% · KH / TH</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $row['name'] }}</span>
                                    <div class="text-muted small">{{ $row['code'] }} · {{ $row['position'] }}</div>
                                    <div class="small mt-1">
                                        @if($row['salary'] !== null)
                                            <span class="text-muted">Lương: {{ number_format($row['salary'], 0, ',', '.') }} đ</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning-emphasis border">Chưa có lương thoả thuận</span>
                                        @endif
                                    </div>
                                    @if($row['projects']->isNotEmpty())
                                        <div class="kpi-in-hint">{{ $row['projects']->count() }} dự án trong tháng</div>
                                    @endif
                                </td>
                                @foreach($criteria as $c)
                                    @php
                                        $code = $c['code'];
                                        $saved = $row['saved']->get($code);
                                        $auto = $row['auto']->get($code);
                                        $plan = old("scores.{$row['id']}.{$code}.plan", $saved?->plan_value !== null ? $fmt($saved->plan_value) : '');
                                        $actual = old("scores.{$row['id']}.{$code}.actual", $saved?->actual_value !== null ? $fmt($saved->actual_value) : '');
                                        $rate = ($saved && (float) $saved->plan_value > 0) ? (float) $saved->actual_value / (float) $saved->plan_value : null;
                                    @endphp
                                    <td class="kpi-in-cell">
                                        <div class="kpi-in-pair">
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="KH"
                                                   name="scores[{{ $row['id'] }}][{{ $code }}][plan]" value="{{ $plan }}" aria-label="Kế hoạch {{ $c['name'] }} — {{ $row['name'] }}">
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="TH"
                                                   name="scores[{{ $row['id'] }}][{{ $code }}][actual]" value="{{ $actual }}" aria-label="Thực tế {{ $c['name'] }} — {{ $row['name'] }}">
                                        </div>
                                        @if($rate !== null)
                                            <div class="kpi-in-rate {{ $rate >= 1 ? 'is-over' : 'is-under' }}">Đạt {{ number_format($rate * 100, 1, ',', '.') }}%</div>
                                        @endif
                                        @if($auto && $auto['total'] !== null)
                                            <div class="kpi-in-hint">Tự động: <b>{{ $fmt($auto['passed']) }}/{{ $fmt($auto['total']) }}</b></div>
                                        @elseif(! $saved)
                                            <div class="kpi-in-hint">Chưa có số tự động</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="tw-card__body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">Để trống cả KH và TH = xoá số đã nhập (quay về số tự động nếu có).</span>
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Lưu số liệu tháng {{ $monthLabel }}</button>
            </div>
        </div>
    </form>

    <div class="tw-card mb-3" id="cong-tru-diem">
        <div class="tw-card__head">
            <div>
                <h2 class="tw-card__title">Cộng / trừ điểm KPI</h2>
                <div class="text-muted small">1 điểm = 1% KPI. Dùng cho điểm thưởng hoặc lỗi vi phạm nghiêm trọng (không đeo dây an toàn, bạo lực, lời lẽ thiếu chuẩn mực với chủ nhà: trừ 10–20 điểm). Bắt buộc lý do.</div>
            </div>
        </div>
        <div class="tw-card__body">
            <form method="POST" action="{{ route('ky-thuat.kpis.input.adjust') }}" class="row g-2 align-items-end mb-3">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <div class="col-md-3">
                    <label class="form-label small">Nhân viên</label>
                    <select name="user_id" class="form-select form-select-sm" required>
                        <option value="">— Chọn —</option>
                        @foreach($rows as $row)<option value="{{ $row['id'] }}" @selected((string) old('user_id') === (string) $row['id'])>{{ $row['name'] }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Điểm (+ cộng / − trừ)</label>
                    <input type="number" step="0.5" min="-100" max="100" name="points" class="form-control form-control-sm" value="{{ old('points') }}" placeholder="VD: -10" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Lý do (bắt buộc)</label>
                    <input type="text" name="reason" maxlength="2000" class="form-control form-control-sm" value="{{ old('reason') }}" placeholder="VD: Không đeo dây an toàn khi lên mái ngày 12/09" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-plus-lg"></i> Ghi nhận</button>
                </div>
            </form>

            @php $allAdjust = $rows->flatMap(fn ($r) => collect($r['adjustments'])->map(fn ($a) => ['name' => $r['name'], 'a' => $a])); @endphp
            @if($allAdjust->isEmpty())
                <div class="text-muted small">Chưa có điều chỉnh nào trong tháng {{ $monthLabel }}.</div>
            @else
                <table class="table table-sm mb-0 kpi-in-adj">
                    <thead><tr><th>Nhân viên</th><th class="text-end">Điểm</th><th>Lý do</th><th>Người ghi</th><th></th></tr></thead>
                    <tbody>
                        @foreach($allAdjust as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td class="text-end"><span class="{{ (float) $item['a']->points >= 0 ? 'plus' : 'minus' }}">{{ (float) $item['a']->points > 0 ? '+' : '' }}{{ $fmt($item['a']->points) }}</span></td>
                                <td>{{ $item['a']->reason }}</td>
                                <td class="text-muted">{{ $item['a']->created_by_name ?? '—' }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('ky-thuat.kpis.input.adjust.destroy', $item['a']->id) }}" onsubmit="return confirm('Xoá điều chỉnh này?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0">Xoá</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
