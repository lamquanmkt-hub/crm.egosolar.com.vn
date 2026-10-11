{{--
    Khối kiểm tra nguồn KPI HCNS dùng chung (HC06 cấp phát VPP, kiểm kê tài sản).
    $p: id, title, row (dòng nguồn đã đánh giá), review, events, files, missing, locked, isOwner, canEdit, canReview,
        checks (key => nhãn), facts ([nhãn, giá trị]), how (HTML đã escape), routes[draft, submit, confirm, return, upload],
        fileUrl(file, download), deleteUrl(file), ownerHint.
        Tùy chọn: files = null (ẩn phần tệp, kèm filesNote), uploadField (mặc định "files"), previewPage(file) → mở trang
        xem trước sẵn có thay cho hộp xem nhanh.
--}}
@php
    $status = $p['review']->status ?? 'none';
    $fmt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i d/m/Y') : '—';
    $stateLabel = [
        'counted' => [$p['row']['state_label'] ?? 'Đã kiểm tra — được tính vào số đo', 'ok'],
        'earned' => ['Đúng hạn, đã kiểm tra — được tính', 'ok'],
        'lost' => [$p['row']['state_label'] ?? 'Không đạt — tính vào mẫu số', 'bad'],
        'pending' => ['Chờ kiểm tra / gửi lại', 'wait'],
        'waiting' => ['Còn hạn — chưa tính', 'wait'],
        'excluded' => ['Loại khỏi KPI', 'muted'],
        'missing' => ['Chưa gửi kiểm tra — chưa tính', 'warn'],
    ][$p['row']['kpi_state'] ?? 'missing'] ?? ['—', 'muted'];
    $canSend = $p['isOwner'] && ! $p['locked'] && in_array($status, ['none', 'draft', 'returned', 'changed'], true);
    $checks = json_decode((string) ($p['review']->checks ?? ''), true) ?: [];
    $isImage = fn ($f) => str_starts_with((string) $f->mime_type, 'image/');
    $isPdf = fn ($f) => (string) $f->mime_type === 'application/pdf';
