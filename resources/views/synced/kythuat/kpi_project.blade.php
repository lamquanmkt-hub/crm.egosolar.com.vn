@extends('layouts.app')

@section('title', 'KPI công trình · EGO Solar')

@section('content')
@php
    $projectCode = $site->project_code ?: ('DA-SITE-'.str_pad((string)$site->id, 5, '0', STR_PAD_LEFT));
    $projectName = $site->name ?: ('Công trình #'.$site->id);
@endphp
<style>
.pkpi{min-height:100vh;background:#f4f7fb;padding:20px}.pkpi-shell{max-width:1500px;margin:auto}.pkpi-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px}.pkpi-bc{font-size:10px;font-weight:850;color:#718298;margin-bottom:6px}.pkpi h1{font-size:25px;font-weight:950;margin:0;color:#10253d;letter-spacing:-.03em}.pkpi-sub{font-size:11px;color:#728196;margin-top:5px}.pkpi-actions{display:flex;gap:8px;flex-wrap:wrap}.pkpi-btn{height:37px;border:1px solid #d8e4ef;background:#fff;color:#27445e;border-radius:10px;padding:0 13px;display:inline-flex;align-items:center;gap:7px;text-decoration:none;font-size:11px;font-weight:900}.pkpi-btn.primary{background:#0d355c;border-color:#0d355c;color:#fff}.pkpi-card{background:#fff;border:1px solid #e3ebf3;border-radius:15px;box-shadow:0 10px 28px rgba(15,23,42,.04);margin-bottom:12px;overflow:hidden}.pkpi-summary{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;padding:12px}.pkpi-stat{padding:11px 12px;border:1px solid #e8eef5;border-radius:12px;background:#fbfdff}.pkpi-stat span{font-size:9.5px;color:#708298;font-weight:850;text-transform:uppercase}.pkpi-stat strong{display:block;margin-top:5px;font-size:16px;color:#173b59}.pkpi-table{width:100%;border-collapse:collapse;font-size:10.5px}.pkpi-table th{background:#f7fafc;color:#5a7086;text-align:left;padding:9px;border-bottom:1px solid #e5edf4;font-size:9px;text-transform:uppercase}.pkpi-table td{padding:9px;border-bottom:1px solid #edf2f7;vertical-align:top}.pkpi-control{width:100%;height:34px;border:1px solid #d8e4ef;border-radius:9px;padding:0 9px;background:#fff;color:#183751;font-size:10.5px;font-weight:750}.pkpi-textarea{height:58px;padding:8px;resize:vertical}.pkpi-person strong{display:block;color:#12324d;font-size:11.5px}.pkpi-person small{display:block;color:#8390a0;margin-top:3px}.pkpi-auto{font-size:10px;color:#48647e;line-height:1.45}.pkpi-auto .ok{color:#15803d;font-weight:850}.pkpi-auto .bad{color:#c2410c;font-weight:850}.pkpi-badge{display:inline-flex;align-items:center;padding:4px 7px;border-radius:999px;background:#edf6ff;color:#2563a2;font-size:9px;font-weight:850}.pkpi-note{padding:10px 12px;background:#f8fbff;border-top:1px solid #e8eef5;color:#6d7e90;font-size:10px}.pkpi-alert{padding:9px 12px;border-radius:10px;margin-bottom:10px;background:#ecfdf5;color:#166534;font-size:11px;font-weight:800}.pkpi-empty{padding:28px;text-align:center;color:#8090a0}.pkpi-footer{display:flex;justify-content:flex-end;padding:12px;border-top:1px solid #e8eef5}.pkpi-checkbox{display:flex;align-items:center;gap:6px;font-size:10px;font-weight:800;color:#51677c;margin-top:6px}@media(max-width:1000px){.pkpi-summary{grid-template-columns:1fr 1fr}.pkpi-card{overflow:auto}.pkpi-table{min-width:1100px}}@media(max-width:600px){.pkpi{padding:12px}.pkpi-head{flex-direction:column}.pkpi-summary{grid-template-columns:1fr}}
</style>

<div class="pkpi">
<div class="pkpi-shell">
    <header class="pkpi-head">
        <div>
            <div class="pkpi-bc">Kỹ thuật › KPIs › Công trình</div>
            <h1>KPI công trình · {{ $projectCode }}</h1>
            <div class="pkpi-sub">{{ $projectName }} · Kỳ {{ $month }} · Dữ liệu tại đây là nguồn bằng chứng cho KPI tháng của kỹ sư.</div>
        </div>
        <div class="pkpi-actions">
            <a class="pkpi-btn" href="{{ route('projects-unified.show', $site) }}"><i class="bi bi-building"></i>Về công trình</a>
            <a class="pkpi-btn" href="{{ route('ky-thuat.kpis.index', ['month'=>$month]) }}"><i class="bi bi-bar-chart"></i>Dashboard KPI</a>
        </div>
    </header>

    @if(session('success'))<div class="pkpi-alert"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>@endif

    <section class="pkpi-card">
        <div class="pkpi-summary">
            <div class="pkpi-stat"><span>Công trình</span><strong>{{ $projectCode }}</strong></div>
            <div class="pkpi-stat"><span>Tiến độ</span><strong>{{ (int)($site->progress_percent ?? 0) }}%</strong></div>
            <div class="pkpi-stat"><span>Hạn hoàn thành</span><strong>{{ $site->target_completion_at ? \Illuminate\Support\Carbon::parse($site->target_completion_at)->format('d/m/Y') : '—' }}</strong></div>
            <div class="pkpi-stat"><span>Ngày hoàn thành</span><strong>{{ $site->completed_at ? \Illuminate\Support\Carbon::parse($site->completed_at)->format('d/m/Y') : '—' }}</strong></div>
            <div class="pkpi-stat"><span>Kỹ sư liên quan</span><strong>{{ $users->count() }}</strong></div>
        </div>
        <div class="pkpi-note"><i class="bi bi-link-45deg"></i> Tiến độ được đọc tự động từ workflow Công trình. Chất lượng, HSE, hao hụt vật tư và EVN/App được xác nhận tại màn hình này để tránh nhập lại ở bảng KPI tháng.</div>
    </section>

    <form method="POST" action="{{ route('ky-thuat.kpis.project.save', ['site'=>$site->id, 'month'=>$month]) }}" class="pkpi-card">
        @csrf
        <input type="hidden" name="month" value="{{ $month }}">
        @if($users->isEmpty())
            <div class="pkpi-empty">Chưa có kỹ sư được phân công cho công trình này.</div>
        @else
        <div style="overflow:auto">
        <table class="pkpi-table">
            <thead><tr><th>Kỹ sư</th><th>Tiến độ tự động</th><th>Chất lượng</th><th>Hao hụt vật tư</th><th>HSE</th><th>EVN / App</th><th>Điểm phạt</th><th>Ghi chú</th></tr></thead>
            <tbody>
            @foreach($users as $user)
                @php
                    $row = $evidence->get($user->id);
                    $signal = $signals[$user->id] ?? null;
                @endphp
                <tr>
                    <td class="pkpi-person"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small><span class="pkpi-badge">Kỹ sư #{{ $user->id }}</span></td>
                    <td>
                        <div class="pkpi-auto">
                            @if($signal)
                                <div>Hạn: <b>{{ $signal['deadline'] ?? '—' }}</b></div>
                                <div>Hoàn thành: <b>{{ $signal['completed'] ?? '—' }}</b></div>
                                @if(($signal['on_time'] ?? null) === true)<div class="ok">✓ Đúng hạn</div>@elseif(($signal['on_time'] ?? null) === false)<div class="bad">! Trễ hạn</div>@else<div>Chưa đủ dữ liệu</div>@endif
                            @else
                                <div>Chưa có hoạt động workflow trong kỳ.</div>
                            @endif
                        </div>
                        <label class="pkpi-checkbox"><input type="checkbox" name="evidence[{{ $user->id }}][timeline_excluded]" value="1" @checked((bool)($row->timeline_excluded ?? false)) @disabled(!$canManage)> Loại trừ tiến độ</label>
                        <input class="pkpi-control" style="margin-top:6px" type="text" name="evidence[{{ $user->id }}][timeline_exclusion_reason]" value="{{ $row->timeline_exclusion_reason ?? '' }}" placeholder="Lý do loại trừ" @disabled(!$canManage)>
                    </td>
                    <td><select class="pkpi-control" name="evidence[{{ $user->id }}][quality_first_pass]" @disabled(!$canManage)><option value="">Chưa xác nhận</option><option value="1" @selected(($row->quality_first_pass ?? null) === 1)>Đạt lần đầu</option><option value="0" @selected(($row->quality_first_pass ?? null) === 0)>Phải sửa / nghiệm thu lại</option></select></td>
                    <td><input class="pkpi-control" type="number" step="0.01" min="0" max="100" name="evidence[{{ $user->id }}][material_waste_percent]" value="{{ $row->material_waste_percent ?? '' }}" placeholder="% hao hụt" @disabled(!$canManage)></td>
                    <td><select class="pkpi-control" name="evidence[{{ $user->id }}][hse_pass]" @disabled(!$canManage)><option value="">Chưa xác nhận</option><option value="1" @selected(($row->hse_pass ?? null) === 1)>Đạt HSE</option><option value="0" @selected(($row->hse_pass ?? null) === 0)>Không đạt HSE</option></select></td>
                    <td>
                        <select class="pkpi-control" name="evidence[{{ $user->id }}][evn_app_required]" @disabled(!$canManage)><option value="">Chưa xác nhận</option><option value="0" @selected(($row->evn_app_required ?? null) === 0)>N/A · Không yêu cầu</option><option value="1" @selected(($row->evn_app_required ?? null) === 1)>Có yêu cầu</option></select>
                        <select class="pkpi-control" style="margin-top:6px" name="evidence[{{ $user->id }}][evn_app_completed]" @disabled(!$canManage)><option value="">Trạng thái</option><option value="1" @selected(($row->evn_app_completed ?? null) === 1)>Đã hoàn tất</option><option value="0" @selected(($row->evn_app_completed ?? null) === 0)>Chưa hoàn tất</option></select>
                    </td>
                    <td><select class="pkpi-control" name="evidence[{{ $user->id }}][penalty_points]" @disabled(!$canManage)><option value="0" @selected((float)($row->penalty_points ?? 0)===0.0)>0 điểm</option><option value="10" @selected((float)($row->penalty_points ?? 0)===10.0)>-10 điểm</option><option value="20" @selected((float)($row->penalty_points ?? 0)===20.0)>-20 điểm</option></select></td>
                    <td><textarea class="pkpi-control pkpi-textarea" name="evidence[{{ $user->id }}][note]" placeholder="Minh chứng / ghi chú" @disabled(!$canManage)>{{ $row->note ?? '' }}</textarea></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
        <div class="pkpi-footer">
            @if($canManage)<button class="pkpi-btn primary" type="submit"><i class="bi bi-save"></i>Lưu KPI công trình</button>@else<span class="pkpi-badge"><i class="bi bi-lock"></i> Chỉ Trưởng kỹ thuật / Quản lý / Admin được cập nhật</span>@endif
        </div>
    </form>
</div>
</div>
@endsection
