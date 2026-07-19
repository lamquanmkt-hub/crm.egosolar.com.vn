@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Marketing Performance</h4>
            <small class="text-muted">Gộp: Ngân sách + Chỉ số</small>
        </div>

        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('marketing.budget') }}">Trang Ngân sách</a>
            <a class="btn btn-outline-secondary" href="{{ route('marketing.metrics') }}">Trang Chỉ số</a>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="GET" action="{{ route('marketing.performance') }}">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Tháng</label>
                    <input type="month" name="month" value="{{ $month }}" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted">Kênh</label>
                    <select name="platform" class="form-select">
                        <option value="">-- Tất cả --</option>
                        <option value="Facebook" {{ ($platform ?? '')=='Facebook' ? 'selected' : '' }}>Facebook</option>
                        <option value="Google"   {{ ($platform ?? '')=='Google' ? 'selected' : '' }}>Google</option>
                        <option value="TikTok"   {{ ($platform ?? '')=='TikTok' ? 'selected' : '' }}>TikTok</option>
                        <option value="Zalo"     {{ ($platform ?? '')=='Zalo' ? 'selected' : '' }}>Zalo</option>
                        <option value="Khác"     {{ ($platform ?? '')=='Khác' ? 'selected' : '' }}>Khác</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-outline-secondary w-100">Lọc</button>
                    <a href="{{ route('marketing.performance') }}" class="btn btn-light w-100">Xóa</a>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Ngân sách</div>
            <div class="fs-5 fw-bold">{{ number_format($kpi['budget']) }} đ</div>
        </div></div></div>

        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Chi tiêu</div>
            <div class="fs-5 fw-bold">{{ number_format($kpi['spend']) }} đ</div>
        </div></div></div>

        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Lead</div>
            <div class="fs-5 fw-bold">{{ number_format($kpi['leads']) }}</div>
        </div></div></div>

        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Đơn</div>
            <div class="fs-5 fw-bold">{{ number_format($kpi['orders']) }}</div>
        </div></div></div>

        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">ROAS</div>
            <div class="fs-5 fw-bold">{{ $kpi['roas'] }}</div>
            <div class="text-muted small">CPL: {{ number_format($kpi['cpl']) }} | CPO: {{ number_format($kpi['cpo']) }}</div>
        </div></div></div>
    </div>

    {{-- Performance table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between">
            <span>Bảng tổng hợp</span>
            <span class="text-muted small">Gộp theo tháng & kênh</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tháng</th>
                        <th>Kênh</th>
                        <th class="text-end">Ngân sách</th>
                        <th class="text-end">Chi tiêu (thực tế)</th>
                        <th class="text-end">Lead</th>
                        <th class="text-end">CPL</th>
                        <th class="text-end">Đơn</th>
                        <th class="text-end">CPO</th>
                        <th class="text-end">Doanh thu</th>
                        <th class="text-end">ROAS</th>
                        <th class="text-end">% tiêu ngân sách</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($performance as $p)
                        @php
                            $cpl  = $p['leads']  > 0 ? round($p['spend'] / $p['leads']) : 0;
                            $cpo  = $p['orders'] > 0 ? round($p['spend'] / $p['orders']) : 0;
                            $roas = $p['spend']  > 0 ? round($p['revenue'] / $p['spend'], 2) : 0;
                            $percent = $p['budget'] > 0 ? round(($p['spend'] / $p['budget']) * 100) : 0;
                        @endphp
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $p['month'])->format('m/Y') }}</td>
                            <td>{{ $p['platform'] }}</td>
                            <td class="text-end">{{ number_format($p['budget']) }} đ</td>
                            <td class="text-end">{{ number_format($p['spend']) }} đ</td>
                            <td class="text-end">{{ number_format($p['leads']) }}</td>
                            <td class="text-end">{{ number_format($cpl) }}</td>
                            <td class="text-end">{{ number_format($p['orders']) }}</td>
                            <td class="text-end">{{ number_format($cpo) }}</td>
                            <td class="text-end">{{ number_format($p['revenue']) }} đ</td>
                            <td class="text-end">{{ $roas }}</td>
                            <td class="text-end">{{ $percent }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">Chưa có dữ liệu</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
