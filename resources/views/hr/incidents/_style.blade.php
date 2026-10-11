{{-- CSS dùng chung cho trang Sự cố văn phòng (trên nền giao diện Chấm công). --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-attendance-promax.css') }}?v={{ filemtime(public_path('css/ego-attendance-promax.css')) }}">
    <style>
        #egoAttendancePromax .ic-body{padding:14px 16px}
        #egoAttendancePromax .ic-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
        #egoAttendancePromax .ic-field label{display:block;margin-bottom:4px;color:#5b7284;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.03em}
        #egoAttendancePromax .ic-input{width:100%;min-width:0;border:1px solid #d2e2e8;border-radius:9px;padding:7px 9px;font-size:12px;background:#fff;color:#1d3a50}
        #egoAttendancePromax textarea.ic-input{min-height:70px}
        #egoAttendancePromax .ic-hint{margin:10px 0 0;padding:10px 12px;border:1px dashed #b9d9e2;border-radius:10px;background:#f7fcfd;color:#35556b;font-size:11px;line-height:1.55}
        #egoAttendancePromax .ic-section{margin-top:12px}
        #egoAttendancePromax .ic-muted{color:#7a8f9c;font-size:10px}
        #egoAttendancePromax .ic-late{color:#b42318;font-weight:800}
        #egoAttendancePromax .ic-ok{color:#08745a;font-weight:800}
        #egoAttendancePromax .ic-filter{display:flex;flex-wrap:wrap;align-items:end;gap:10px}
        #egoAttendancePromax .ic-kpis{display:flex;flex-wrap:wrap;gap:24px}
        #egoAttendancePromax .ic-kpis strong{display:block;font-size:22px;color:var(--at-ink)}
        #egoAttendancePromax .ic-files{display:flex;flex-wrap:wrap;gap:8px}
        #egoAttendancePromax .ic-files a{display:block;border:1px solid var(--at-line);border-radius:10px;overflow:hidden;width:120px;font-size:10px;text-decoration:none;color:#35556b;background:#fff}
        #egoAttendancePromax .ic-files img{display:block;width:120px;height:90px;object-fit:cover}
        #egoAttendancePromax .ic-files span{display:block;padding:4px 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        #egoAttendancePromax .ic-stars label{font-size:22px;cursor:pointer;color:#c9a227;margin-right:2px}
        @media(max-width:900px){#egoAttendancePromax .ic-grid{grid-template-columns:1fr}}
    </style>
@endpush

@if(session('success'))
    <div class="at-alert"><i class="bi bi-check-circle"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="at-alert at-alert--danger"><i class="bi bi-exclamation-circle"></i>{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="at-alert at-alert--danger"><i class="bi bi-exclamation-circle"></i>{{ $errors->first() }}</div>
@endif
