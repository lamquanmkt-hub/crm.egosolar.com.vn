<style>
.ego-profile-doc-card{
    background:linear-gradient(135deg,#ffffff 0%,#f8fdff 55%,#eefaff 100%);
    border:1px solid rgba(14,165,233,.18);
    border-radius:22px;
    box-shadow:0 16px 42px rgba(15,23,42,.07);
    overflow:hidden;
    margin-bottom:16px;
}

.ego-profile-doc-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding:16px 18px;
    border-bottom:1px solid rgba(148,163,184,.20);
}

.ego-profile-doc-title{
    font-size:18px;
    font-weight:950;
    color:#0f172a;
    margin:0;
}

.ego-profile-doc-sub{
    margin-top:4px;
    color:#64748b;
    font-size:12px;
    font-weight:700;
}

.ego-profile-doc-count{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:28px;
    padding:0 10px;
    border-radius:999px;
    background:#e0f2fe;
    color:#0369a1;
    font-size:12px;
    font-weight:950;
    white-space:nowrap;
}

.ego-profile-doc-body{
    padding:16px 18px 18px;
}

.ego-profile-doc-list{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(330px,1fr));
    gap:12px;
}

.ego-profile-doc-item{
    border:1px solid #e5edf5;
    border-radius:18px;
    padding:14px;
    background:#fff;
    box-shadow:0 8px 22px rgba(15,23,42,.035);
}

.ego-profile-doc-top{
    display:flex;
    align-items:flex-start;
    gap:11px;
}

