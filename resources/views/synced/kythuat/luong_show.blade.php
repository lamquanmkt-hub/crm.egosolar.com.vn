@extends('layouts.app')

@section('content')
@php
    $isAdmin = auth()->user()->hasRole('admin');
    $kpiPercent = (float)($payroll->total_kpi_percent ?? 0) * 100;
    $status = $payroll->status ?? 'draft';
    $groups = collect($slipFields ?? [])->groupBy('group_key');
    $values = collect($slipValues ?? []);
    $money = fn($v) => number_format((float)$v, 0, ',', '.') . ' đ';
    $displayValue = function($field) use ($values, $money) {
        $v = optional($values->get($field->id))->value;
        return match($field->field_type ?? 'money') {
            'money' => $money($v),
            'percent' => number_format((float)$v, 1, ',', '.') . '%',
            'number' => number_format((float)$v, 2, ',', '.'),
            default => (string)($v ?? '—'),
        };
    };
@endphp

<style>
.payroll-slip-page{--bg:#f4f7fb;--panel:#fff;--line:#e4ecf5;--text:#0f172a;--muted:#64748b;--navy:#0b3b66;--blue:#0f6ea8;--cyan:#17a8c7;--green:#10a870;--amber:#e8a11b;--red:#e24b4b;background:var(--bg);min-height:100vh;padding-bottom:38px;color:var(--text);font-size:12px}
.slip-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:12px}.slip-head h1{font-size:24px;line-height:1.1;margin:3px 0 5px;font-weight:950;letter-spacing:-.03em}.slip-head p{margin:0;color:var(--muted)}.crumb{font-size:11px;color:#8090a4;font-weight:800}.head-actions{display:flex;gap:8px;flex-wrap:wrap}.btn-soft,.btn-main{height:36px;border-radius:11px;padding:0 13px;font-weight:900;font-size:11.5px;display:inline-flex;align-items:center;gap:7px;text-decoration:none}.btn-soft{background:#fff;border:1px solid var(--line);color:#17324d}.btn-main{background:#0c3f6b;border:1px solid #0c3f6b;color:#fff}.status{padding:5px 9px;border-radius:999px;font-size:10px;font-weight:950}.status.draft{background:#fff3d6;color:#9a6500}.status.approved{background:#dcf8ea;color:#0b7a50}
.hero{background:linear-gradient(135deg,#0a2742 0%,#0b4f79 58%,#0a7e83 100%);color:#fff;border-radius:20px;padding:18px 20px;display:grid;grid-template-columns:1.4fr .8fr;gap:18px;align-items:center;box-shadow:0 18px 48px rgba(15,48,77,.16);margin-bottom:12px}.hero small{color:#bde7ef;font-weight:900}.hero h2{font-size:26px;font-weight:950;margin:5px 0 5px}.hero p{margin:0;color:#d8e7f2}.net-box{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);border-radius:16px;padding:15px}.net-box span{display:block;color:#d7e7ef;font-size:10.5px;font-weight:850}.net-box strong{font-size:29px;line-height:1.1;display:block;margin-top:4px}
.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px}.metric{background:#fff;border:1px solid var(--line);border-radius:16px;padding:13px 14px;box-shadow:0 8px 25px rgba(15,23,42,.035)}.metric span{font-size:10px;color:#718096;font-weight:900;text-transform:uppercase}.metric strong{display:block;font-size:20px;margin-top:5px;font-weight:950}.metric.green strong{color:var(--green)}.metric.red strong{color:var(--red)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}.panel{background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden;box-shadow:0 9px 28px rgba(15,23,42,.04)}.panel-head{padding:13px 14px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.panel-head h3{font-size:14px;font-weight:950;margin:0 0 3px}.panel-head p{font-size:10.8px;color:var(--muted);margin:0}.field-list{padding:5px 14px}.field-row{display:grid;grid-template-columns:minmax(0,1fr) 180px;gap:12px;align-items:center;padding:10px 0;border-bottom:1px dashed #edf1f5}.field-row:last-child{border-bottom:0}.field-name strong{display:block;font-size:11.8px}.field-name small{color:#8b99aa}.field-value{text-align:right;font-weight:950}.field-input{height:34px;border:1px solid #dbe5ee;border-radius:9px;padding:0 9px;width:100%;font-size:11.5px;text-align:right}.field-input.text{text-align:left}.override-note{font-size:9.5px;color:#a06d00;margin-top:3px}.info-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:13px}.info-card{border:1px solid #edf1f5;background:#f9fbfd;border-radius:12px;padding:10px}.info-card span{display:block;color:#8492a5;font-size:9.8px;font-weight:850}.info-card strong{display:block;margin-top:4px;font-size:11.8px}
.kpi-table{width:100%;border-collapse:collapse}.kpi-table th{background:#0d2942;color:#fff;padding:9px 10px;font-size:10px;text-align:left;white-space:nowrap}.kpi-table td{padding:9px 10px;border-bottom:1px solid #edf1f5;font-size:10.8px}.kpi-table tr:last-child td{border-bottom:0}.savebar{display:flex;justify-content:flex-end;gap:8px;padding:12px 14px;border-top:1px solid var(--line);background:#fbfdff}.notice{padding:10px 12px;border-radius:12px;background:#eef8ff;border:1px solid #d8edf8;color:#2f5e79;font-size:10.5px;margin-bottom:12px}.empty{padding:18px;color:#8a98a8;text-align:center}.summary-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #edf1f5}.summary-row:last-child{border-bottom:0}
@media(max-width:1000px){.hero,.grid2{grid-template-columns:1fr}.metrics{grid-template-columns:repeat(2,1fr)}.info-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.slip-head{flex-direction:column}.metrics,.info-grid{grid-template-columns:1fr}.field-row{grid-template-columns:1fr}.field-value{text-align:left}.hero h2{font-size:22px}}
</style>

<div class="payroll-slip-page">
    <div class="container-fluid px-3 px-lg-4 py-3">
        <div class="slip-head">
            <div>
                <div class="crumb">Kỹ thuật &nbsp;›&nbsp; KPIs &nbsp;›&nbsp; Phiếu lương #{{ $payroll->id }}</div>
                <h1>Phiếu lương kỹ thuật</h1>
                <p>Chi tiết kỳ lương, KPI và các khoản thu nhập/khấu trừ của nhân sự.</p>
            </div>
            <div class="head-actions">
                <a class="btn-soft" href="{{ url('/ky-thuat/kpis') }}"><i class="fas fa-chart-line"></i> KPIs</a>
                @if($isAdmin)
                    <a class="btn-soft" href="{{ route('ky-thuat.luong.slip-settings') }}"><i class="fas fa-sliders-h"></i> Cấu hình phiếu lương</a>
                    <a class="btn-main" href="{{ route('ky-thuat.luong.edit', $payroll->id) }}"><i class="fas fa-pen"></i> Sửa KPI / lương</a>
                @endif
            </div>
        </div>

        @if(session('success'))<div class="notice"><b>✓</b> {{ session('success') }}</div>@endif
        @if(session('error'))<div class="notice" style="background:#fff1f1;border-color:#ffd7d7;color:#a83d3d"><b>!</b> {{ session('error') }}</div>@endif

        <div class="hero">
            <div>
                <small>PHIẾU LƯƠNG • {{ $payroll->payroll_month ?? $payroll->month_label ?? '' }}</small>
                <h2>{{ $payroll->employee_name ?? 'Nhân viên kỹ thuật' }}</h2>
                <p>{{ $payroll->position_name ?? 'Kỹ thuật' }} &nbsp;•&nbsp; KPI {{ number_format($kpiPercent,1,',','.') }}% &nbsp;•&nbsp; <span class="status {{ $status }}">{{ $status === 'approved' ? 'Đã duyệt' : 'Chờ duyệt' }}</span></p>
            </div>
            <div class="net-box">
                <span>THỰC NHẬN THEO CẤU HÌNH</span>
                <strong>{{ $money($slipTotals['net'] ?? ($payroll->total_income ?? 0)) }}</strong>
            </div>
        </div>

        <div class="metrics">
            <div class="metric"><span>Lương thỏa thuận</span><strong>{{ $money($payroll->gross_salary ?? 0) }}</strong></div>
            <div class="metric"><span>Lương cố định</span><strong>{{ $money($payroll->base_salary ?? 0) }}</strong></div>
            <div class="metric green"><span>Lương KPI</span><strong>{{ $money($payroll->real_kpi_salary ?? 0) }}</strong></div>
            <div class="metric red"><span>Tổng khấu trừ</span><strong>{{ $money($slipTotals['deduction'] ?? 0) }}</strong></div>
        </div>

        @if(($groups->get('info') ?? collect())->count())
        <div class="panel" style="margin-bottom:12px">
            <div class="panel-head"><div><h3>Thông tin phiếu lương</h3><p>Các trường hiển thị được quản trị viên cấu hình.</p></div></div>
            <div class="info-grid">
                @foreach($groups->get('info', collect()) as $field)
                    <div class="info-card"><span>{{ $field->label }}</span><strong>{{ $displayValue($field) }}</strong></div>
                @endforeach
            </div>
        </div>
        @endif

        @if($isAdmin && ($slipFields ?? collect())->count())
        <form method="POST" action="{{ route('ky-thuat.luong.slip-values.save', $payroll->id) }}">
            @csrf
        @endif

        <div class="grid2">
            <div class="panel">
                <div class="panel-head"><div><h3>Thu nhập</h3><p>Các khoản cộng vào thực nhận.</p></div><strong>{{ $money($slipTotals['income'] ?? 0) }}</strong></div>
                <div class="field-list">
                    @forelse($groups->get('income', collect()) as $field)
                        @php $entry = $values->get($field->id); $current = optional($entry)->value; @endphp
                        <div class="field-row">
                            <div class="field-name"><strong>{{ $field->label }}</strong><small>{{ $field->note ?: ($field->source_column ? 'Nguồn hệ thống: '.$field->source_column : 'Nhập tay') }}</small></div>
                            <div class="field-value">
                                @if($isAdmin)
                                    <input class="field-input" type="{{ $field->field_type === 'text' ? 'text' : 'number' }}" step="0.01" name="values[{{ $field->id }}]" value="{{ $current }}" placeholder="{{ $displayValue($field) }}">
                                    @if(optional($entry)->is_override)<div class="override-note">Đang dùng giá trị Admin ghi đè</div>@endif
                                @else
                                    {{ $displayValue($field) }}
                                @endif
                            </div>
                        </div>
                    @empty <div class="empty">Chưa cấu hình khoản thu nhập.</div> @endforelse
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><div><h3>Khấu trừ</h3><p>Các khoản trừ khỏi thực nhận.</p></div><strong>{{ $money($slipTotals['deduction'] ?? 0) }}</strong></div>
                <div class="field-list">
                    @forelse($groups->get('deduction', collect()) as $field)
                        @php $entry = $values->get($field->id); $current = optional($entry)->value; @endphp
                        <div class="field-row">
                            <div class="field-name"><strong>{{ $field->label }}</strong><small>{{ $field->note ?: ($field->source_column ? 'Nguồn hệ thống: '.$field->source_column : 'Nhập tay') }}</small></div>
                            <div class="field-value">
                                @if($isAdmin)
                                    <input class="field-input" type="{{ $field->field_type === 'text' ? 'text' : 'number' }}" step="0.01" name="values[{{ $field->id }}]" value="{{ $current }}" placeholder="{{ $displayValue($field) }}">
                                    @if(optional($entry)->is_override)<div class="override-note">Đang dùng giá trị Admin ghi đè</div>@endif
                                @else
                                    {{ $displayValue($field) }}
                                @endif
                            </div>
                        </div>
                    @empty <div class="empty">Chưa cấu hình khoản khấu trừ.</div> @endforelse
                </div>
                @if($isAdmin)
                    <div class="savebar"><small style="color:#7d8b9a;margin-right:auto;align-self:center">Để trống ô rồi lưu = quay về giá trị hệ thống/mặc định.</small><button class="btn-main" type="submit"><i class="fas fa-save"></i> Lưu phiếu lương</button></div>
                @endif
            </div>
        </div>

        @if($isAdmin && ($slipFields ?? collect())->count())</form>@endif

        <div class="grid2">
            <div class="panel">
                <div class="panel-head"><div><h3>Chi tiết KPI</h3><p>Các tiêu chí đã dùng để tính KPI kỳ này.</p></div><strong>{{ number_format($kpiPercent,1,',','.') }}%</strong></div>
                <div style="overflow:auto">
                    <table class="kpi-table">
                        <thead><tr><th>#</th><th>Tiêu chí</th><th>KH</th><th>TH</th><th>Trọng số</th><th>Kết quả</th></tr></thead>
                        <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><b>{{ $item->kpi_name ?? 'KPI' }}</b></td>
                                <td>{{ $item->plan_value ?? '—' }}</td>
                                <td>{{ $item->actual_value ?? '—' }}</td>
                                <td>{{ number_format(((float)($item->weight ?? 0))*100,0) }}%</td>
                                <td><b>{{ number_format(((float)($item->final_rate ?? $item->result_rate ?? 0))*100,1,',','.') }}%</b></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">Chưa có chi tiết KPI.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><div><h3>Tổng hợp</h3><p>Các thông tin bổ sung trên phiếu.</p></div></div>
                <div class="field-list">
                    <div class="summary-row"><span>Tổng thu nhập</span><b>{{ $money($slipTotals['income'] ?? 0) }}</b></div>
                    <div class="summary-row"><span>Tổng khấu trừ</span><b style="color:#d84b4b">- {{ $money($slipTotals['deduction'] ?? 0) }}</b></div>
                    <div class="summary-row"><span>Thực nhận</span><b style="color:#0b8b5c;font-size:15px">{{ $money($slipTotals['net'] ?? 0) }}</b></div>
                    @foreach($groups->get('summary', collect()) as $field)
                        <div class="summary-row"><span>{{ $field->label }}</span><b>{{ $displayValue($field) }}</b></div>
                    @endforeach
                </div>
                @if($status !== 'approved' && auth()->user()->hasAnyRole(['admin','accounting','manager']))
                    <div class="savebar">
                        <form method="POST" action="{{ route('ky-thuat.luong.approve', $payroll->id) }}">@csrf<button class="btn-main" type="submit"><i class="fas fa-check"></i> Duyệt phiếu lương</button></form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
