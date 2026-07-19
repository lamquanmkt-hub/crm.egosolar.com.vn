@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 mt-3">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Ngân sách & Chỉ số Marketing</h4>
            <small class="text-muted">Marketing / Quảng cáo</small>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif

    {{-- KPI TỔNG --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Ngân sách</div>
                    <div class="fs-5 fw-bold">{{ number_format($totalBudget) }} đ</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Đã chi (ngân sách)</div>
                    <div class="fs-5 fw-bold">{{ number_format($totalSpent) }} đ</div>
                    <div class="text-muted small">Chi theo chỉ số: {{ number_format($sumSpend) }} đ</div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Lead</div>
                    <div class="fs-5 fw-bold">{{ number_format($sumLeads) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Đơn</div>
                    <div class="fs-5 fw-bold">{{ number_format($sumOrders) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">ROAS</div>
                    <div class="fs-5 fw-bold">{{ $roas }}</div>
                    <div class="text-muted small">CPL {{ number_format($cpl) }} | CPO {{ number_format($cpo) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- THANH ĐIỀU KHIỂN --}}
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#budgetSummary">
                Tổng hợp ngân sách
            </button>

            <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#adsModal">
                + Thêm
            </button>
        </div>
        <div class="text-muted small">Xem tổng → lọc → nhập</div>
    </div>

    {{-- TỔNG HỢP NGÂN SÁCH --}}
    @php
    $hasFilter = request()->filled('from')
        || request()->filled('to')
        || request()->filled('platform')
        || request()->filled('campaign_id')
        || request()->filled('month'); // legacy
@endphp

<div class="collapse {{ $hasFilter ? 'show' : '' }}" id="budgetSummary">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Tổng hợp theo tháng & kênh</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Khoảng</th>
                            <th>Kênh</th>
                            <th class="text-end">Ngân sách</th>
                            <th class="text-end">Đã chi</th>
                            <th class="text-end">% tiêu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summary as $s)
                            @php
                                $p = ($s->total_budget ?? 0) > 0 ? round($s->total_spent / $s->total_budget * 100) : 0;
                            @endphp
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($s->month)->format('m/Y') }}</td>
                                <td>{{ $s->platform }}</td>
                                <td class="text-end">{{ number_format($s->total_budget) }}</td>
                                <td class="text-end">{{ number_format($s->total_spent) }}</td>
                                <td class="text-end">{{ $p }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Chưa có dữ liệu</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- LỌC --}}
    <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label small">Từ ngày</label>
                <input type="text" name="from" value="{{ $from ?? '' }}" class="form-control" placeholder="dd/mm/yyyy">
            </div>

            <div class="col-md-3">
                <label class="form-label small">Đến ngày</label>
                <input type="text" name="to" value="{{ $to ?? '' }}" class="form-control" placeholder="dd/mm/yyyy">
            </div>

            <div class="col-md-3">
                <label class="form-label small">Kênh</label>
                <select name="platform" class="form-select">
                    <option value="">-- Tất cả --</option>
                    @foreach(['Facebook','Google','TikTok','Zalo','Khác'] as $p)
                        <option value="{{ $p }}" {{ ($platform ?? '')==$p?'selected':'' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small">Chiến dịch (từ kho campaign)</label>
                <select name="campaign_id" class="form-select">
                    <option value="">-- Tất cả --</option>
                    @foreach($campaigns as $c)
                        <option value="{{ $c->id }}" {{ (string)($campaign_id ?? '') === (string)$c->id ? 'selected':'' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-outline-secondary w-100">Lọc</button>
                <a href="{{ route('marketing.budget') }}" class="btn btn-light w-100">Xóa</a>
            </div>

            {{-- legacy month (nếu còn link cũ), không cần hiển thị --}}
            @if(request()->filled('month'))
                <input type="hidden" name="month" value="{{ request('month') }}">
            @endif
        </form>

        <div class="text-muted small mt-2">
            Định dạng: <b>dd/mm/yyyy</b>. Nếu không nhập sẽ mặc định tính <b>cả tháng hiện tại</b>.
        </div>
    </div>
</div>


    {{-- BẢNG NGÂN SÁCH --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Danh sách ngân sách</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tháng</th>
                        <th>Kênh</th>
                        <th>Chiến dịch</th>
                        <th class="text-end">Ngân sách</th>
                        <th class="text-end">Đã chi</th>
                        @hasanyrole('marketing_manager|admin')
                            <th class="text-end">Thao tác</th>
                        @endhasanyrole
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ optional($r->month)->format('m/Y') }}</td>
                            <td>{{ $r->platform }}</td>
                            <td>{{ $r->marketingCampaign?->name ?? '-' }}</td>
                            <td class="text-end">{{ number_format($r->budget) }}</td>
                            <td class="text-end">{{ number_format($r->actual_spent) }}</td>

                            @hasanyrole('marketing_manager|admin')
                                <td class="text-end">
                                    <a href="{{ route('marketing.budget.edit',$r->id) }}" class="btn btn-sm btn-outline-primary">Sửa</a>

                                    <form action="{{ route('marketing.budget.destroy', $r->id) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Xóa dòng ngân sách này?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Xóa</button>
                                    </form>
                                </td>
                            @endhasanyrole
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()?->hasAnyRole('marketing_manager|admin') ? 6 : 5 }}" class="text-center text-muted py-3">Chưa có dữ liệu</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $rows->links() }}</div>
    </div>

    {{-- CHỈ SỐ GẦN ĐÂY --}}
    @php
        $formatBreakdown = function($arr){
            if(!$arr || !is_array($arr) || count($arr)===0) return '-';
            $pairs = [];
            foreach($arr as $k=>$v){
                if((int)$v > 0) $pairs[] = $k.':'.number_format($v);
            }
            return count($pairs) ? implode(', ', $pairs) : '-';
        };
    @endphp

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold">Chỉ số marketing (gần đây)</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ngày</th>
                        <th>Kênh</th>
                        <th>Chiến dịch</th>
                        <th class="text-end">Chi</th>
                        <th class="text-end">Reach</th>
                        <th class="text-end">Lead</th>
                        <th>Giới tính</th>
                        <th>Độ tuổi</th>
                        <th>Khu vực</th>
                        <th class="text-end" style="width:160px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($metricRows as $m)
                        <tr>
                            <td>
                                {{ $m->date_from ? \Illuminate\Support\Carbon::parse($m->date_from)->format('d/m') : '' }}
                                @if($m->date_to) - {{ \Illuminate\Support\Carbon::parse($m->date_to)->format('d/m') }} @endif
                            </td>
                            <td>{{ $m->platform }}</td>

                            {{-- ✅ FIX: đúng campaign theo từng dòng metrics --}}
                            <td>{{ $m->campaign?->name ?? '-' }}</td>

                            <td class="text-end">{{ number_format($m->spend ?? 0) }}</td>
                            <td class="text-end">{{ number_format($m->reach ?? 0) }}</td>
                            <td class="text-end">{{ number_format($m->leads ?? 0) }}</td>

                            <td>{{ $formatBreakdown($m->gender_breakdown) }}</td>
                            <td>{{ $formatBreakdown($m->age_breakdown) }}</td>
                            <td>{{ $formatBreakdown($m->region_breakdown) }}</td>

                            <td class="text-end">
                                @hasanyrole('marketing_manager|admin')
                                    <a href="{{ route('marketing.metrics.edit', $m->id) }}" class="btn btn-sm btn-outline-primary">Sửa</a>

                                    <form action="{{ route('marketing.metrics.destroy', $m->id) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Xóa chỉ số này nhé?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Xóa</button>
                                    </form>
                                @endhasanyrole
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-3">Chưa có dữ liệu</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- CAMPAIGN TỔNG HỢP --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold">Campaign tổng hợp (Ngân sách + Chỉ số)</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tháng</th>
                        <th>Kênh</th>
                        <th>Chiến dịch</th>
                        <th class="text-end">Ngân sách</th>
                        <th class="text-end">Đã chi (NS)</th>
                        <th class="text-end">Chi tiêu (chỉ số)</th>
                        <th class="text-end">Reach</th>
                        <th class="text-end">Lead</th>
                    </tr>
                </thead>
                <tbody>
    @php($campaignCombined = $campaignCombined ?? collect())

    @forelse($campaignCombined as $c)
        <tr>
            <td>
                @if($month)
                    {{ \Illuminate\Support\Carbon::parse($c->month)->format('m/Y') }}
                @else
                    {{ $c->month }}
                @endif
            </td>
            <td>{{ $c->platform }}</td>
            <td>{{ $c->campaign_name ?? '-' }}</td>
            <td class="text-end">{{ number_format($c->budget ?? 0) }}</td>
            <td class="text-end">{{ number_format($c->budget_spent ?? 0) }}</td>
            <td class="text-end">{{ number_format($c->spend ?? 0) }}</td>
            <td class="text-end">{{ number_format($c->reach ?? 0) }}</td>
            <td class="text-end">{{ number_format($c->leads ?? 0) }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-3">Chưa có dữ liệu</td></tr>
    @endforelse
</tbody>
            </table>
        </div>
    </div>

</div>

{{-- MODAL: Ngân sách + Chỉ số + Chiến dịch --}}
<div class="modal fade" id="adsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0">Thêm dữ liệu Marketing</h5>
                    <small class="text-muted">Chọn tab Ngân sách hoặc Chỉ số hoặc Chiến dịch</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabBudget" type="button" role="tab">
                            Ngân sách
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMetric" type="button" role="tab">
                            Chỉ số
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCampaign" type="button" role="tab">
                            Chiến dịch
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-3">

                    {{-- TAB: NGÂN SÁCH --}}
                    <div class="tab-pane fade show active" id="tabBudget" role="tabpanel">
                        <form method="POST" action="{{ route('marketing.budget.store') }}" class="row g-2">
                            @csrf

                            <div class="col-md-3">
                                <label class="form-label small text-muted">Tháng</label>
                                <input type="month" name="month" class="form-control" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small text-muted">Kênh</label>
                                <select name="platform" class="form-select" required>
                                    @foreach(['Facebook','Google','TikTok','Zalo','Khác'] as $p)
                                        <option value="{{ $p }}">{{ $p }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small text-muted">Chiến dịch</label>
                                <select name="campaign_id" class="form-select" required>
                                    <option value="">-- Chọn chiến dịch --</option>
                                    @foreach($campaigns as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small text-muted">Ngân sách (đ)</label>
                                <input type="number" name="budget" class="form-control" min="0" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small text-muted">Đã chi (đ)</label>
                                <input type="number" name="actual_spent" class="form-control" min="0" value="0">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small text-muted">Ghi chú</label>
                                <input type="text" name="note" class="form-control" placeholder="Tuỳ chọn">
                            </div>

                            <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                                <button class="btn btn-success">Lưu ngân sách</button>
                            </div>
                        </form>
                    </div>

                    {{-- TAB: CHỈ SỐ --}}
                    <div class="tab-pane fade" id="tabMetric" role="tabpanel">
                        <form method="POST" action="{{ route('marketing.metrics.store') }}" class="row g-2">
                            @csrf

                            <div class="col-md-3">
                                <label class="form-label small text-muted">Từ ngày</label>
                                <input type="date" name="date_from" class="form-control" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small text-muted">Đến ngày</label>
                                <input type="date" name="date_to" class="form-control" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small text-muted">Kênh</label>
                                <select name="platform" class="form-select" required>
                                    @foreach(['Facebook','Google','TikTok','Zalo','Khác'] as $p)
                                        <option value="{{ $p }}">{{ $p }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small text-muted">Chiến dịch</label>
                                <select name="campaign_id" class="form-select" required>
                                    <option value="">-- Chọn chiến dịch --</option>
                                    @foreach($campaigns as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small text-muted">Reach (tiếp cận)</label>
                                <input type="number" name="reach" class="form-control" min="0" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small text-muted">Số lead</label>
                                <input type="number" name="leads" class="form-control" min="0" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small text-muted">Chi tiêu (nếu có)</label>
                                <input type="number" name="spend" class="form-control" min="0" value="0">
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted">Ghi chú</label>
                                <input type="text" name="note" class="form-control" placeholder="Tuỳ chọn">
                            </div>

                            <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                                <button class="btn btn-primary">Lưu chỉ số</button>
                            </div>
                        </form>
                    </div>

                    {{-- TAB: CHIẾN DỊCH --}}
                    <div class="tab-pane fade" id="tabCampaign" role="tabpanel">
                        <form method="POST" action="{{ route('marketing.campaigns.store') }}" class="row g-2">
                            @csrf

                            <div class="col-md-6">
                                <label class="form-label small text-muted">Tên chiến dịch</label>
                                <input type="text" name="name" class="form-control" placeholder="VD: Goodwe 5kw" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small text-muted">Kênh</label>
                                <select name="platform" class="form-select" required>
                                    @foreach(['Facebook','Google','TikTok','Zalo','Khác'] as $p)
                                        <option value="{{ $p }}">{{ $p }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted">Ghi chú</label>
                                <input type="text" name="note" class="form-control" placeholder="Tuỳ chọn">
                            </div>

                            <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                                <button class="btn btn-primary">Tạo chiến dịch</button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@endsection
