@extends('layouts.app')
@section('title', 'Xem trước hồ sơ tuyển dụng')
@section('content')
<div class="rec-preview-page">
    <div class="rec-preview-head"><div><h1>Xem trước: {{ $attachment->original_name }}</h1><p>{{ strtoupper($attachment->extension) }} · {{ number_format($attachment->size / 1024, 0) }} KB</p></div><div class="rec-preview-actions"><a class="btn btn-outline-secondary" href="{{ $back }}">← Quay lại hồ sơ</a><a class="btn btn-primary" href="{{ route('hr.recruitment.attachments.download', [$entity,$record,$attachment->id]) }}">Tải xuống</a></div></div>
    @if(in_array($attachment->extension,['jpg','jpeg','png','webp'],true))
        <div class="rec-image-preview"><img src="{{ route('hr.recruitment.attachments.content', [$entity,$record,$attachment->id]) }}" alt="{{ $attachment->original_name }}"></div>
    @elseif($attachment->extension === 'pdf')
        <section class="rec-pdf-preview" data-rec-pdf data-url="{{ route('hr.recruitment.attachments.content', [$entity,$record,$attachment->id]) }}" aria-label="Xem tài liệu PDF">
            <div class="rec-pdf-tools"><button type="button" class="btn btn-outline-secondary" data-pdf-prev disabled>← Trang trước</button><span data-pdf-page aria-live="polite">Đang mở PDF…</span><button type="button" class="btn btn-outline-secondary" data-pdf-next disabled>Trang sau →</button></div>
            <p data-pdf-error role="alert" hidden></p><div class="rec-pdf-canvas"><canvas data-pdf-canvas aria-label="Trang PDF"></canvas></div>
        </section>
        <script type="module" src="{{ asset('js/hr-recruitment-pdf.js') }}?v={{ filemtime(public_path('js/hr-recruitment-pdf.js')) }}"></script>
    @else
        <iframe class="rec-document-preview" title="Nội dung {{ $attachment->original_name }}" src="{{ route('hr.recruitment.attachments.content', [$entity,$record,$attachment->id]) }}"></iframe>
    @endif
    <p>“Xem trước” giúp đọc nội dung. Bấm “Tải xuống” để giữ tệp gốc.</p>
</div>
<style>.rec-preview-page{padding:18px;min-width:0}.rec-preview-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px}.rec-preview-head h1{font-size:20px;overflow-wrap:anywhere}.rec-preview-head p{color:#64748b}.rec-preview-actions{display:flex;gap:8px;flex-wrap:wrap}.rec-document-preview{width:100%;height:75vh;border:1px solid #d9e2ef;border-radius:10px;background:white}.rec-image-preview{background:white;text-align:center;padding:16px;border-radius:10px}.rec-image-preview img{max-width:100%;height:auto}.rec-pdf-tools{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;padding:12px}.rec-pdf-canvas{padding:12px;text-align:center;background:#e8edf4;border-radius:10px;overflow:auto}.rec-pdf-canvas canvas{max-width:100%;height:auto;background:white}.rec-pdf-preview{min-width:0}</style>
@endsection