@endphp
<section class="hk-src" id="{{ $p['id'] }}" data-kpi-review-status="{{ $status }}" data-kpi-state="{{ $p['row']['kpi_state'] ?? 'missing' }}">
    <header class="hk-src-head">
        <h2>{{ $p['title'] }}</h2>
        <span class="hk-src-pill is-{{ $stateLabel[1] }}" data-hk-src-state>{{ $stateLabel[0] }}</span>
    </header>

    @if($p['locked'])
        <div class="hk-src-alert is-lock" data-hk-src-locked>Kỳ KPI tháng {{ substr($p['locked'], 5, 2) }}/{{ substr($p['locked'], 0, 4) }} đã chốt lương — hồ sơ chỉ xem, không sửa/gửi/xác nhận được.</div>
    @endif

    <div class="hk-src-grid">
        @foreach($p['facts'] as [$label, $value])
            <div class="hk-src-fact"><span>{{ $label }}</span><b>{{ $value }}</b></div>
        @endforeach
        <div class="hk-src-fact"><span>Trạng thái kiểm tra</span><b>{{ \App\Services\Hr\Kpi\HcnsSourceReviews::STATUSES[$status] ?? 'Chưa lưu/gửi' }}</b></div>
        <div class="hk-src-fact"><span>Gửi lần đầu · số lần gửi</span><b>{{ $fmt($p['review']->first_submitted_at ?? null) }} · {{ (int) ($p['review']->submission_count ?? 0) }}</b></div>
        <div class="hk-src-fact"><span>Người/giờ xác nhận</span><b>{{ ! empty($p['review']->confirmed_by) ? ($p['row']['confirmed_by_name'] ?? '—').' · '.$fmt($p['review']->confirmed_at) : '—' }}</b></div>
        <div class="hk-src-fact"><span>Kết quả từng mục</span><b>
            @forelse($checks as $key => $value)
                <em class="hk-src-check is-{{ $value }}">{{ $p['checks'][$key] ?? $key }}: {{ $value === 'pass' ? 'Đạt' : 'Không đạt' }}</em>
            @empty — @endforelse
        </b></div>
    </div>

    <p class="hk-src-reason" data-hk-src-reason><strong>KPI:</strong> {{ $p['row']['reason'] ?? '' }}</p>
    @if($status === 'returned' && ! empty($p['review']->return_reason))
        <div class="hk-src-alert"><strong>Trả bổ sung:</strong> {{ $p['review']->return_reason }}</div>
    @endif

    @if($p['files'] === null)
        @if(! empty($p['filesNote']))<div class="hk-src-files hk-src-muted" data-hk-src-files>{!! $p['filesNote'] !!}</div>@endif
    @else
    <div class="hk-src-files" data-hk-src-files>
        <h3>Minh chứng ({{ $p['files']->count() }})</h3>
        @forelse($p['files'] as $f)
            <div class="hk-src-file">
                <span class="hk-src-file-name">{{ $f->original_name }}</span>
                <small>{{ number_format(((int) $f->size) / 1024, 0, ',', '.') }} KB · {{ $f->uploader_name ?: '—' }} · {{ $fmt($f->created_at) }}</small>
                <span class="hk-src-file-actions">
                    @if(! empty($p['previewPage']))
                        <a class="hk-src-btn soft" href="{{ ($p['previewPage'])($f) }}">Xem</a>
                    @elseif($isImage($f) || $isPdf($f))
                        <button type="button" class="hk-src-btn soft" data-hk-preview="{{ ($p['fileUrl'])($f, false) }}" data-hk-preview-kind="{{ $isImage($f) ? 'image' : 'pdf' }}" data-hk-preview-name="{{ $f->original_name }}">Xem</button>
                    @endif
                    <a class="hk-src-btn soft" href="{{ ($p['fileUrl'])($f, true) }}">Tải</a>
                    @if($p['canEdit'] && ! $p['locked'])
                        <form method="POST" action="{{ ($p['deleteUrl'])($f) }}" onsubmit="return confirm('Xóa tệp này? Nếu đã gửi kiểm, cần gửi lại.')">
                            @csrf @method('DELETE')
                            <button class="hk-src-btn danger" type="submit">Xóa</button>
                        </form>
                    @endif
                </span>
            </div>
        @empty
            <div class="hk-src-muted">Chưa có tệp minh chứng.</div>
        @endforelse
        @if($p['canEdit'] && ! $p['locked'])
            <form method="POST" action="{{ $p['routes']['upload'] }}" enctype="multipart/form-data" class="hk-src-upload" data-hk-src-upload>
                @csrf
                <input type="file" name="{{ $p['uploadField'] ?? 'files' }}[]" multiple accept="{{ $p['accept'] ?? '.jpg,.jpeg,.png,.webp,.heic,.pdf,.doc,.docx,.xls,.xlsx,.csv' }}" required>
                <button class="hk-src-btn" type="submit">Tải lên</button>
                <small>{{ $p['uploadHint'] ?? 'Ảnh/PDF/Word/Excel ≤ 20MB.' }} Tệp riêng tư, chỉ mở qua trang này. Thêm/xóa tệp sau khi gửi → phải gửi lại.</small>
            </form>
        @endif
    </div>
    @endif

    @if($canSend)
        <form method="POST" class="hk-src-form" data-hk-src-owner-form>
            @csrf
            <label>Ghi chú cho người kiểm (không bắt buộc)</label>
            <textarea name="note" maxlength="2000" rows="2"></textarea>
            @if($p['missing'] !== [])
                <div class="hk-src-alert" data-hk-src-missing>Còn thiếu để gửi kiểm: {{ implode(', ', $p['missing']) }}.</div>
            @endif
            <div class="hk-src-actions">
                <button class="hk-src-btn soft" type="submit" formaction="{{ $p['routes']['draft'] }}">Lưu nháp</button>
                <button class="hk-src-btn" type="submit" formaction="{{ $p['routes']['submit'] }}" @disabled($p['missing'] !== [])>{{ in_array($status, ['returned', 'changed'], true) ? 'Gửi lại' : 'Gửi kiểm tra' }}</button>
            </div>
        </form>
    @elseif($p['isOwner'] && $status === 'submitted')
        <div class="hk-src-muted">Đã gửi, chờ người kiểm. Sửa mốc/người/tệp sẽ làm bản đã gửi hết hiệu lực và cần gửi lại.</div>
    @endif

    @if($p['canReview'] && ! $p['locked'] && in_array($status, ['submitted', 'confirmed'], true))
        <div class="hk-src-review">
            @if($status === 'submitted')
                <form method="POST" action="{{ $p['routes']['confirm'] }}" class="hk-src-form boxed" data-hk-src-confirm>
                    @csrf
                    <h3>Người kiểm chấm từng mục</h3>
                    @foreach($p['checks'] as $key => $label)
                        <div class="hk-src-row">
                            <span>{{ $label }}</span>
                            <label><input type="radio" name="checks[{{ $key }}]" value="pass" required> Đạt</label>
                            <label><input type="radio" name="checks[{{ $key }}]" value="fail"> Không đạt</label>
                        </div>
                    @endforeach
                    <textarea name="note" maxlength="2000" rows="2" placeholder="Ghi rõ lỗi nếu có mục không đạt"></textarea>
                    <button class="hk-src-btn" type="submit">Xác nhận kết quả kiểm tra</button>
                </form>
            @endif
            <form method="POST" action="{{ $p['routes']['return'] }}" class="hk-src-form boxed" data-hk-src-return>
                @csrf
                <h3>Trả bổ sung</h3>
                <textarea name="reason" required minlength="5" maxlength="2000" rows="2" placeholder="{{ $p['returnPlaceholder'] ?? 'Nội dung cần sửa: mốc giờ, số lượng, thiếu ký nhận…' }}"></textarea>
                <button class="hk-src-btn danger" type="submit">Trả bổ sung</button>
            </form>
        </div>
    @elseif(! $p['isOwner'] && ! $p['canReview'] && ! $p['canEdit'])
        <div class="hk-src-muted">Bạn chỉ xem. {{ $p['ownerHint'] ?? 'Người xử lý gửi kiểm; người kiểm được chỉ định hoặc Ban Giám đốc xác nhận.' }}</div>
    @endif

    <div class="hk-src-how">{!! $p['how'] !!}</div>

    @if($p['events']->isNotEmpty())
        <details class="hk-src-events"><summary>Lịch sử kiểm tra ({{ $p['events']->count() }})</summary>
            @foreach($p['events'] as $event)
                <div>{{ $fmt($event->created_at) }} · {{ $event->actor_name ?: 'Hệ thống' }} · {{ [
                    'draft_saved' => 'Lưu nháp', 'submitted' => 'Gửi kiểm tra', 'resubmitted' => 'Gửi lại', 'confirmed' => 'Xác nhận',
                    'returned' => 'Trả bổ sung', 'invalidated' => 'Xác nhận/bản gửi hết hiệu lực do sửa',
                ][$event->action] ?? $event->action }} (bản {{ $event->revision }})@if($event->note) — {{ $event->note }}@endif</div>
            @endforeach
        </details>
    @endif
</section>

@once
<dialog class="hk-src-preview" id="hkSrcPreview">
    <div class="hk-src-preview-head"><b data-hk-preview-title>Xem tệp</b><button type="button" class="hk-src-btn soft" data-hk-preview-close>Đóng</button></div>
    <div class="hk-src-preview-body" data-hk-preview-body></div>
</dialog>
<script>
(function () {
    var dlg = document.getElementById('hkSrcPreview');
    if (!dlg) return;
    var body = dlg.querySelector('[data-hk-preview-body]');
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-hk-preview]');
        if (btn) {
            var url = btn.getAttribute('data-hk-preview');
            body.innerHTML = '';
            var el = document.createElement(btn.getAttribute('data-hk-preview-kind') === 'image' ? 'img' : 'iframe');
            el.src = url;
            el.alt = btn.getAttribute('data-hk-preview-name') || '';
            body.appendChild(el);
            dlg.querySelector('[data-hk-preview-title]').textContent = btn.getAttribute('data-hk-preview-name') || 'Xem tệp';
            dlg.showModal();
        }
        if (e.target.closest('[data-hk-preview-close]') || e.target === dlg) {
            dlg.close();
            body.innerHTML = '';
        }
    });
})();
</script>
<style>
    .hk-src{background:#fff;border:1px solid #dbe5f0;border-radius:18px;padding:16px;margin:14px 0;box-shadow:0 8px 22px rgba(15,23,42,.05)}
    .hk-src-head{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
    .hk-src-head h2{margin:0;font-size:16px;font-weight:900;color:#0f3b78}
    .hk-src-pill{border-radius:999px;padding:4px 11px;font-size:12px;font-weight:900;border:1px solid transparent}
    .hk-src-pill.is-ok{background:#e8f7ee;color:#15803d;border-color:#c3e8d1}
    .hk-src-pill.is-bad{background:#fdecec;color:#b91c1c;border-color:#f5c9c9}
    .hk-src-pill.is-wait{background:#e9f6fb;color:#0e6a8a;border-color:#c6e6f2}
    .hk-src-pill.is-warn{background:#fff6e5;color:#9a5b00;border-color:#f3dcae}
    .hk-src-pill.is-muted{background:#f1f4f8;color:#556274;border-color:#e1e7ef}
    .hk-src-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px}
    .hk-src-fact{background:#f8fafc;border:1px solid #e5edf5;border-radius:12px;padding:8px 10px;display:grid;gap:2px}
    .hk-src-fact span{font-size:11.5px;color:#64748b;font-weight:700}
    .hk-src-fact b{font-size:13px;color:#0f172a;word-break:break-word}
    .hk-src-check{display:inline-block;font-style:normal;margin:0 4px 4px 0;border-radius:8px;padding:2px 7px;font-size:12px}
    .hk-src-check.is-pass{background:#e8f7ee;color:#15803d}.hk-src-check.is-fail{background:#fdecec;color:#b91c1c}
    .hk-src-reason{margin:12px 0 8px;font-size:13px;color:#334155}
    .hk-src-alert{margin:8px 0;padding:10px 12px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;font-weight:700}
    .hk-src-alert.is-lock{background:#eef2ff;border-color:#c7d2fe;color:#3730a3}
    .hk-src-files{margin-top:10px;border-top:1px dashed #dbe5f0;padding-top:10px}
    .hk-src-files h3,.hk-src-form h3{margin:0 0 6px;font-size:14px;color:#0f3b78}
    .hk-src-file{display:flex;flex-wrap:wrap;gap:6px 10px;align-items:center;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
    .hk-src-file-name{font-weight:800;color:#0f172a;word-break:break-all}
    .hk-src-file small{color:#64748b}
    .hk-src-file-actions{display:flex;gap:6px;margin-left:auto}
    .hk-src-file-actions form{margin:0}
    .hk-src-upload{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:8px}
    .hk-src-upload small{flex-basis:100%;color:#64748b}
    .hk-src-form{display:grid;gap:8px;margin-top:10px}
    .hk-src-form label{font-size:12px;font-weight:900;color:#475569}
    .hk-src-form textarea{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:8px;font-size:13px}
    .hk-src-form.boxed{border:1px solid #dbe5f0;border-radius:14px;padding:12px;background:#fbfdff}
    .hk-src-actions{display:flex;gap:8px;flex-wrap:wrap}
    .hk-src-btn{border:0;border-radius:10px;padding:7px 13px;font-weight:800;font-size:13px;background:#0f3b78;color:#fff;cursor:pointer;text-decoration:none;display:inline-block}
    .hk-src-btn.soft{background:#eef4fb;color:#0f3b78}
    .hk-src-btn.danger{background:#fdecec;color:#b91c1c}
    .hk-src-btn:disabled{opacity:.5;cursor:not-allowed}
    .hk-src-review{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:12px;margin-top:6px}
    .hk-src-row{display:flex;flex-wrap:wrap;gap:12px;align-items:center;font-size:13px}
    .hk-src-row span{flex:1 1 180px;font-weight:800}
    .hk-src-muted{font-size:13px;color:#64748b;margin-top:6px}
    .hk-src-how{margin-top:12px;font-size:12.5px;color:#475569;background:#f8fafc;border:1px dashed #d3dce8;border-radius:12px;padding:9px 11px}
    .hk-src-events{margin-top:10px;font-size:12.5px;color:#475569}
    .hk-src-events summary{cursor:pointer;font-weight:800}
    .hk-src-preview{border:0;border-radius:16px;padding:0;width:min(920px,96vw);max-height:92vh}
    .hk-src-preview::backdrop{background:rgba(15,23,42,.55)}
    .hk-src-preview-head{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid #e5edf5}
    .hk-src-preview-body{padding:8px;display:flex;justify-content:center}
    .hk-src-preview-body img{max-width:100%;max-height:80vh}
    .hk-src-preview-body iframe{width:100%;height:78vh;border:0}
    @media(max-width:768px){.hk-src{padding:12px}.hk-src-review{grid-template-columns:1fr}.hk-src-file-actions{margin-left:0}}
</style>
@endonce
