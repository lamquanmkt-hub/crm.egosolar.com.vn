<div class="wx2-card">
    <h2>File minh chứng</h2>
    @forelse($attachments as $file)
        <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;padding:4px 0;border-bottom:1px solid #eef2f7">
            <span style="min-width:0"><a target="_blank" rel="noopener" href="{{ route('ky-thuat.warranty-exchange.evidence.download', ['claim' => $claim->id, 'attachment' => $file->id]) }}">{{ $file->original_name }}</a>
                <small style="color:#64748b"> · {{ number_format(($file->file_size ?? 0) / 1024, 0) }} KB · {{ $file->uploader_name ?: '—' }}</small></span>
            @if($can['evidence'])
                <form method="POST" action="{{ route('ky-thuat.warranty-exchange.evidence.destroy', ['claim' => $claim->id, 'attachment' => $file->id]) }}" onsubmit="return confirm('Gỡ minh chứng này?')">@csrf @method('DELETE')
                    <button class="wx-btn tiny ghost" type="submit"><i class="bi bi-trash"></i></button></form>
            @endif
        </div>
    @empty
        <p class="meta" style="color:#64748b;font-size:12.5px;margin:0">Chưa có minh chứng (hoặc bạn không có quyền xem file).</p>
    @endforelse
    @if($can['evidence'])
        <form class="wx2-form" style="margin-top:10px" method="POST" enctype="multipart/form-data" action="{{ route('ky-thuat.warranty-exchange.evidence.upload', ['claim' => $claim->id]) }}">@csrf
            <label>Tải thêm (JPG/PNG/WEBP/PDF, tối đa {{ config('warranty.evidence_max_files') }} tệp, 20MB/tệp)
                <input type="file" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label>
            <button class="wx-btn secondary small" type="submit"><i class="bi bi-cloud-arrow-up"></i>Tải lên (lưu riêng tư)</button>
        </form>
    @endif
</div>
