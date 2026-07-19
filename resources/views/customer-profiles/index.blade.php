@extends('layouts.app')

@section('content')
@include('customer-profiles._style')
<div class="cp-page">
    <div class="cp-head">
        <div>
            <h1 class="cp-title">Hồ sơ khách hàng / đại lý</h1>
            <div class="cp-sub">Quản lý tên đại lý, cấp đại lý, tiền đặt cọc, trạng thái và toàn bộ giấy tờ liên quan.</div>
        </div>
        <div class="cp-actions">
            <a class="cp-btn ok" href="{{ route('customers.index') }}">☑ Chọn khách hàng làm đại lý</a>
            <a class="cp-btn" href="{{ route('customer-profiles.export', request()->query()) }}">⬇ Xuất CSV</a>
            <a class="cp-btn primary" href="{{ route('customer-profiles.create') }}">+ Thêm hồ sơ</a>
        </div>
    </div>

    
@include('customer-profiles._messages')

    <div class="cp-grid-kpi">
        <div class="cp-kpi"><div class="label">Tổng hồ sơ</div><div class="value">{{ number_format($summary['total']) }}</div><div class="hint">Đại lý/khách hàng</div></div>
        <div class="cp-kpi"><div class="label">Đang hợp tác</div><div class="value">{{ number_format($summary['active']) }}</div><div class="hint">Trạng thái active</div></div>
        <div class="cp-kpi"><div class="label">Đã đặt cọc</div><div class="value">{{ number_format($summary['deposited']) }}</div><div class="hint">Đã cọc/đang hợp tác</div></div>
        <div class="cp-kpi"><div class="label">Tổng tiền cọc</div><div class="value">{{ number_format($summary['deposit_total'], 0, ',', '.') }} đ</div><div class="hint">Theo hồ sơ</div></div>
        <div class="cp-kpi"><div class="label">Giấy tờ</div><div class="value">{{ number_format($summary['documents']) }}</div><div class="hint">File đã upload</div></div>
    </div>

    <div class="cp-card">
        <div class="cp-card-head"><div class="cp-card-title">Bộ lọc nhanh</div></div>
        <div class="cp-card-body">
            <form class="cp-filter" method="GET" action="{{ route('customer-profiles.index') }}">
                <div class="cp-field"><label>Tìm kiếm</label><input class="cp-input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tên đại lý, khách hàng, SĐT, MST, mã HĐ..."></div>
                <div class="cp-field"><label>Trạng thái</label><select class="cp-select" name="status"><option value="">-- Tất cả --</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                <div class="cp-field"><label>Cấp đại lý</label><select class="cp-select" name="agent_level"><option value="">-- Tất cả --</option>@foreach($levels as $level)<option value="{{ $level }}" {{ ($filters['agent_level'] ?? '') === $level ? 'selected' : '' }}>{{ $level }}</option>@endforeach</select></div>
                <div class="cp-field"><label>Từ ngày cọc</label><input class="cp-input" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
                <div class="cp-field"><label>Đến ngày cọc</label><input class="cp-input" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
                <div class="cp-field"><label>&nbsp;</label><button class="cp-btn primary" type="submit">Lọc</button></div>
            </form>
        </div>
    </div>

    <div class="cp-card">
        <div class="cp-card-head">
            <div class="cp-card-title">Danh sách hồ sơ</div>
            <div class="cp-small cp-muted">{{ $profiles->total() }} kết quả</div>
        </div>
        <div class="cp-table-wrap">
            <table class="cp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Khách hàng / Đại lý</th>
                        <th>Cấp đại lý</th>
                        <th>Tiền đặt cọc</th>
                        <th>Ngày đặt cọc</th>
                        <th>Trạng thái</th>
                        <th>Giấy tờ</th>
                        <th>Liên hệ</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($profiles as $profile)
                        <tr>
                            <td>{{ $profile->id }}</td>
                            <td>
                                <div class="cp-name">{{ $profile->agent_name }}</div>
                                <div class="cp-small cp-muted">{{ $profile->customer_name ?: 'Chưa gắn khách hàng' }}</div>
                                @if($profile->contract_code)<div class="cp-small">Mã HĐ: <b>{{ $profile->contract_code }}</b></div>@endif
                            </td>
                            <td><span class="cp-badge">{{ $profile->agent_level ?: ($profile->price_tier_name ?: 'Chưa phân cấp') }}</span></td>
                            <td><span class="cp-money">{{ number_format((float) $profile->deposit_amount, 0, ',', '.') }} đ</span></td>
                            <td>{{ optional($profile->deposit_date)->format('d/m/Y') ?: '—' }}</td>
                            <td><span class="cp-badge {{ $profile->status }}">{{ $statuses[$profile->status] ?? $profile->status }}</span></td>
                            <td>{{ $profile->documents_count }} file</td>
                            <td><div>{{ $profile->phone ?: '—' }}</div><div class="cp-small cp-muted">{{ $profile->email }}</div></td>
                            <td>
                                <div class="cp-actions" style="justify-content:flex-start">
                                    <a class="cp-btn" href="{{ route('customer-profiles.show', $profile) }}">Xem</a>
                                    <a class="cp-btn" href="{{ route('customer-profiles.edit', $profile) }}">Sửa</a>
                                    <form method="POST" action="{{ route('customer-profiles.destroy', $profile) }}" onsubmit="return confirm('Xóa hồ sơ này?')">
                                        @csrf @method('DELETE')
                                        <button class="cp-btn danger" type="submit">Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center;padding:28px;color:#64748b">Chưa có hồ sơ. Vào Danh sách KH, tích chọn khách hàng rồi bấm “Chuyển sang hồ sơ đại lý”.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="cp-card-body">{{ $profiles->links() }}</div>
    </div>
</div>
@endsection
