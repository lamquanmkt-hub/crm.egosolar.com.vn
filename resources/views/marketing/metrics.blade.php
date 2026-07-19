@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Chỉ số Marketing</h4>
            <small class="text-muted">Marketing / Chỉ số</small>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif

    {{-- KPI --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Chi tiêu</div>
            <div class="fs-5 fw-bold">{{ number_format($sumSpend) }} đ</div>
        </div></div></div>

        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Lead</div>
            <div class="fs-5 fw-bold">{{ number_format($sumLeads) }}</div>
        </div></div></div>

        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Đơn</div>
            <div class="fs-5 fw-bold">{{ number_format($sumOrders) }}</div>
        </div></div></div>

        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Doanh thu</div>
            <div class="fs-5 fw-bold">{{ number_format($sumRevenue) }} đ</div>
        </div></div></div>

        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">ROAS</div>
            <div class="fs-5 fw-bold">{{ $roas }}</div>
            <div class="text-muted small">CPL: {{ number_format($cpl) }} | CPO: {{ number_format($cpo) }}</div>
        </div></div></div>
    </div>

    {{-- Lọc + Thêm --}}
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div class="fw-semibold">Bộ lọc & nhập liệu</div>
        <button class="btn btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#addMetric">
            + Nhập chỉ số
        </button>
    </div>

    {{-- Lọc --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="GET" action="{{ route('marketing.metrics') }}">
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

                <div class="col-md-4">
                    <label class="form-label small text-muted">Chiến dịch</label>
                    <input type="text" name="campaign" value="{{ $campaign ?? '' }}" class="form-control" placeholder="VD: Solar T1">
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-outline-secondary w-100">Lọc</button>
                    <a href="{{ route('marketing.metrics') }}" class="btn btn-light w-100">Xóa</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Thêm (collapse) --}}
    <div class="collapse {{ $errors->any() ? 'show' : '' }}" id="addMetric">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Nhập chỉ số</div>
            <div class="card-body">
                <form method="POST" action="{{ route('marketing.metrics.store') }}" class="row g-2">
                    @csrf

                    <div class="col-md-2">
                        <label class="form-label small text-muted">Ngày</label>
                        <input type="date" name="date" class="form-control" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small text-muted">Kênh</label>
                        <select name="platform" class="form-select" required>
                            <option value="Facebook">Facebook</option>
                            <option value="Google">Google</option>
                            <option value="TikTok">TikTok</option>
                            <option value="Zalo">Zalo</option>
                            <option value="Khác">Khác</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small text-muted">Chiến dịch</label>
                        <input type="text" name="campaign" class="form-control" placeholder="VD: Solar T1">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small text-muted">Chi tiêu (đ)</label>
                        <input type="number" name="spend" class="form-control" min="0" required>
                    </div>

                    <div class="col-md-1">
                        <label class="form-label small text-muted">Lead</label>
                        <input type="number" name="leads" class="form-control" min="0" required>
                    </div>

                    <div class="col-md-1">
                        <label class="form-label small text-muted">Đơn</label>
                        <input type="number" name="orders" class="form-control" min="0" required>
                    </div>

                    <div class="col-md-1">
                        <label class="form-label small text-muted">DT (đ)</label>
                        <input type="number" name="revenue" class="form-control" min="0" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label small text-muted">Ghi chú</label>
                        <input type="text" name="note" class="form-control" placeholder="Tuỳ chọn">
                    </div>

                    <div class="col-12">
                        <button class="btn btn-success">Lưu chỉ số</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Danh sách --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between">
            <span>Danh sách</span>
            <span class="text-muted small">Tổng: {{ $rows->total() }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ngày</th>
                        <th>Kênh</th>
                        <th>Chiến dịch</th>
                        <th class="text-end">Chi tiêu</th>
                        <th class="text-end">Lead</th>
                        <th class="text-end">CPL</th>
                        <th class="text-end">Đơn</th>
                        <th class="text-end">CPO</th>
                        <th class="text-end">DT</th>
                        <th class="text-end">ROAS</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        @php
                            $rowCpl  = $r->leads  > 0 ? round($r->spend / $r->leads) : 0;
                            $rowCpo  = $r->orders > 0 ? round($r->spend / $r->orders) : 0;
                            $rowRoas = $r->spend  > 0 ? round($r->revenue / $r->spend, 2) : 0;
                        @endphp
                        <tr>
                            <td>{{ optional($r->date)->format('d/m/Y') }}</td>
                            <td>{{ $r->platform }}</td>
                            <td>{{ $r->campaign }}</td>
                            <td class="text-end">{{ number_format($r->spend) }}</td>
                            <td class="text-end">{{ number_format($r->leads) }}</td>
                            <td class="text-end">{{ number_format($rowCpl) }}</td>
                            <td class="text-end">{{ number_format($r->orders) }}</td>
                            <td class="text-end">{{ number_format($rowCpo) }}</td>
                            <td class="text-end">{{ number_format($r->revenue) }}</td>
                            <td class="text-end">{{ $rowRoas }}</td>
                            <td class="text-muted">{{ $r->note }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">Chưa có dữ liệu</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-body">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection
