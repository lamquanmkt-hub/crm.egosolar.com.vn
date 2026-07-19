@php
    $debtFiles = collect($item->files ?? []);
@endphp

<style>
    .sd-file-manager {
        margin-top: 12px;
        border: 1px solid #dbeafe;
        border-radius: 16px;
        background: #f8fbff;
        padding: 12px;
    }

    .sd-file-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 9px;
    }

    .sd-file-title {
        font-size: 13px;
        font-weight: 900;
        color: #0f172a;
    }

    .sd-file-sub {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
    }

    .sd-file-upload {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 8px;
        align-items: center;
        margin-bottom: 10px;
    }

    .sd-file-input-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        border: 1px dashed #bfdbfe;
        border-radius: 12px;
        background: #fff;
        padding: 6px 8px;
        overflow: hidden;
    }

    .sd-file-input-wrap input {
        width: 100%;
        font-size: 12px;
    }

    .sd-file-list {
        display: grid;
        gap: 7px;
    }

    .sd-file-row {
        display: grid;
        grid-template-columns: 1fr auto auto;
        gap: 8px;
        align-items: center;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        padding: 8px 9px;
    }

    .sd-file-name {
        color: #0f172a;
        font-size: 12px;
        font-weight: 800;
        word-break: break-word;
    }

    .sd-file-meta {
        margin-top: 2px;
        color: #64748b;
        font-size: 11px;
        font-weight: 600;
    }

    .sd-file-empty {
        padding: 9px;
        border-radius: 12px;
        background: #fff;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    @media (max-width: 900px) {
        .sd-file-upload,
        .sd-file-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="sd-file-manager">
    <div class="sd-file-head">
        <div>
            <div class="sd-file-title">Tệp công nợ</div>
            <div class="sd-file-sub">Chọn tệp rồi bấm “Thêm tệp”. Có thể xóa từng tệp bên dưới.</div>
        </div>
    </div>

    <form method="POST" action="{{ route('finance.supplier-debts.files.store', $item->id) }}" enctype="multipart/form-data" class="sd-file-upload">
        @csrf
        <div class="sd-file-input-wrap">
            <input name="attachments[]" type="file" multiple required>
        </div>
        <button class="sd-btn sd-btn-primary" type="submit" style="height:40px">+ Thêm tệp</button>
    </form>

    <div class="sd-file-list">
        @forelse($debtFiles as $file)
            <div class="sd-file-row">
                <div>
                    <div class="sd-file-name">{{ $file->original_name ?? basename($file->path ?? '') }}</div>
                    <div class="sd-file-meta">
                        {{ !empty($file->size) ? number_format(((float) $file->size) / 1024, 1, ',', '.') . ' KB' : 'Tệp đính kèm' }}
                    </div>
                </div>

                <a class="sd-btn sd-btn-light" href="{{ route('finance.supplier-debts.files.download', $file->id) }}" style="height:34px;text-decoration:none">Tải</a>

                <form method="POST" action="{{ route('finance.supplier-debts.files.destroy', $file->id) }}" onsubmit="return confirm('Xóa tệp này?')" style="margin:0">
                    @csrf
                    @method('DELETE')
                    <button class="sd-btn sd-btn-danger" type="submit" style="height:34px;padding:0 10px">Xóa</button>
                </form>
            </div>
        @empty
            <div class="sd-file-empty">Chưa có tệp nào.</div>
        @endforelse
    </div>
</div>
