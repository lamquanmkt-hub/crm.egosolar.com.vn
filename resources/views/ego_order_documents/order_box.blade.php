<div class="op-card">
    <header class="op-card-head">
        <div><h2><i class="bi bi-folder2-open"></i> Chứng từ đơn hàng</h2><p>ĐNTT, hợp đồng, báo giá, hóa đơn, UNC, phiếu xuất kho và biên bản.</p></div>
        <span class="op-badge neutral">{{ collect($orderDocuments ?? [])->count() }} file</span>
    </header>
    <div class="op-card-body">
        <form method="POST" action="{{ url('/orders/'.$order->id.'/documents-ego') }}?tab=documents" enctype="multipart/form-data" class="op-doc-form">
            @csrf
            <label>Loại chứng từ<select class="op-select" name="document_type">@foreach($documentTypes ?? [] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label>Tiêu đề<input class="op-input" name="title" placeholder="VD: HĐMB / ĐNTT / UNC..."></label>
            <label>Ghi chú<input class="op-input" name="note" placeholder="Ghi chú ngắn"></label>
            <label>File<input class="op-input" type="file" name="documents[]" multiple required></label>
            <button class="op-btn op-btn-primary"><i class="bi bi-cloud-arrow-up"></i> Tải lên</button>
        </form>
        <div class="op-doc-grid">
            @forelse($orderDocuments ?? [] as $document)
                <article class="op-doc-card">
                    <div class="op-doc-icon"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="op-doc-info"><span class="op-badge info">{{ ($documentTypes ?? [])[$document->document_type] ?? $document->document_type }}</span><strong>{{ $document->title ?: $document->original_name }}</strong><small>{{ $document->original_name }} · {{ number_format(((int)($document->size_bytes ?? 0))/1024,1) }} KB · {{ $document->created_at ? \Carbon\Carbon::parse($document->created_at)->format('d/m/Y H:i') : '—' }}</small>@if($document->note)<p>{{ $document->note }}</p>@endif</div>
                    <div class="op-doc-actions"><a target="_blank" class="op-icon-btn" title="Xem" href="{{ url('/orders/'.$order->id.'/documents-ego/'.$document->id.'/preview') }}"><i class="bi bi-eye"></i></a><a class="op-icon-btn" title="Tải" href="{{ url('/orders/'.$order->id.'/documents-ego/'.$document->id.'/download') }}"><i class="bi bi-download"></i></a><form method="POST" action="{{ url('/orders/'.$order->id.'/documents-ego/'.$document->id.'/xoa') }}?tab=documents" data-op-confirm="Xóa file này?">@csrf<button class="op-icon-btn danger" title="Xóa"><i class="bi bi-trash"></i></button></form></div>
                </article>
            @empty
                <div class="op-empty"><i class="bi bi-folder2-open"></i><strong>Chưa có chứng từ</strong><span>Tải file lên ngay trong tab này.</span></div>
            @endforelse
        </div>
    </div>
</div>
