@extends('layouts.app')

@section('title', 'Sự cố văn phòng')

@section('content')
@use('App\Services\Hr\OfficeIncidents')
@php
    $vnd = static fn ($n) => number_format((float) $n, 0, ',', '.');
    $tone = ['new' => 'warning', 'waiting_approval' => 'warning', 'in_progress' => 'info', 'waiting_acceptance' => 'info', 'closed' => 'success', 'cancelled' => 'muted'];
@endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-tools"></i></div>
                <div>
                    <span>NHÂN SỰ · HÀNH CHÍNH</span>
                    <h1>Sự cố văn phòng</h1>
                    <p>Báo hỏng hóc, sự cố trong văn phòng kèm ảnh. Hành chính Nhân sự phân loại, xử lý theo hạn; bạn kiểm tra và chấm sao khi xong.</p>
                </div>
            </div>
        </header>

        @include('hr.incidents._style')

        <section class="at-panel ic-body">
            <div class="at-card-head"><div><h2><i class="bi bi-megaphone"></i>Báo sự cố mới</h2></div></div>
            <form method="POST" action="{{ route('hr.incidents.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="ic-grid">
                    <div class="ic-field">
                        <label>Vị trí sự cố *</label>
                        <input class="ic-input" name="location" maxlength="255" required value="{{ old('location') }}" placeholder="VD: Phòng họp tầng 2, góc cửa sổ">
                    </div>
                    <div class="ic-field" style="grid-column:span 2">
                        <label>Mô tả hiện tượng *</label>
                        <textarea class="ic-input" name="description" maxlength="3000" required placeholder="VD: Điều hoà chảy nước xuống sàn từ sáng nay">{{ old('description') }}</textarea>
                    </div>
                    <div class="ic-field" style="grid-column:span 3">
                        <label>Ảnh / video thực tế (tối đa 6 file, mỗi file 20MB)</label>
                        <input class="ic-input" type="file" name="files[]" multiple accept="image/*,video/mp4,video/quicktime,application/pdf">
                    </div>
                </div>
                <p class="ic-hint">Trường hợp khẩn cấp (mất điện, mất mạng, ngập nước, kẹt cửa) vẫn báo ở đây và nhắn trực tiếp cho Hành chính Nhân sự.</p>
                <button class="at-btn at-btn--primary" type="submit" style="margin-top:10px"><i class="bi bi-send"></i>Gửi báo sự cố</button>
            </form>
        </section>

        @if($stats)
            <section class="at-panel ic-body ic-section">
                <form method="GET" class="ic-filter">
                    <div class="ic-field"><label>Tháng</label><input class="ic-input" type="month" name="month" value="{{ $month }}"></div>
                    <div class="ic-field">
                        <label>Trạng thái</label>
                        <select class="ic-input" name="status">
                            <option value="">Đang mở + trong tháng</option>
                            @foreach(OfficeIncidents::STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-funnel"></i>Lọc</button>
                </form>
                <div class="ic-kpis ic-section">
                    <div><span class="ic-muted">ĐÚNG HẠN THÁNG {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('m/Y') }}</span><strong>{{ $stats['percent'] !== null ? rtrim(rtrim(number_format($stats['percent'], 2, ',', '.'), '0'), ',').'%' : '—' }}</strong></div>
                    <div><span class="ic-muted">ĐÚNG HẠN / TỔNG</span><strong>{{ $stats['on_time'] }} / {{ $stats['total'] }}</strong></div>
                    <div><span class="ic-muted">QUÁ HẠN CHƯA XONG</span><strong class="{{ $stats['late_open'] ? 'ic-late' : '' }}">{{ $stats['late_open'] }}</strong></div>
                </div>
                <p class="ic-hint">Số liệu này dùng cho KPI HCNS — tiêu chí "Quản lý tài sản & văn phòng phẩm" (chỉ tiêu ≥ 95% đúng hạn). Hạn xử lý tính từ lúc nhân viên báo.</p>
            </section>
        @endif

        <section class="at-panel ic-section">
            <div class="at-table-wrap">
                <table class="at-table" style="min-width:980px">
                    <thead>
                        <tr><th>Mã</th><th>Vị trí · Mô tả</th>@if($seeAll)<th>Người báo</th>@endif<th>Mức</th><th>Hạn xử lý</th><th>Trạng thái</th><th style="text-align:right">Chi phí</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($incidents as $i)
                            @php $late = $i->due_at && ! in_array($i->status, ['waiting_acceptance', 'closed', 'cancelled'], true) && now()->gt($i->due_at); @endphp
                            <tr>
                                <td><strong>{{ $i->code }}</strong><div class="ic-muted">{{ \Illuminate\Support\Carbon::parse($i->created_at)->format('d/m H:i') }}</div></td>
                                <td><strong>{{ $i->location }}</strong><div class="ic-muted" style="max-width:340px;white-space:normal">{{ \Illuminate\Support\Str::limit($i->description, 110) }}</div></td>
                                @if($seeAll)<td>{{ $i->reporter_name }}</td>@endif
                                <td>{{ $i->level ? 'Mức '.$i->level : '—' }}</td>
                                <td>
                                    @if($i->due_at)
                                        <span class="{{ $late ? 'ic-late' : '' }}">{{ \Illuminate\Support\Carbon::parse($i->due_at)->format('H:i d/m') }}</span>
                                        @if($late)<div class="ic-muted ic-late">Quá hạn</div>@endif
                                        @if($i->status === 'closed' && $i->on_time !== null)<div class="ic-muted {{ $i->on_time ? 'ic-ok' : 'ic-late' }}">{{ $i->on_time ? 'Đúng hạn' : 'Trễ hạn' }}</div>@endif
                                    @else — @endif
                                </td>
                                <td><span class="at-status-pill at-status-pill--{{ $tone[$i->status] ?? 'muted' }}">{{ OfficeIncidents::STATUSES[$i->status] ?? $i->status }}</span>
                                    @if($i->rating)<div class="ic-muted">{{ str_repeat('★', (int) $i->rating) }}</div>@endif
                                </td>
                                <td style="text-align:right">{{ $i->actual_cost !== null ? $vnd($i->actual_cost) : ((float) $i->estimated_cost > 0 ? '~'.$vnd($i->estimated_cost) : '—') }}</td>
                                <td><a class="at-btn at-btn--primary" style="min-height:30px" href="{{ route('hr.incidents.show', $i->id) }}"><i class="bi bi-eye"></i>Mở</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="at-empty">Chưa có sự cố nào.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($canApprove)
            <section class="at-panel ic-body ic-section">
                <details>
                    <summary style="cursor:pointer;font-weight:800;color:#087e92"><i class="bi bi-sliders"></i> Cài đặt sự cố văn phòng (Ban Giám đốc)</summary>
                    <form method="POST" action="{{ route('hr.incidents.settings.save') }}" class="ic-section">
                        @csrf
                        <div class="ic-grid">
                            @foreach(OfficeIncidents::LABELS as $key => $label)
                                <div class="ic-field"><label>{{ $label }}</label><input class="ic-input" type="number" step="any" min="0" name="{{ $key }}" value="{{ $settings[$key] + 0 }}" required></div>
                            @endforeach
                        </div>
                        <button class="at-btn at-btn--primary ic-section" type="submit"><i class="bi bi-check2"></i>Lưu cài đặt</button>
                    </form>
                </details>
            </section>
        @endif
    </div>
</div>
@endsection
