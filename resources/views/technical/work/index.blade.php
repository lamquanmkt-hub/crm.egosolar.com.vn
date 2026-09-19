@extends('layouts.app')

@section('title', 'Kỹ thuật · EGO Solar')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-business-flow-v5.css') }}?v={{ file_exists(public_path('css/ego-business-flow-v5.css')) ? filemtime(public_path('css/ego-business-flow-v5.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/technical-work-word-v1.css') }}?v={{ filemtime(public_path('css/technical-work-word-v1.css')) }}">
@endpush

@section('content')
@php
    $nav = [
        'overview'=>['label'=>'Tổng quan','route'=>'ky-thuat.tong-quan','icon'=>'bi-grid-1x2'],
        'plan'=>['label'=>'Kế hoạch','route'=>'ky-thuat.ke-hoach','icon'=>'bi-calendar2-check'],
        'report'=>['label'=>'Báo cáo','route'=>'ky-thuat.bao-cao','icon'=>'bi-journal-check'],
        'kpis'=>['label'=>'KPIs','route'=>'ky-thuat.kpis.index','icon'=>'bi-bar-chart-line'],
    ];
    $workTypeLabels = ['periodic'=>'Bảo trì định kỳ','survey'=>'Khảo sát','design'=>'Phương án / thiết kế','construction'=>'Thi công','acceptance'=>'Nghiệm thu','repair'=>'Sửa chữa / xử lý sự cố','other'=>'Công việc khác'];

    $pageMeta = [
        'overview' => [
            'title' => 'Module Kỹ thuật - Tổng quan',
            'subtitle' => 'Theo dõi nhanh kế hoạch, báo cáo và hiệu suất KPIs kỹ thuật.',
            'note' => 'Bấm bước nào ở cột trái thì khung bên phải chỉ hiện nội dung của bước đó.',
        ],
        'plan' => [
            'title' => '10. Trang Kế hoạch Kỹ thuật',
            'subtitle' => 'Kế hoạch là màn hình riêng; khi bấm Kế hoạch chỉ hiện nội dung của bước Kế hoạch.',
            'note' => 'Các trường chính bám theo form đang có: ngày thực hiện, loại công việc, ưu tiên, thiết bị, nội dung và ghi chú kỹ thuật.',
        ],
        'report' => [
            'title' => '11. Trang Báo cáo Kỹ thuật',
            'subtitle' => 'Nhân sự cập nhật công việc đã làm, ngày thực hiện, kết quả và hồ sơ đính kèm.',
            'note' => 'Báo cáo lưu kết quả thực hiện, hình ảnh, tệp và ghi chú theo đúng công trình đã liên kết.',
        ],
        'completion' => [
            'title' => '12. Trang Hoàn thiện',
            'subtitle' => 'Theo dõi phần việc còn lại, người phụ trách, hạn hoàn thiện và kết quả cuối cùng.',
            'note' => 'Phần hoàn thiện được lưu riêng và giữ liên kết với kế hoạch, báo cáo và công trình.',
        ],
    ];
@endphp