.ego-profile-doc-ext{
    width:42px;
    height:42px;
    border-radius:15px;
    flex:0 0 auto;
    display:grid;
    place-items:center;
    background:linear-gradient(135deg,#e0f2fe,#f0fdfa);
    color:#0369a1;
    font-size:10px;
    font-weight:950;
    text-transform:uppercase;
}

.ego-profile-doc-info{
    min-width:0;
    flex:1;
}

.ego-profile-doc-name{
    font-size:13.5px;
    font-weight:950;
    color:#0f172a;
    line-height:1.35;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.ego-profile-doc-meta{
    margin-top:4px;
    font-size:11.5px;
    color:#64748b;
    font-weight:700;
}

.ego-profile-doc-actions{
    margin-top:12px;
    display:flex;
    align-items:center;
    gap:7px;
    flex-wrap:wrap;
}

.ego-profile-doc-actions form{
    margin:0;
}

.ego-profile-doc-btn{
    height:32px;
    border:1px solid #dbe5ef;
    border-radius:11px;
    padding:0 11px;
    background:#fff;
    color:#0f172a;
    font-size:12px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:5px;
    text-decoration:none;
    cursor:pointer;
}

.ego-profile-doc-btn:hover{
    color:#0891b2;
    border-color:#7dd3fc;
    text-decoration:none;
}

.ego-profile-doc-btn.primary{
    background:linear-gradient(135deg,#0891b2,#0f766e);
    color:#fff;
    border-color:transparent;
    box-shadow:0 10px 20px rgba(8,145,178,.18);
}

.ego-profile-doc-btn.danger{
    background:#fff1f2;
    color:#e11d48;
    border-color:#fecdd3;
}

.ego-profile-doc-empty{
    padding:14px;
    border:1px dashed #cbd5e1;
    border-radius:16px;
    background:#f8fafc;
    color:#64748b;
    font-weight:750;
}

/* Popup */
.ego-doc-modal{
    position:fixed;
    inset:0;
    z-index:99999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:24px;
    background:rgba(2,8,23,.72);
    backdrop-filter:blur(6px);
}

.ego-doc-modal.show{
    display:flex;
}

.ego-doc-modal-box{
    width:min(1180px,96vw);
    height:min(820px,92vh);
    background:#fff;
    border-radius:22px;
    overflow:hidden;
    box-shadow:0 32px 90px rgba(0,0,0,.38);
    display:flex;
    flex-direction:column;
}

.ego-doc-modal-head{
    height:58px;
    padding:0 16px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    background:linear-gradient(135deg,#0f172a,#0f766e);
    color:#fff;
    flex:0 0 auto;
}

.ego-doc-modal-title{
    min-width:0;
    font-size:14px;
    font-weight:950;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.ego-doc-modal-actions{
    display:flex;
    align-items:center;
    gap:8px;
    flex:0 0 auto;
}

.ego-doc-modal-actions a,
.ego-doc-modal-close{
    height:34px;
    border:1px solid rgba(255,255,255,.22);
    border-radius:11px;
    padding:0 11px;
    background:rgba(255,255,255,.10);
    color:#fff;
    font-size:12px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    cursor:pointer;
}

.ego-doc-modal-close{
    width:36px;
    padding:0;
    font-size:18px;
    line-height:1;
}

.ego-doc-modal-frame{
    width:100%;
    flex:1;
    border:0;
    background:#f8fafc;
}

@media(max-width:760px){
    .ego-profile-doc-list{
        grid-template-columns:1fr;
    }

    .ego-doc-modal{
        padding:10px;
    }

    .ego-doc-modal-box{
        width:100%;
        height:94vh;
        border-radius:18px;
    }

    .ego-doc-modal-head{
        height:auto;
        min-height:58px;
        align-items:flex-start;
        flex-direction:column;
        padding:12px;
    }

    .ego-doc-modal-actions{
        width:100%;
        justify-content:space-between;
    }
}
</style>

<div class="ego-profile-doc-card">
    <div class="ego-profile-doc-head">
        <div>
            <h3 class="ego-profile-doc-title">Các giấy tờ liên quan</h3>
            <div class="ego-profile-doc-sub">Bấm “Xem trước” để mở popup PDF/ảnh ngay trên màn hình.</div>
        </div>

        <span class="ego-profile-doc-count">{{ $documents->count() }} file</span>
    </div>

    <div class="ego-profile-doc-body">
        @if($documents->count())
            <div class="ego-profile-doc-list">
                @foreach($documents as $doc)
                    @php
                        $docName = $doc->title ?: $doc->original_name;
                        $previewUrl = url('/customer-profiles/'.$profile->id.'/documents/'.$doc->id.'/preview-ego');
                        $downloadUrl = route('customer-profiles.documents.download', [$profile, $doc]);
                        $docType = $documentTypes[$doc->document_type] ?? $doc->document_type;
                        $docExt = strtoupper(pathinfo($doc->original_name ?: $doc->file_path, PATHINFO_EXTENSION) ?: 'FILE');
                    @endphp

                    <div class="ego-profile-doc-item">
                        <div class="ego-profile-doc-top">
                            <div class="ego-profile-doc-ext">{{ $docExt }}</div>

                            <div class="ego-profile-doc-info">
                                <div class="ego-profile-doc-name">{{ $docName }}</div>
                                <div class="ego-profile-doc-meta">
                                    {{ $docType }} • {{ $docExt }} • {{ number_format(($doc->size_bytes ?? 0) / 1024, 1) }} KB
                                </div>

                                @if($doc->note)
                                    <div class="ego-profile-doc-meta">{{ $doc->note }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="ego-profile-doc-actions">
                            <button type="button"
                                    class="ego-profile-doc-btn primary"
                                    data-ego-doc-popup-url="{{ $previewUrl }}"
                                    data-ego-doc-popup-name="{{ $docName }}">
                                Xem trước
                            </button>

                            <a class="ego-profile-doc-btn" target="_blank" href="{{ $previewUrl }}">Mở tab</a>
                            <a class="ego-profile-doc-btn" href="{{ $downloadUrl }}">Tải file</a>

                            <form method="POST" action="{{ route('customer-profiles.documents.destroy', [$profile, $doc]) }}" onsubmit="return confirm('Xóa file này?')">
                                @csrf
                                @method('DELETE')
                                <button class="ego-profile-doc-btn danger" type="submit">Xóa</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="ego-profile-doc-empty">Chưa có giấy tờ liên quan.</div>
        @endif
    </div>
</div>

<div class="ego-doc-modal" id="egoDocPreviewModal" aria-hidden="true">
    <div class="ego-doc-modal-box">
        <div class="ego-doc-modal-head">
            <div class="ego-doc-modal-title" id="egoDocPreviewTitle">Xem trước file</div>

            <div class="ego-doc-modal-actions">
                <a href="#" target="_blank" id="egoDocPreviewOpen">Mở tab</a>
                <button type="button" class="ego-doc-modal-close" id="egoDocPreviewClose">×</button>
            </div>
        </div>

        <iframe class="ego-doc-modal-frame" id="egoDocPreviewFrame" src=""></iframe>
    </div>
</div>

<script>
(function(){
    const modal = document.getElementById('egoDocPreviewModal');
    const frame = document.getElementById('egoDocPreviewFrame');
    const title = document.getElementById('egoDocPreviewTitle');
    const open = document.getElementById('egoDocPreviewOpen');
    const close = document.getElementById('egoDocPreviewClose');

    function openModal(url, name){
        if (!modal || !frame) return;

        frame.src = url;
        if (title) title.textContent = name || 'Xem trước file';
        if (open) open.href = url;

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(){
        if (!modal || !frame) return;

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        frame.src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function(e){
        const btn = e.target.closest('[data-ego-doc-popup-url]');
        if (!btn) return;

        e.preventDefault();

        openModal(
            btn.getAttribute('data-ego-doc-popup-url'),
            btn.getAttribute('data-ego-doc-popup-name')
        );
    });

    if (close) {
        close.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', function(e){
            if (e.target === modal) closeModal();
        });
    }

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeModal();
    });
})();
</script>