<div class="ego-doc-page ego-tech-word-page">
    @if(session('success'))<div class="ego-doc-alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="ego-doc-alert danger">{{ $errors->first() }}</div>@endif
    <div class="ego-doc-heading compact">
        <div>
            <span class="ego-doc-kicker">EGO SOLAR<br>MODULE KỸ THUẬT</span>
            <h1>{{ $pageMeta[$mode]['title'] ?? ($nav[$mode]['label'] ?? 'Kỹ thuật') }}</h1>
            <p>{{ $pageMeta[$mode]['subtitle'] ?? '' }}</p>
        </div>
    </div>
    <div class="ego-tech-word-bar" aria-hidden="true"></div>
    @if(!empty($pageMeta[$mode]['note']))
        <div class="ego-tech-word-note">{{ $pageMeta[$mode]['note'] }}</div>
    @endif

    <div class="ego-process-layout technical">
        <aside class="ego-process-rail">
            <div class="ego-process-title"><span>QUY TRÌNH</span><strong>KỸ THUẬT</strong></div>
            @foreach($nav as $key=>$item)
                <a href="{{ route($item['route']) }}" class="ego-process-link {{ $mode===$key ? 'active' : '' }}"><span class="icon"><i class="bi {{ $item['icon'] }}"></i></span><span><strong>{{ $item['label'] }}</strong><small>{{ $key==='plan' ? $kpis['planned'].' kế hoạch' : ($key==='report' ? $kpis['reported'].' đã báo cáo' : ($key==='kpis' ? 'Dashboard hiệu suất' : $kpis['total'].' công việc')) }}</small></span><i class="bi bi-chevron-right"></i></a>
            @endforeach
        </aside>

        <main class="ego-process-content">
            @if($mode === 'overview')
                <div class="ego-step-unit">
                    <div class="ego-step-unit-head"><div><span>MODULE KỸ THUẬT</span><h3>Tổng quan</h3><p>Theo dõi công việc kỹ thuật từ kế hoạch, báo cáo đến hiệu suất KPIs.</p></div></div>
                    <div class="ego-kpi-strip four"><div><strong>{{ $kpis['total'] }}</strong><span>Tổng công việc</span></div><div><strong>{{ $kpis['planned'] }}</strong><span>Đã lên kế hoạch</span></div><div><strong>{{ $kpis['reported'] }}</strong><span>Đã báo cáo</span></div><div><strong>{{ $kpis['completed'] }}</strong><span>Đã hoàn thiện</span></div></div>
                    <section class="ego-form-section"><h4>CÔNG VIỆC GẦN ĐÂY</h4>@include('technical.work.partials-records-v5')</section>
                </div>

            @elseif($mode === 'plan')
                <div class="ego-business-step-heading"><span>MODULE KỸ THUẬT</span><h2>Kế hoạch</h2><p>Kế hoạch là màn hình riêng; các trường chính bám theo form: ngày thực hiện, loại công việc, ưu tiên, thiết bị, nội dung và ghi chú kỹ thuật.</p></div>
                <div class="ego-step-unit">
                    <form method="POST" action="{{ route('ky-thuat.ke-hoach.store') }}" enctype="multipart/form-data">@csrf
                        <section class="ego-form-section">
                            <h4>KẾ HOẠCH</h4>
                            <div class="ego-grid-form cols-3">
                                <label><span>Công trình *</span><select name="site_id" required><option value="">Chọn công trình</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected(old('site_id')==$s->id)>{{ $s->project_code ?? ('CT-'.$s->id) }} · {{ $s->name }}</option>@endforeach</select></label>
                                <label><span>Ngày thực hiện *</span><input type="date" name="work_date" value="{{ old('work_date', now()->format('Y-m-d')) }}" required></label>
                                <label><span>Loại công việc *</span><select name="work_type" required>@foreach($workTypeLabels as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                                <label><span>Ưu tiên *</span><select name="priority"><option value="normal">Bình thường</option><option value="high">Cao</option><option value="urgent">Khẩn cấp</option><option value="low">Thấp</option></select></label>
                                <label class="span-2"><span>Nhân sự thực hiện</span><select name="assignee_ids[]" multiple size="4">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></label>
                            </div>
                        </section>
                        <section class="ego-form-section"><h4>THÔNG TIN INVERTER / THIẾT BỊ</h4><textarea name="device_info" rows="3" placeholder="Model, công suất, serial nếu cần...">{{ old('device_info') }}</textarea></section>
                        <section class="ego-form-section"><h4>NỘI DUNG / YÊU CẦU CÔNG VIỆC</h4><textarea name="requirement" rows="5" required>{{ old('requirement') }}</textarea></section>
                        <section class="ego-form-section"><h4>GHI CHÚ KỸ THUẬT</h4><textarea name="technical_note" rows="3">{{ old('technical_note') }}</textarea><label class="ego-file-input"><span>Hình ảnh / File kế hoạch</span><input type="file" name="files[]" multiple></label><div class="ego-actions"><button class="ego-doc-btn primary" type="submit"><i class="bi bi-save"></i> Lưu kế hoạch</button></div></section>
                    </form>
                </div>

            @elseif($mode === 'report')
                <div class="ego-business-step-heading"><span>MODULE KỸ THUẬT</span><h2>Báo cáo Kỹ thuật</h2><p>Nhân sự cập nhật công việc đã làm, ngày thực hiện, kết quả và hồ sơ đính kèm.</p></div>
                <div class="ego-step-unit">
                    <section class="ego-form-section"><h4>CHỌN CÔNG VIỆC</h4><select onchange="if(this.value) location='{{ route('ky-thuat.bao-cao') }}?item='+this.value"><option value="">Chọn kế hoạch cần báo cáo</option>@foreach($records as $r)<option value="{{ $r->id }}" @selected($selected && (int)$selected->id===(int)$r->id)>{{ $r->site_name }} · {{ $workTypeLabels[$r->work_type] ?? $r->work_type }} · {{ \Illuminate\Support\Carbon::parse($r->work_date)->format('d/m/Y') }}</option>@endforeach</select></section>
                    @if($selected)
                    <form method="POST" action="{{ route('ky-thuat.bao-cao.save', $selected->id) }}" enctype="multipart/form-data">@csrf
                        <section class="ego-form-section"><h4>BÁO CÁO</h4><div class="ego-grid-form cols-2"><label><span>Tên công việc</span><input name="report_title" value="{{ old('report_title', $selected->report_title ?: (($workTypeLabels[$selected->work_type] ?? $selected->work_type).' - '.$selected->site_name)) }}" required></label><label><span>Ngày thực hiện</span><input type="date" name="report_date" value="{{ old('report_date', $selected->report_date ?: $selected->work_date) }}" required></label></div></section>
                        <section class="ego-form-section"><h4>KẾT QUẢ THỰC HIỆN</h4><textarea name="result_text" rows="7" required>{{ old('result_text', $selected->result_text) }}</textarea><label class="ego-file-input"><span>Hình ảnh / File</span><input type="file" name="files[]" multiple></label><label><span>Ghi chú</span><textarea name="report_note" rows="3">{{ old('report_note', $selected->report_note) }}</textarea></label><div class="ego-actions"><button class="ego-doc-btn primary" type="submit"><i class="bi bi-save"></i> Lưu báo cáo</button></div></section>
                    </form>
                    @else<div class="ego-empty-cell">Chưa có kế hoạch để lập báo cáo.</div>@endif
                </div>

            @elseif($mode === 'completion')
                <div class="ego-business-step-heading"><span>MODULE KỸ THUẬT</span><h2>Hoàn thiện</h2><p>Theo dõi phần việc còn lại, người phụ trách, hạn hoàn thiện và kết quả cuối cùng.</p></div>
                <div class="ego-step-unit">
                    <section class="ego-form-section"><h4>CHỌN CÔNG VIỆC</h4><select onchange="if(this.value) location='{{ route('ky-thuat.hoan-thien') }}?item='+this.value"><option value="">Chọn công việc</option>@foreach($records as $r)<option value="{{ $r->id }}" @selected($selected && (int)$selected->id===(int)$r->id)>{{ $r->site_name }} · {{ $workTypeLabels[$r->work_type] ?? $r->work_type }} · {{ $r->status }}</option>@endforeach</select></section>
                    @if($selected)
                    @php $selectedAssignees = json_decode((string)$selected->assignee_ids, true) ?: []; @endphp
                    <form method="POST" action="{{ route('ky-thuat.hoan-thien.save', $selected->id) }}" enctype="multipart/form-data">@csrf
                        <section class="ego-form-section"><h4>VIỆC CÒN LẠI</h4><textarea name="remaining_work" rows="5">{{ old('remaining_work', $selected->remaining_work) }}</textarea><div class="ego-grid-form cols-2"><label><span>Người phụ trách</span><select name="assignee_ids[]" multiple size="4">@foreach($users as $u)<option value="{{ $u->id }}" @selected(in_array((int)$u->id, array_map('intval',$selectedAssignees), true))>{{ $u->name }}</option>@endforeach</select></label><label><span>Ngày phải hoàn thiện</span><input type="date" name="due_date" value="{{ old('due_date', $selected->due_date) }}"></label></div></section>
                        <section class="ego-form-section"><h4>BÁO CÁO HOÀN THIỆN</h4><textarea name="completion_report" rows="7" required>{{ old('completion_report', $selected->completion_report) }}</textarea><label class="ego-file-input"><span>Hình ảnh / File</span><input type="file" name="files[]" multiple></label><div class="ego-radio-row"><label><input type="radio" name="completion_state" value="completed" @checked($selected->status==='completed') required> Hoàn thành</label><label><input type="radio" name="completion_state" value="continue" @checked($selected->status!=='completed') required> Chưa hoàn thành / cần xử lý tiếp</label></div><div class="ego-actions"><button class="ego-doc-btn primary" type="submit"><i class="bi bi-save"></i> Lưu hoàn thiện</button></div></section>
                    </form>
                    @else<div class="ego-empty-cell">Chưa có công việc để hoàn thiện.</div>@endif
                </div>
            @endif
        </main>
    </div>
</div>
@endsection
