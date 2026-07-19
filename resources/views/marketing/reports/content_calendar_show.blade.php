@extends('layouts.app')

@section('title', 'Chi tiết nội dung')

@section('content')
@php
    use Illuminate\Support\Str;
    use Carbon\Carbon;

    Carbon::setLocale('vi');

    if(!function_exists('cc_parse_platform')) {
        function cc_parse_platform($raw){
            $raw = trim((string)$raw);
            $type = $raw;
            $account = null;

            if($raw === '') return ['type'=>'', 'account'=>null, 'raw'=>''];

            if(str_contains($raw, '|')){
                [$type, $account] = array_map('trim', explode('|', $raw, 2));
                return ['type'=>Str::lower($type), 'account'=>$account ?: null, 'raw'=>$raw];
            }

            if(preg_match('/^(facebook|tiktok|youtube|website)\s*[:\-]\s*(.+)$/i', $raw, $m)){
                $type = $m[1];
                $account = trim($m[2]);
                return ['type'=>Str::lower($type), 'account'=>$account ?: null, 'raw'=>$raw];
            }

            return ['type'=>Str::lower($raw), 'account'=>null, 'raw'=>$raw];
        }
    }

    $platformLabel = function($type){
        $type = Str::lower($type);
        return match($type){
            'facebook' => 'Facebook',
            'tiktok'   => 'TikTok',
            'youtube'  => 'YouTube',
            'website'  => 'Website',
            default    => Str::ucfirst($type),
        };
    };

    $p = cc_parse_platform($item->platform);
    $platformType = $p['type'];
    $platformAccount = $p['account'];

    $statusMap = [
        'draft'     => ['label' => 'Nháp',     'class' => 'cc-badge-draft'],
        'scheduled' => ['label' => 'Lên lịch', 'class' => 'cc-badge-scheduled'],
        'posted'    => ['label' => 'Đã đăng',  'class' => 'cc-badge-posted'],
        'submitted' => ['label' => 'Chờ duyệt','class' => 'cc-badge-submitted'],
        'approved'  => ['label' => 'Đã duyệt', 'class' => 'cc-badge-approved'],
        'rejected'  => ['label' => 'Từ chối',  'class' => 'cc-badge-rejected'],
    ];
    $s = $statusMap[$item->status] ?? ['label' => strtoupper((string)$item->status), 'class' => 'cc-badge-default'];

    $creatorName = optional($item->creator)->name ?? '—';
    $publishDMY = Carbon::parse($item->publish_date)->format('d/m/Y');

    // tuần của publish_date (Monday)
    $weekStart = Carbon::parse($item->publish_date)->startOfWeek(Carbon::MONDAY)->format('Y-m-d');

    $linkHost = '';
    if(!empty($item->link)){
        try{
            $u = parse_url($item->link);
            $linkHost = $u['host'] ?? '';
            $linkHost = preg_replace('/^www\./','',$linkHost);
        }catch(\Throwable $e){
            $linkHost = '';
        }
    }

    $isImageExt = function($name){
        $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg']);
    };

    $fileIcon = function($name){
        $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
        return match(true){
            in_array($ext, ['pdf']) => 'bi-file-earmark-pdf',
            in_array($ext, ['doc','docx']) => 'bi-file-earmark-word',
            in_array($ext, ['xls','xlsx','csv']) => 'bi-file-earmark-excel',
            in_array($ext, ['ppt','pptx']) => 'bi-file-earmark-ppt',
            in_array($ext, ['zip','rar','7z']) => 'bi-file-earmark-zip',
            in_array($ext, ['mp4','mov','mkv','avi']) => 'bi-file-earmark-play',
            in_array($ext, ['mp3','wav']) => 'bi-file-earmark-music',
            default => 'bi-paperclip',
        };
    };

    // metricWeek có thể được controller truyền sẵn; nếu không, JS sẽ tự load qua API
    $w = $metricWeek ?? null;
    $eng = ($w->likes ?? 0) + ($w->comments ?? 0) + ($w->shares ?? 0);

    $authName = auth()->user()->name ?? 'Bạn';
    $authId = auth()->id();
    $authInitial = mb_substr($authName ?: 'U', 0, 1);

    // feedbackTree được controller truyền sang
    $feedbackTreeLocal = $feedbackTree ?? collect();
@endphp

<div class="container-fluid px-4 mt-3 cc-show-page">

    {{-- HEADER --}}
    <div class="cc-page-header mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div class="cc-head-left">
                <div class="cc-breadcrumb small text-muted mb-1">
                    Marketing <span class="mx-1">/</span> Lịch biên tập <span class="mx-1">/</span> Chi tiết
                </div>

                <h3 class="cc-title mb-2">{{ $item->title }}</h3>

                <div class="cc-subline small">
                    <span class="cc-pill">
                        <i class="bi bi-globe"></i> {{ $platformLabel($platformType) }}
                    </span>

                    @if($platformAccount)
                        <span class="cc-pill cc-pill-muted" title="{{ $platformAccount }}">
                            <i class="bi bi-flag"></i> {{ Str::limit($platformAccount, 34) }}
                        </span>
                    @endif

                    <span class="cc-dot">•</span>
                    <span class="text-muted"><i class="bi bi-calendar3"></i> {{ $publishDMY }}</span>

                    <span class="cc-dot">•</span>
                    <span class="text-muted"><i class="bi bi-person"></i> {{ $creatorName }}</span>

                    @if(!empty($item->link))
                        <span class="cc-dot">•</span>
                        <a class="text-muted text-decoration-none"
                           href="{{ $item->link }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           title="Mở link">
                            <i class="bi bi-link-45deg"></i> {{ $linkHost ?: 'Link' }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="cc-head-right d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <span class="cc-status {{ $s['class'] }}">{{ $s['label'] }}</span>

                <a href="{{ route('marketing.reports.content-calendar') }}"
                   class="btn btn-outline-secondary cc-btn">
                    <i class="bi bi-arrow-left"></i> Về danh sách
                </a>

                <button class="btn btn-outline-secondary cc-btn" type="button" id="ccCopyTitle"
                        data-cc-copy="{{ $item->title }}">
                    <i class="bi bi-clipboard"></i> Copy tiêu đề
                </button>

                @if(!empty($item->link))
                    <a class="btn btn-outline-secondary cc-btn"
                       href="{{ $item->link }}" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right"></i> Mở link
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3">

        {{-- LEFT --}}
        <div class="col-lg-8">

            {{-- CONTENT EDITOR --}}
            <div class="card mb-3 cc-card">
                <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="cc-card-title"><i class="bi bi-file-text"></i> Nội dung bài viết</span>
                        <span class="cc-hint small text-muted">Gõ nội dung → Lưu. Có tab Preview để xem nhanh.</span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-secondary cc-btn-sm" type="button" id="ccCopyContent">
                            <i class="bi bi-clipboard"></i> Copy nội dung
                        </button>
                        <button class="btn btn-ego cc-btn-sm" type="button" id="ccSubmitEditor">
                            <i class="bi bi-save2"></i> Lưu
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <ul class="nav nav-pills cc-tabs mb-3" id="ccTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="cc-edit-tab" data-bs-toggle="pill" data-bs-target="#cc-edit" type="button" role="tab">
                                Soạn thảo
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cc-preview-tab" data-bs-toggle="pill" data-bs-target="#cc-preview" type="button" role="tab">
                                Preview
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="cc-edit" role="tabpanel">
                            <form method="POST"
                                  action="{{ route('marketing.reports.content-calendar.update', $item->id) }}"
                                  id="ccEditorForm">
                                @csrf
                                @method('PUT')

                                <textarea name="full_content"
                                          id="ccEditor"
                                          class="form-control cc-editor"
                                          rows="16"
                                          placeholder="Nhập nội dung chi tiết...">{{ $item->full_content }}</textarea>

                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <button class="btn btn-ego cc-btn" type="submit">
                                        <i class="bi bi-save2"></i> Lưu nội dung
                                    </button>

                                    <a href="{{ route('marketing.reports.content-calendar') }}"
                                       class="btn btn-outline-secondary cc-btn">
                                        <i class="bi bi-list-ul"></i> Về danh sách
                                    </a>

                                    @if(!empty($item->link))
                                        <button type="button"
                                                class="btn btn-outline-secondary cc-btn"
                                                data-cc-copy="{{ $item->link }}">
                                            <i class="bi bi-clipboard"></i> Copy link
                                        </button>
                                    @endif
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="cc-preview" role="tabpanel">
                            <div class="cc-preview-wrap">
                                <div class="cc-preview-title small text-muted mb-2">
                                    <i class="bi bi-eye"></i> Xem trước (format đơn giản)
                                </div>
                                <div class="cc-preview" id="ccPreviewBox"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ATTACHMENTS + UPLOAD --}}
            <div class="card cc-card">
                <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="cc-card-title"><i class="bi bi-images"></i> Ảnh / File đính kèm</span>
                        <span class="small text-muted">Kéo thả để upload nhanh, xem thumbnail ngay.</span>
                    </div>
                </div>

                <div class="card-body">

                    <form method="POST"
                          action="{{ route('marketing.reports.content-calendar.files', $item->id) }}"
                          enctype="multipart/form-data"
                          class="cc-upload"
                          id="ccUploadForm">
                        @csrf

                        <div class="cc-dropzone" id="ccDropzone">
                            <div class="cc-dropzone-ico"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div class="fw-semibold">Kéo thả ảnh/file vào đây</div>
                            <div class="text-muted small">hoặc bấm để chọn file (JPG/PNG/PDF/DOCX...)</div>
                            <input type="file" name="file" class="d-none" id="ccFileInput" required>
                        </div>

                        <div class="cc-upload-meta mt-3 d-none" id="ccUploadMeta">
                            <div class="cc-upload-left">
                                <div class="cc-filepicked">
                                    <div class="cc-filepicked-name" id="ccPickedName">—</div>
                                    <div class="cc-filepicked-sub text-muted small" id="ccPickedSub">—</div>
                                </div>
                                <div class="cc-img-preview d-none" id="ccImgPreviewWrap">
                                    <img id="ccImgPreview" alt="preview">
                                </div>
                            </div>

                            <div class="cc-upload-right">
                                <button class="btn btn-ego cc-btn w-100" type="submit" id="ccUploadBtn">
                                    <i class="bi bi-upload"></i> Upload
                                </button>
                                <button class="btn btn-outline-secondary cc-btn w-100 mt-2" type="button" id="ccClearPicked">
                                    <i class="bi bi-x-lg"></i> Bỏ chọn
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr class="cc-hr">

                    @if($item->files && $item->files->count())
                        <div class="cc-files-grid">
                            @foreach($item->files as $f)
                                @php
                                    $url = asset('storage/'.$f->file_path);
                                    $isImg = $isImageExt($f->file_name);
                                    $icon = $fileIcon($f->file_name);
                                @endphp

                                <div class="cc-file-card">
                                    <div class="cc-file-thumb">
                                        @if($isImg)
                                            <img src="{{ $url }}" alt="{{ $f->file_name }}">
                                        @else
                                            <div class="cc-file-icon">
                                                <i class="bi {{ $icon }}"></i>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="cc-file-info">
                                        <div class="cc-file-name" title="{{ $f->file_name }}">{{ $f->file_name }}</div>
                                        <div class="cc-file-sub small text-muted">
                                            {{ $f->file_type ?? (strtoupper(pathinfo($f->file_name, PATHINFO_EXTENSION)) ?: 'FILE') }}
                                        </div>
                                    </div>

                                    <div class="cc-file-actions">
                                        <button type="button" class="btn btn-outline-secondary cc-mini"
                                                data-cc-copy="{{ $url }}" title="Copy URL">
                                            <i class="bi bi-clipboard"></i>
                                        </button>

                                        <a class="btn btn-outline-secondary cc-mini"
                                           href="{{ $url }}" target="_blank" rel="noopener" title="Mở file">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>

                                        <form method="POST"
                                              action="{{ route('marketing.reports.content-calendar.files.delete', ['id' => $item->id, 'fileId' => $f->id]) }}"
                                              onsubmit="return confirm('Xóa file này nhé?');"
                                              style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger cc-mini" title="Xóa file">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="cc-empty">
                            <div class="cc-empty-ico"><i class="bi bi-image"></i></div>
                            <div class="fw-semibold">Chưa có file nào</div>
                            <div class="text-muted small">Hãy upload ảnh/file ngay phía trên (kéo thả hoặc bấm chọn file).</div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- FEEDBACK (Facebook-like) --}}
            <div class="card cc-card mt-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="cc-card-title">
                        <i class="bi bi-chat-dots"></i> Feedback
                    </span>
                    <span class="cc-hint small text-muted">Góp ý dạng comment (có thể kèm ảnh).</span>
                </div>

                <div class="card-body">

                    {{-- FORM GỬI FEEDBACK (comment cha) --}}
                    <form method="POST"
                          action="{{ route('marketing.reports.content-calendar.feedback.store', $item->id) }}"
                          enctype="multipart/form-data"
                          class="cc-fb-form mb-3">
                        @csrf

                        <input type="hidden" name="parent_id" value="">

                        <div class="cc-fb-compose">
                            <div class="cc-fb-avatar cc-fb-avatar--me" title="{{ $authName }}">
                                {{ $authInitial }}
                            </div>

                            <div class="cc-fb-box">
                                <textarea name="message"
                                          class="form-control cc-input cc-fb-input"
                                          rows="2"
                                          placeholder="Viết bình luận... (có thể kèm ảnh)"
                                          required></textarea>

                                <div class="cc-fb-actions">
                                    <label class="cc-fb-attach">
                                        <input type="file" name="image" accept="image/*" class="d-none">
                                        <i class="bi bi-image"></i> Ảnh
                                    </label>

                                    <button class="btn btn-ego cc-btn-sm" type="submit">
                                        <i class="bi bi-send"></i> Gửi
                                    </button>
                                </div>

                                <div class="cc-fb-preview d-none">
                                    <img alt="preview">
                                    <button type="button" class="cc-fb-remove" title="Bỏ ảnh">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    {{-- DANH SÁCH FEEDBACK (tree) --}}
                    @if($feedbackTreeLocal && $feedbackTreeLocal->count())
                        <div class="cc-fb-list">
                            @foreach($feedbackTreeLocal as $fb)
                                @php
                                    $name = $fb->user->name ?? 'User';
                                    $uid  = $fb->user_id ?? null;
                                    $initial = mb_substr($name ?: 'U', 0, 1);
                                    $timeText = $fb->created_at ? Carbon::parse($fb->created_at)->diffForHumans() : '';
                                    $imgUrl = !empty($fb->image_path) ? asset('storage/'.$fb->image_path) : null;
                                    $canManage = ($authId && $uid && ((int)$authId === (int)$uid));
                                    $children = $fb->children ?? collect();
                                @endphp

                                <div class="cc-fb-item" id="fb-{{ $fb->id }}">
                                    <div class="cc-fb-row">
                                        <div class="cc-fb-avatar" title="{{ $name }}">{{ $initial }}</div>

                                        <div class="cc-fb-main">
                                            <div class="cc-fb-bubble">
                                                <div class="cc-fb-name">{{ $name }}</div>

                                                <div class="cc-fb-msg" data-fb-message="{{ $fb->id }}">{{ $fb->message }}</div>

                                                @if($imgUrl)
                                                    <div class="cc-fb-img">
                                                        <a href="{{ $imgUrl }}" target="_blank" rel="noopener">
                                                            <img src="{{ $imgUrl }}" alt="feedback image">
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="cc-fb-meta2">
                                                <button type="button" class="cc-fb-link" disabled>Thích</button>
                                                <span class="cc-fb-dot">·</span>

                                                <button type="button"
                                                        class="cc-fb-link js-reply"
                                                        data-parent-id="{{ $fb->id }}">
                                                    Trả lời
                                                </button>

                                                <span class="cc-fb-dot">·</span>
                                                <span class="cc-fb-time">{{ $timeText }}</span>
                                            </div>

                                            {{-- Reply form (ẩn/hiện) --}}
                                            <div class="cc-reply-wrap d-none" data-reply-wrap="{{ $fb->id }}">
                                                <form method="POST"
                                                      action="{{ route('marketing.reports.content-calendar.feedback.store', $item->id) }}"
                                                      enctype="multipart/form-data"
                                                      class="cc-fb-form cc-fb-form--reply mt-2">
                                                    @csrf
                                                    <input type="hidden" name="parent_id" value="{{ $fb->id }}">

                                                    <div class="cc-fb-compose">
                                                        <div class="cc-fb-avatar cc-fb-avatar--me" title="{{ $authName }}">
                                                            {{ $authInitial }}
                                                        </div>

                                                        <div class="cc-fb-box">
                                                            <textarea name="message"
                                                                      class="form-control cc-input cc-fb-input"
                                                                      rows="2"
                                                                      placeholder="Trả lời..."
                                                                      required></textarea>

                                                            <div class="cc-fb-actions">
                                                                <label class="cc-fb-attach">
                                                                    <input type="file" name="image" accept="image/*" class="d-none">
                                                                    <i class="bi bi-image"></i> Ảnh
                                                                </label>

                                                                <div class="d-flex gap-2">
                                                                    <button class="btn btn-ego cc-btn-sm" type="submit">
                                                                        <i class="bi bi-send"></i> Gửi
                                                                    </button>
                                                                    <button class="btn btn-outline-secondary cc-btn-sm js-cancel-reply" type="button"
                                                                            data-parent-id="{{ $fb->id }}">
                                                                        Huỷ
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <div class="cc-fb-preview d-none">
                                                                <img alt="preview">
                                                                <button type="button" class="cc-fb-remove" title="Bỏ ảnh">
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>

                                            {{-- Inline edit form (ẩn/hiện) --}}
                                            <div class="cc-edit-wrap d-none" data-edit-wrap="{{ $fb->id }}">
                                                <form method="POST"
                                                      action="{{ route('marketing.reports.content-calendar.feedback.update', ['id' => $item->id, 'feedbackId' => $fb->id]) }}"
                                                      class="mt-2">
                                                    @csrf
                                                    @method('PUT')

                                                    <textarea name="message"
                                                              class="form-control cc-input"
                                                              rows="2"
                                                              required>{{ $fb->message }}</textarea>

                                                    <div class="d-flex gap-2 mt-2">
                                                        <button class="btn btn-ego cc-btn-sm" type="submit">
                                                            <i class="bi bi-save2"></i> Lưu
                                                        </button>
                                                        <button class="btn btn-outline-secondary cc-btn-sm js-cancel-edit"
                                                                type="button"
                                                                data-fb-id="{{ $fb->id }}">Huỷ</button>
                                                    </div>
                                                </form>
                                            </div>

                                            {{-- Replies --}}
                                            @if($children && $children->count())
                                                <div class="cc-replies mt-2">
                                                    @foreach($children as $ch)
                                                        @php
                                                            $cname = $ch->user->name ?? 'User';
                                                            $cuid  = $ch->user_id ?? null;
                                                            $cinitial = mb_substr($cname ?: 'U', 0, 1);
                                                            $ctimeText = $ch->created_at ? Carbon::parse($ch->created_at)->diffForHumans() : '';
                                                            $cimgUrl = !empty($ch->image_path) ? asset('storage/'.$ch->image_path) : null;
                                                            $ccanManage = ($authId && $cuid && ((int)$authId === (int)$cuid));
                                                        @endphp

                                                        <div class="cc-fb-item cc-fb-item--child" id="fb-{{ $ch->id }}">
                                                            <div class="cc-fb-row">
                                                                <div class="cc-fb-avatar" title="{{ $cname }}">{{ $cinitial }}</div>

                                                                <div class="cc-fb-main">
                                                                    <div class="cc-fb-bubble">
                                                                        <div class="cc-fb-name">{{ $cname }}</div>
                                                                        <div class="cc-fb-msg" data-fb-message="{{ $ch->id }}">{{ $ch->message }}</div>

                                                                        @if($cimgUrl)
                                                                            <div class="cc-fb-img">
                                                                                <a href="{{ $cimgUrl }}" target="_blank" rel="noopener">
                                                                                    <img src="{{ $cimgUrl }}" alt="feedback image">
                                                                                </a>
                                                                            </div>
                                                                        @endif
                                                                    </div>

                                                                    <div class="cc-fb-meta2">
                                                                        <button type="button" class="cc-fb-link" disabled>Thích</button>
                                                                        <span class="cc-fb-dot">·</span>
                                                                        <span class="cc-fb-time">{{ $ctimeText }}</span>
                                                                    </div>

                                                                    {{-- Child inline edit --}}
                                                                    <div class="cc-edit-wrap d-none" data-edit-wrap="{{ $ch->id }}">
                                                                        <form method="POST"
                                                                              action="{{ route('marketing.reports.content-calendar.feedback.update', $ch->id) }}"
                                                                              class="mt-2">
                                                                            @csrf
                                                                            @method('PUT')

                                                                            <textarea name="message"
                                                                                      class="form-control cc-input"
                                                                                      rows="2"
                                                                                      required>{{ $ch->message }}</textarea>

                                                                            <div class="d-flex gap-2 mt-2">
                                                                                <button class="btn btn-ego cc-btn-sm" type="submit">
                                                                                    <i class="bi bi-save2"></i> Lưu
                                                                                </button>
                                                                                <button class="btn btn-outline-secondary cc-btn-sm js-cancel-edit"
                                                                                        type="button"
                                                                                        data-fb-id="{{ $ch->id }}">Huỷ</button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>

                                                                <div class="cc-fb-more">
                                                                    @if($ccanManage)
                                                                        <button type="button"
                                                                                class="cc-fb-morebtn js-more"
                                                                                title="Tuỳ chọn"
                                                                                data-menu-id="menu-{{ $ch->id }}">
                                                                            <i class="bi bi-three-dots"></i>
                                                                        </button>

                                                                        <div class="cc-menu d-none" id="menu-{{ $ch->id }}">
                                                                            <button type="button"
                                                                                    class="cc-menu-item js-edit"
                                                                                    data-fb-id="{{ $ch->id }}">Sửa</button>

                                                                            <form method="POST"
                                                                                  action="{{ route('marketing.reports.content-calendar.feedback.delete', ['id' => $item->id, 'feedbackId' => $ch->id]) }}"
                                                                                  onsubmit="return confirm('Xóa feedback này nhé?');">
                                                                                @csrf
                                                                                @method('DELETE')
                                                                                <button type="submit" class="cc-menu-item cc-menu-item--danger">Xoá</button>
                                                                            </form>
                                                                        </div>
                                                                    @else
                                                                        <button type="button" class="cc-fb-morebtn" title="Tuỳ chọn" disabled>
                                                                            <i class="bi bi-three-dots"></i>
                                                                        </button>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                        </div>

                                        <div class="cc-fb-more">
                                            @if($canManage)
                                                <button type="button"
                                                        class="cc-fb-morebtn js-more"
                                                        title="Tuỳ chọn"
                                                        data-menu-id="menu-{{ $fb->id }}">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>

                                                <div class="cc-menu d-none" id="menu-{{ $fb->id }}">
                                                    <button type="button"
                                                            class="cc-menu-item js-edit"
                                                            data-fb-id="{{ $fb->id }}">Sửa</button>

                                                    <form method="POST"
                                                          action="{{ route('marketing.reports.content-calendar.feedback.delete', ['id' => $item->id, 'feedbackId' => $fb->id]) }}"
                                                          onsubmit="return confirm('Xóa feedback này nhé?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="cc-menu-item cc-menu-item--danger">Xoá</button>
                                                    </form>
                                                </div>
                                            @else
                                                <button type="button" class="cc-fb-morebtn" title="Tuỳ chọn" disabled>
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="cc-empty">
                            <div class="cc-empty-ico"><i class="bi bi-chat-left-dots"></i></div>
                            <div class="fw-semibold">Chưa có feedback nào</div>
                            <div class="text-muted small">Khi có comment/ảnh, hệ thống sẽ hiển thị ở đây.</div>
                        </div>
                    @endif

                </div>
            </div>

        </div> {{-- end col-lg-8 --}}

        {{-- RIGHT --}}
        <div class="col-lg-4">

            {{-- Info + Update --}}
            <div class="card mb-3 cc-card cc-sticky">
                <div class="card-header">
                    <span class="cc-card-title"><i class="bi bi-info-circle"></i> Thông tin</span>
                </div>

                <div class="card-body small">

                    <form method="POST" action="{{ route('marketing.reports.content-calendar.update', $item->id) }}" class="cc-side-form">
                        @csrf
                        @method('PUT')

                        <div class="cc-side-row">
                            <label class="form-label fw-semibold mb-1">Ngày đăng</label>
                            <input type="date"
                                   name="publish_date"
                                   class="form-control cc-input"
                                   value="{{ \Carbon\Carbon::parse($item->publish_date)->format('Y-m-d') }}">
                        </div>

                        <div class="cc-side-row">
                            <label class="form-label fw-semibold mb-1">Trạng thái</label>
                            <select name="status" class="form-control cc-input">
                                <option value="draft"     {{ $item->status==='draft' ? 'selected' : '' }}>Nháp</option>
                                <option value="scheduled" {{ $item->status==='scheduled' ? 'selected' : '' }}>Lên lịch</option>
                                <option value="submitted" {{ $item->status==='submitted' ? 'selected' : '' }}>Chờ duyệt</option>
                                <option value="approved"  {{ $item->status==='approved' ? 'selected' : '' }}>Đã duyệt</option>
                                <option value="posted"    {{ $item->status==='posted' ? 'selected' : '' }}>Đã đăng</option>
                                <option value="rejected"  {{ $item->status==='rejected' ? 'selected' : '' }}>Từ chối</option>
                            </select>
                        </div>

                        <div class="cc-side-row">
                            <label class="form-label fw-semibold mb-1">Link (Drive/Facebook...)</label>
                            <input type="url"
                                   name="link"
                                   class="form-control cc-input"
                                   placeholder="https://..."
                                   value="{{ $item->link }}">
                            <div class="text-muted small mt-1">Nhập đúng https:// để bấm mở link.</div>
                        </div>

                        <button class="btn btn-ego cc-btn w-100" type="submit">
                            <i class="bi bi-save2"></i> Cập nhật
                        </button>
                    </form>

                    {{-- Metrics (view + edit on same page) --}}
                    <div class="cc-metric-card" id="ccMetricCard" data-week-start="{{ $weekStart }}">
                        <div class="cc-metric-head">
                            <div class="cc-metric-title">
                                <i class="bi bi-bar-chart"></i> Chỉ số bài viết
                            </div>
                            <div class="cc-metric-sub">
                                Tuần: {{ \Carbon\Carbon::parse($weekStart)->format('d/m') }}
                            </div>
                        </div>

                        <div class="cc-metric-grid" id="ccMetricGrid">
                            <div class="cc-metric-item">
                                <div class="cc-metric-label">Reach</div>
                                <div class="cc-metric-value" id="ccMReach">{{ number_format($w->reach ?? 0) }}</div>
                            </div>

                            <div class="cc-metric-item">
                                <div class="cc-metric-label">Views</div>
                                <div class="cc-metric-value" id="ccMViews">{{ number_format($w->views ?? 0) }}</div>
                            </div>

                            <div class="cc-metric-item">
                                <div class="cc-metric-label">Engagement</div>
                                <div class="cc-metric-value" id="ccMEng">{{ number_format($eng) }}</div>
                            </div>

                            <div class="cc-metric-item">
                                <div class="cc-metric-label">Leads</div>
                                <div class="cc-metric-value" id="ccMLeads">{{ number_format($w->leads ?? 0) }}</div>
                            </div>

                            <div class="cc-metric-item">
                                <div class="cc-metric-label">Phút Livestream</div>
                                <div class="cc-metric-value" id="ccMDuration">{{ number_format($w->duration_min ?? 0) }}</div>
                            </div>
                        </div>

                        <div class="cc-metric-note text-muted small">
                            (Số liệu lấy theo tuần của ngày đăng.)
                        </div>

                        <hr class="cc-hr">

                        <div class="cc-metric-edit">
                            <div class="fw-semibold mb-2">Chỉnh sửa chỉ số (tuần này)</div>

                            <form id="ccMetricForm">
                                @csrf
                                <input type="hidden" name="week_start" value="{{ $weekStart }}">

                                <div class="cc-metric-formgrid">
                                    <div>
                                        <label class="form-label small fw-semibold mb-1">Reach</label>
                                        <input type="number" min="0" name="reach" class="form-control cc-input" value="{{ (int)($w->reach ?? 0) }}">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-semibold mb-1">Views</label>
                                        <input type="number" min="0" name="views" class="form-control cc-input" value="{{ (int)($w->views ?? 0) }}">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-semibold mb-1">Likes</label>
                                        <input type="number" min="0" name="likes" class="form-control cc-input" value="{{ (int)($w->likes ?? 0) }}">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-semibold mb-1">Comments</label>
                                        <input type="number" min="0" name="comments" class="form-control cc-input" value="{{ (int)($w->comments ?? 0) }}">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-semibold mb-1">Shares</label>
                                        <input type="number" min="0" name="shares" class="form-control cc-input" value="{{ (int)($w->shares ?? 0) }}">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-semibold mb-1">Leads</label>
                                        <input type="number" min="0" name="leads" class="form-control cc-input" value="{{ (int)($w->leads ?? 0) }}">
                                    </div>

                                    <div>
                                        <label class="form-label small fw-semibold mb-1">Phút Livestream</label>
                                        <input type="number" min="0" name="duration_min" class="form-control cc-input"
                                               value="{{ (int)($w->duration_min ?? 0) }}">
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <label class="form-label small fw-semibold mb-1">Ghi chú</label>
                                    <textarea name="note" rows="2" class="form-control cc-input" placeholder="Ghi chú...">{{ $w->note ?? '' }}</textarea>
                                </div>

                                <button class="btn btn-ego cc-btn w-100 mt-3" type="submit" id="ccMetricSaveBtn">
                                    <i class="bi bi-save2"></i> Lưu chỉ số
                                </button>

                                <div class="cc-toast text-muted small mt-2 d-none" id="ccMetricToast">—</div>
                            </form>
                        </div>
                    </div>

                    {{-- Quick actions --}}
                    <div class="card mb-3 cc-card mt-3">
                        <div class="card-header">
                            <span class="cc-card-title"><i class="bi bi-lightning-charge"></i> Hành động</span>
                        </div>
                        <div class="card-body d-grid gap-2">
                            <button class="btn btn-ego cc-btn w-100" type="button" id="ccSubmitEditor2">
                                <i class="bi bi-save2"></i> Lưu nội dung
                            </button>

                            <button class="btn btn-outline-secondary cc-btn w-100" type="button" id="ccScrollUpload">
                                <i class="bi bi-cloud-arrow-up"></i> Upload ảnh/file
                            </button>

                            <a href="{{ route('marketing.reports.content-calendar') }}"
                               class="btn btn-outline-secondary cc-btn w-100">
                                <i class="bi bi-list-ul"></i> Về danh sách
                            </a>
                        </div>
                    </div>

                    {{-- Mini lists --}}
                    <div class="card mb-3 cc-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="cc-card-title"><i class="bi bi-clock-history"></i> Bài viết sắp lên lịch</div>
                            <span class="cc-chip">Top 8</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="cc-mini-list">
                                @if(isset($upcoming) && $upcoming->count())
                                    @foreach($upcoming as $u)
                                        <a class="cc-mini-item" href="{{ route('marketing.reports.content-calendar.show', $u->id) }}">
                                            <div class="cc-mini-title">{{ $u->title }}</div>
                                            <div class="cc-mini-sub">
                                                {{ Carbon::parse($u->publish_date)->format('d/m') }} • {{ ucfirst((string)$u->platform) }}
                                            </div>
                                        </a>
                                    @endforeach
                                @else
                                    <div class="cc-mini-empty">Chưa có nội dung sắp lên lịch</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3 cc-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="cc-card-title"><i class="bi bi-file-earmark"></i> Nháp gần đây</div>
                            <span class="cc-chip">Top 8</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="cc-mini-list">
                                @if(isset($drafts) && $drafts->count())
                                    @foreach($drafts as $u)
                                        <a class="cc-mini-item" href="{{ route('marketing.reports.content-calendar.show', $u->id) }}">
                                            <div class="cc-mini-title">{{ $u->title }}</div>
                                            <div class="cc-mini-sub">
                                                {{ Carbon::parse($u->publish_date)->format('d/m') }} • {{ ucfirst((string)$u->platform) }}
                                            </div>
                                        </a>
                                    @endforeach
                                @else
                                    <div class="cc-mini-empty">Không có nháp</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="card cc-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="cc-card-title"><i class="bi bi-shield-check"></i> Chờ duyệt</div>
                            <span class="cc-chip">Top 8</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="cc-mini-list">
                                @if(isset($submitted) && $submitted->count())
                                    @foreach($submitted as $u)
                                        <a class="cc-mini-item" href="{{ route('marketing.reports.content-calendar.show', $u->id) }}">
                                            <div class="cc-mini-title">{{ $u->title }}</div>
                                            <div class="cc-mini-sub">
                                                {{ Carbon::parse($u->publish_date)->format('d/m') }} • {{ ucfirst((string)$u->platform) }}
                                            </div>
                                        </a>
                                    @endforeach
                                @else
                                    <div class="cc-mini-empty">Không có nội dung chờ duyệt</div>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div> {{-- end col-lg-4 --}}
    </div>
</div>
@endsection

@push('styles')
<style>
:root{
  --cc-bg:#f3f7fb;
  --cc-card:#ffffff;
  --cc-border:rgba(15,23,42,.09);
  --cc-text:#0f172a;
  --cc-muted:rgba(15,23,42,.62);
  --cc-shadow-sm:0 6px 18px rgba(15,23,42,.06);
  --cc-radius:16px;

  --cc-primary:#0ea5a6;
  --cc-primary-2:#12b3b4;
}

body{
  background: var(--cc-bg);
  font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
  font-weight: 400;
}
.cc-show-page{ color: var(--cc-text); font-size: 14px; line-height: 1.45; }

.cc-page-header{
  position: sticky;
  top: 12px;
  z-index: 6;
  background: rgba(243,247,251,.92);
  border: 1px solid var(--cc-border);
  border-radius: var(--cc-radius);
  padding: 16px;
  box-shadow: var(--cc-shadow-sm);
  backdrop-filter: blur(10px);
}
.cc-title{ font-size: 20px; font-weight: 600; line-height: 1.25; }
.cc-subline{ color: var(--cc-muted); display:flex; flex-wrap:wrap; gap: 8px; align-items:center; }
.cc-dot{ color: rgba(15,23,42,.35); }

.cc-pill{
  display:inline-flex;
  align-items:center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(15,23,42,.08);
  background: rgba(255,255,255,.96);
  font-weight: 600;
  font-size: 12px;
  color: rgba(15,23,42,.78);
}
.cc-pill-muted{
  background: rgba(15,23,42,.03);
  color: rgba(15,23,42,.62);
}

.cc-status{
  border-radius: 999px;
  padding: 8px 12px;
  font-weight: 600;
  border: 1px solid rgba(15,23,42,.10);
  white-space: nowrap;
}
.cc-badge-draft{ background: rgba(100,116,139,.14); color:#334155; }
.cc-badge-scheduled{ background: rgba(245,158,11,.16); color:#92400e; }
.cc-badge-posted{ background: rgba(34,197,94,.14); color:#166534; }
.cc-badge-submitted{ background: rgba(59,130,246,.14); color:#1d4ed8; }
.cc-badge-approved{ background: rgba(99,102,241,.14); color:#4338ca; }
.cc-badge-rejected{ background: rgba(239,68,68,.14); color:#991b1b; }
.cc-badge-default{ background: rgba(15,23,42,.06); color:#0f172a; }

.btn-ego{
  background: var(--cc-primary);
  border: 1px solid rgba(0,0,0,0);
  color: #fff;
  font-weight: 600;
  border-radius: 12px;
  padding: 10px 14px;
  box-shadow: 0 10px 18px rgba(14,165,166,.18);
}
.btn-ego:hover{ background: var(--cc-primary-2); color:#fff; }
.cc-btn{ border-radius: 12px; font-weight: 600; padding: 10px 12px; }
.cc-btn-sm{ border-radius: 12px; font-weight: 600; padding: 8px 10px; }

.cc-card{
  border: 1px solid var(--cc-border) !important;
  border-radius: var(--cc-radius) !important;
  background: var(--cc-card);
  box-shadow: var(--cc-shadow-sm);
  overflow: hidden;
}
.cc-card .card-header{
  background: transparent !important;
  border-bottom: 1px solid rgba(15,23,42,.08) !important;
  padding: 14px 16px !important;
}
.cc-card-title{ font-weight: 600; display:inline-flex; align-items:center; gap: 8px; }
.cc-card .card-body{ padding: 16px !important; }

.cc-tabs .nav-link{
  border-radius: 999px;
  font-weight: 600;
  color: rgba(15,23,42,.70);
}
.cc-tabs .nav-link.active{
  background: rgba(15,23,42,.92);
  color: #fff;
}

.cc-editor{
  border-radius: 14px;
  border: 1px solid rgba(15,23,42,.12);
  background: rgba(255,255,255,.95);
  padding: 14px;
  line-height: 1.6;
  font-size: 14px;
}
.cc-editor:focus{
  border-color: rgba(14,165,166,.35);
  box-shadow: 0 0 0 .2rem rgba(14,165,166,.12);
}

.cc-preview-wrap{
  border: 1px solid rgba(15,23,42,.10);
  border-radius: 14px;
  background: rgba(255,255,255,.95);
  padding: 14px;
}
.cc-preview{ white-space: pre-wrap; line-height: 1.65; color: rgba(15,23,42,.85); }

.cc-input{
  border-radius: 12px !important;
  border: 1px solid rgba(15,23,42,.12) !important;
  background: rgba(255,255,255,.96) !important;
  padding: 10px 12px !important;
}
.cc-input:focus{
  border-color: rgba(14,165,166,.35) !important;
  box-shadow: 0 0 0 .2rem rgba(14,165,166,.12) !important;
}

.cc-hr{ margin: 14px 0; border-top: 1px solid rgba(15,23,42,.10); }

.cc-dropzone{
  border: 1px dashed rgba(15,23,42,.22);
  border-radius: 16px;
  padding: 18px;
  text-align: center;
  background: rgba(255,255,255,.75);
  cursor: pointer;
  transition: background .12s ease, border-color .12s ease, transform .12s ease;
}
.cc-dropzone:hover{
  background: rgba(14,165,166,.06);
  border-color: rgba(14,165,166,.35);
  transform: translateY(-1px);
}
.cc-dropzone.dragover{
  background: rgba(14,165,166,.08);
  border-color: rgba(14,165,166,.45);
}
.cc-dropzone-ico{ font-size: 26px; color: rgba(15,23,42,.55); margin-bottom: 6px; }

.cc-upload-meta{ display:flex; gap: 14px; align-items: stretch; justify-content: space-between; }
.cc-upload-left{ flex: 1; min-width: 0; }
.cc-upload-right{ width: 220px; }
@media (max-width: 575px){
  .cc-upload-meta{ flex-direction: column; }
  .cc-upload-right{ width: 100%; }
}

.cc-filepicked{
  border: 1px solid rgba(15,23,42,.10);
  border-radius: 14px;
  padding: 12px;
  background: rgba(255,255,255,.92);
}
.cc-filepicked-name{ font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cc-img-preview{ margin-top: 10px; border-radius: 14px; overflow: hidden; border: 1px solid rgba(15,23,42,.10); background: rgba(15,23,42,.02); }
.cc-img-preview img{ width: 100%; height: 220px; object-fit: cover; display:block; }

.cc-files-grid{ display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 991px){ .cc-files-grid{ grid-template-columns: 1fr; } }

.cc-file-card{
  display:flex;
  gap: 12px;
  align-items: center;
  padding: 12px;
  border-radius: 16px;
  border: 1px solid rgba(15,23,42,.10);
  background: rgba(255,255,255,.92);
  color: var(--cc-text);
  transition: transform .12s ease, border-color .12s ease, box-shadow .12s ease;
}
.cc-file-card:hover{
  transform: translateY(-1px);
  border-color: rgba(14,165,166,.30);
  box-shadow: var(--cc-shadow-sm);
}
.cc-file-thumb{
  width: 74px;
  height: 56px;
  border-radius: 14px;
  overflow:hidden;
  border: 1px solid rgba(15,23,42,.10);
  background: rgba(15,23,42,.02);
  flex: 0 0 auto;
  display:flex;
  align-items:center;
  justify-content:center;
}
.cc-file-thumb img{ width:100%; height:100%; object-fit: cover; display:block; }
.cc-file-icon{ font-size: 22px; color: rgba(15,23,42,.55); }
.cc-file-info{ min-width: 0; flex: 1; }
.cc-file-name{ font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cc-file-actions{ display:flex; align-items:center; gap: 10px; }
.cc-mini{ border-radius: 10px; padding: 6px 8px; font-weight: 600; }

.cc-empty{
  border: 1px dashed rgba(15,23,42,.18);
  border-radius: 16px;
  padding: 18px;
  text-align:center;
  background: rgba(255,255,255,.70);
}
.cc-empty-ico{ font-size: 26px; margin-bottom: 8px; color: rgba(15,23,42,.55); }

.cc-chip{
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid rgba(15,23,42,.10);
  color: rgba(15,23,42,.60);
  background: rgba(255,255,255,.92);
}
.cc-mini-list{ display:flex; flex-direction:column; }
.cc-mini-item{
  padding: 12px 16px;
  border-top: 1px solid rgba(15,23,42,.06);
  text-decoration:none;
  color: var(--cc-text);
  transition: background .12s ease;
}
.cc-mini-item:hover{ background: rgba(14,165,166,.06); }
.cc-mini-title{ font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cc-mini-sub{ font-size: 12px; color: var(--cc-muted); }
.cc-mini-empty{ padding: 14px 16px; color: var(--cc-muted); font-size: 13px; }

.cc-side-form .cc-side-row{ margin-bottom: 10px; }

.cc-metric-card{
  border: 1px solid rgba(15,23,42,.10);
  border-radius: 16px;
  padding: 12px;
  background: rgba(255,255,255,.94);
  box-shadow: 0 6px 18px rgba(15,23,42,.06);
}
.cc-metric-head{
  display:flex;
  align-items:baseline;
  justify-content:space-between;
  gap: 10px;
  padding-bottom: 10px;
  border-bottom: 1px dashed rgba(15,23,42,.12);
  margin-bottom: 10px;
}
.cc-metric-title{
  font-weight: 800;
  font-size: 13px;
  display:flex;
  align-items:center;
  gap: 8px;
  color: rgba(15,23,42,.88);
}
.cc-metric-sub{
  font-size: 12px;
  color: rgba(15,23,42,.55);
  font-weight: 700;
}
.cc-metric-grid{
  display:grid;
  grid-template-columns: repeat(2, minmax(0,1fr));
  gap: 10px;
}
.cc-metric-item{
  border: 1px solid rgba(15,23,42,.10);
  border-radius: 14px;
  padding: 10px;
  background: rgba(15,23,42,.02);
}
.cc-metric-label{ font-size: 12px; color: rgba(15,23,42,.60); font-weight: 800; }
.cc-metric-value{ font-size: 18px; font-weight: 900; color: rgba(15,23,42,.90); margin-top: 2px; }
.cc-metric-note{ margin-top: 10px; }

.cc-metric-formgrid{
  display:grid;
  grid-template-columns: repeat(2, minmax(0,1fr));
  gap: 10px;
}

/* ===== Feedback Facebook-like ===== */
.cc-fb-compose{ display:flex; gap:10px; align-items:flex-start; }
.cc-fb-box{ flex: 1; min-width:0; }

.cc-fb-avatar{
  width: 36px;
  height: 36px;
  border-radius: 999px;
  border: 1px solid rgba(15,23,42,.12);
  background: rgba(15,23,42,.06);
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight: 900;
  color: rgba(15,23,42,.75);
  flex: 0 0 auto;
}
.cc-fb-avatar--me{ background: rgba(14,165,166,.12); border-color: rgba(14,165,166,.30); color: rgba(15,23,42,.88); }

.cc-fb-actions{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap: 10px;
  margin-top: 10px;
}
.cc-fb-attach{
  display:inline-flex;
  align-items:center;
  gap: 8px;
  border: 1px solid rgba(15,23,42,.10);
  background: rgba(255,255,255,.92);
  padding: 8px 10px;
  border-radius: 999px;
  cursor: pointer;
  font-weight: 800;
  color: rgba(15,23,42,.70);
}
.cc-fb-attach:hover{ background: rgba(14,165,166,.06); border-color: rgba(14,165,166,.28); }

.cc-fb-preview{
  position: relative;
  margin-top: 10px;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid rgba(15,23,42,.10);
  background: rgba(15,23,42,.02);
  max-width: 420px;
}
.cc-fb-preview img{
  width:100%;
  height: 220px;
  object-fit: cover;
  display:block;
}
.cc-fb-remove{
  position:absolute;
  top: 8px;
  right: 8px;
  border: 0;
  background: rgba(15,23,42,.75);
  color:#fff;
  border-radius: 10px;
  padding: 6px 8px;
}

.cc-fb-item{ margin-bottom: 12px; }
.cc-fb-item--child{ margin-left: 46px; }
.cc-fb-row{ display:flex; gap:10px; align-items:flex-start; }
.cc-fb-main{ flex:1; min-width:0; position: relative; }

.cc-fb-bubble{
  display:inline-block;
  background: rgba(15,23,42,.04);
  border: 1px solid rgba(15,23,42,.08);
  border-radius: 18px;
  padding: 10px 12px;
  max-width: 100%;
}
.cc-fb-name{ font-weight: 900; font-size: 13px; margin-bottom: 2px; color: rgba(15,23,42,.88); }
.cc-fb-msg{ white-space: pre-wrap; line-height: 1.55; color: rgba(15,23,42,.85); }

.cc-fb-img{
  margin-top: 10px;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid rgba(15,23,42,.10);
  background: rgba(15,23,42,.02);
  max-width: 420px;
}
.cc-fb-img img{
  width: 100%;
  height: 220px;
  object-fit: cover;
  display:block;
}

.cc-fb-meta2{
  margin-top: 6px;
  font-size: 12px;
  color: rgba(15,23,42,.60);
  display:flex;
  align-items:center;
  gap: 6px;
}
.cc-fb-link{
  border:0;
  background:transparent;
  padding:0;
  font-weight: 900;
  color: rgba(15,23,42,.60);
  cursor: pointer;
}
.cc-fb-link[disabled]{ cursor: default; opacity: .7; }
.cc-fb-dot{ color: rgba(15,23,42,.40); }
.cc-fb-time{ font-weight: 800; }

.cc-fb-more{ width: 36px; display:flex; justify-content:flex-end; position: relative; }
.cc-fb-morebtn{
  border: 0;
  background: transparent;
  border-radius: 10px;
  padding: 6px 8px;
  color: rgba(15,23,42,.55);
  cursor: pointer;
}
.cc-fb-morebtn[disabled]{ cursor: default; opacity: .6; }

.cc-replies{ border-left: 2px solid rgba(15,23,42,.08); padding-left: 10px; margin-left: 6px; }

/* Custom menu (không cần bootstrap) */
.cc-menu{
  position: absolute;
  right: 0;
  top: 34px;
  min-width: 140px;
  background: #fff;
  border: 1px solid rgba(15,23,42,.12);
  border-radius: 12px;
  box-shadow: 0 14px 30px rgba(15,23,42,.12);
  padding: 6px;
  z-index: 20;
}
.cc-menu-item{
  width: 100%;
  border: 0;
  background: transparent;
  text-align: left;
  padding: 10px 10px;
  border-radius: 10px;
  font-weight: 800;
  color: rgba(15,23,42,.78);
  cursor: pointer;
}
.cc-menu-item:hover{ background: rgba(15,23,42,.06); }
.cc-menu-item--danger{ color: #b91c1c; }
.cc-menu-item--danger:hover{ background: rgba(239,68,68,.10); }

@media (min-width: 992px){
  .cc-sticky{ position: static; }
}
</style>
@endpush

@push('scripts')
<script>
(function(){
  const $ = (s, root=document) => root.querySelector(s);
  const $$ = (s, root=document) => Array.from(root.querySelectorAll(s));

  async function copyText(text){
    try{
      await navigator.clipboard.writeText(text);
      return true;
    }catch(e){
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      return true;
    }
  }

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-cc-copy]');
    if(!btn) return;
    e.preventDefault();
    const text = btn.getAttribute('data-cc-copy') || '';
    if(!text) return;

    const old = btn.innerHTML;
    await copyText(text);
    btn.innerHTML = '<i class="bi bi-check2"></i>';
    setTimeout(() => btn.innerHTML = old, 900);
  });

  // Editor submit
  const editorForm = $('#ccEditorForm');
  const submitBtn1 = $('#ccSubmitEditor');
  const submitBtn2 = $('#ccSubmitEditor2');
  function submitEditor(){ if(editorForm) editorForm.requestSubmit(); }
  submitBtn1 && submitBtn1.addEventListener('click', submitEditor);
  submitBtn2 && submitBtn2.addEventListener('click', submitEditor);

  // Copy content
  const editor = $('#ccEditor');
  const copyContentBtn = $('#ccCopyContent');
  copyContentBtn && copyContentBtn.addEventListener('click', async () => {
    const old = copyContentBtn.innerHTML;
    await copyText(editor ? editor.value : '');
    copyContentBtn.innerHTML = '<i class="bi bi-check2"></i> Đã copy';
    setTimeout(() => copyContentBtn.innerHTML = old, 900);
  });

  // Copy title
  const copyTitleBtn = $('#ccCopyTitle');
  copyTitleBtn && copyTitleBtn.addEventListener('click', async () => {
    const text = copyTitleBtn.getAttribute('data-cc-copy') || '';
    const old = copyTitleBtn.innerHTML;
    await copyText(text);
    copyTitleBtn.innerHTML = '<i class="bi bi-check2"></i> Đã copy';
    setTimeout(() => copyTitleBtn.innerHTML = old, 900);
  });

  // Preview live
  const previewBox = $('#ccPreviewBox');
  function renderPreview(){
    if(!previewBox || !editor) return;
    previewBox.textContent = editor.value || '';
  }
  editor && editor.addEventListener('input', renderPreview);
  renderPreview();

  // Upload dropzone
  const dropzone = $('#ccDropzone');
  const fileInput = $('#ccFileInput');
  const meta = $('#ccUploadMeta');
  const pickedName = $('#ccPickedName');
  const pickedSub = $('#ccPickedSub');
  const imgWrap = $('#ccImgPreviewWrap');
  const imgPrev = $('#ccImgPreview');
  const clearPicked = $('#ccClearPicked');

  function showPicked(file){
    if(!file) return;
    meta && meta.classList.remove('d-none');
    pickedName && (pickedName.textContent = file.name);
    pickedSub && (pickedSub.textContent = `${Math.round(file.size/1024)} KB • ${file.type || 'file'}`);

    const isImg = file.type && file.type.startsWith('image/');
    if(isImg && imgWrap && imgPrev){
      imgWrap.classList.remove('d-none');
      imgPrev.src = URL.createObjectURL(file);
    }else{
      imgWrap && imgWrap.classList.add('d-none');
      if(imgPrev) imgPrev.src = '';
    }
  }

  function clearPickedFile(){
    if(fileInput) fileInput.value = '';
    meta && meta.classList.add('d-none');
    if(imgPrev) imgPrev.src = '';
  }

  dropzone && dropzone.addEventListener('click', () => fileInput && fileInput.click());

  fileInput && fileInput.addEventListener('change', () => {
    const f = fileInput.files && fileInput.files[0];
    if(f) showPicked(f);
  });

  clearPicked && clearPicked.addEventListener('click', clearPickedFile);

  if(dropzone){
    ['dragenter','dragover'].forEach(evt => {
      dropzone.addEventListener(evt, (e) => {
        e.preventDefault(); e.stopPropagation();
        dropzone.classList.add('dragover');
      });
    });

    ['dragleave','drop'].forEach(evt => {
      dropzone.addEventListener(evt, (e) => {
        e.preventDefault(); e.stopPropagation();
        dropzone.classList.remove('dragover');
      });
    });

    dropzone.addEventListener('drop', (e) => {
      const files = e.dataTransfer && e.dataTransfer.files;
      if(!files || !files.length) return;
      if(fileInput){
        fileInput.files = files;
        const f = fileInput.files[0];
        if(f) showPicked(f);
      }
    });
  }

  // Scroll to upload
  const scrollUpload = $('#ccScrollUpload');
  scrollUpload && scrollUpload.addEventListener('click', () => {
    const form = $('#ccUploadForm');
    form && form.scrollIntoView({behavior:'smooth', block:'start'});
  });

  // =========================
  // Metrics edit/save on page
  // =========================
  const metricCard = $('#ccMetricCard');
  const metricForm = $('#ccMetricForm');
  const toast = $('#ccMetricToast');
  const saveBtn = $('#ccMetricSaveBtn');

  if(metricCard && metricForm){
    const weekStart = metricCard.getAttribute('data-week-start');

    const METRIC_GET_URL  = `/marketing/reports/content-calendar/{{ $item->id }}/weekly-metrics?week_start=${encodeURIComponent(weekStart)}`;
    const METRIC_SAVE_URL = `/marketing/reports/content-calendar/{{ $item->id }}/weekly-metrics`;

    function showToast(msg, ok=true){
      if(!toast) return;
      toast.classList.remove('d-none');
      toast.textContent = msg;
      toast.style.color = ok ? 'rgba(15,23,42,.70)' : '#b91c1c';
      setTimeout(() => { toast.classList.add('d-none'); }, 2200);
    }

    function setNumber(id, val){
      const el = document.getElementById(id);
      if(el) el.textContent = (val ?? 0).toLocaleString();
    }

    async function loadMetric(){
      try{
        const res = await fetch(METRIC_GET_URL, { headers: { 'Accept':'application/json' }});
        const js = await res.json();
        if(!js || !js.ok) return;

        const d = js.data || {};
        const likes = parseInt(d.likes ?? 0, 10);
        const comments = parseInt(d.comments ?? 0, 10);
        const shares = parseInt(d.shares ?? 0, 10);
        const eng = likes + comments + shares;

        setNumber('ccMReach', d.reach ?? 0);
        setNumber('ccMViews', d.views ?? 0);
        setNumber('ccMLeads', d.leads ?? 0);
        setNumber('ccMEng', eng);
        setNumber('ccMDuration', d.duration_min ?? 0);
      }catch(e){
        // ignore
      }
    }

    loadMetric();

    metricForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const fd = new FormData(metricForm);

      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if(csrf && !fd.get('_token')) fd.append('_token', csrf);

      saveBtn && (saveBtn.disabled = true);
      try{
        const res = await fetch(METRIC_SAVE_URL, {
          method: 'POST',
          headers: { 'Accept': 'application/json' },
          body: fd
        });

        const js = await res.json();

        if(js && js.ok){
          showToast(js.message || 'Đã lưu chỉ số.', true);
          await loadMetric();
        }else{
          showToast('Lưu thất bại. Kiểm tra route/validate.', false);
        }
      }catch(err){
        showToast('Lỗi khi lưu (check route).', false);
      }finally{
        saveBtn && (saveBtn.disabled = false);
      }
    });
  }

  // Feedback image preview (cho tất cả form feedback, gồm reply)
  function bindFeedbackFormPreview(form){
    if(!form) return;
    const fileInput = form.querySelector('input[type="file"][name="image"]');
    const previewWrap = form.querySelector('.cc-fb-preview');
    const previewImg = previewWrap ? previewWrap.querySelector('img') : null;
    const removeBtn = previewWrap ? previewWrap.querySelector('.cc-fb-remove') : null;

    function clearPreview(){
      if(fileInput) fileInput.value = '';
      if(previewWrap) previewWrap.classList.add('d-none');
      if(previewImg) previewImg.src = '';
    }

    fileInput && fileInput.addEventListener('change', () => {
      const f = fileInput.files && fileInput.files[0];
      if(!f || !previewWrap || !previewImg) return clearPreview();
      previewImg.src = URL.createObjectURL(f);
      previewWrap.classList.remove('d-none');
    });

    removeBtn && removeBtn.addEventListener('click', clearPreview);
  }

  $$('.cc-fb-form').forEach(bindFeedbackFormPreview);

  // =========================
  // Reply / Edit / More menu
  // =========================
  function hideAllMenus(){
    $$('.cc-menu').forEach(m => m.classList.add('d-none'));
  }

  document.addEventListener('click', (e) => {
    // Toggle menu
    const moreBtn = e.target.closest('.js-more');
    if(moreBtn){
      e.preventDefault();
      e.stopPropagation();
      const id = moreBtn.getAttribute('data-menu-id');
      if(!id) return;
      const menu = document.getElementById(id);
      if(!menu) return;
      const isHidden = menu.classList.contains('d-none');
      hideAllMenus();
      if(isHidden) menu.classList.remove('d-none');
      return;
    }

    // Reply
    const replyBtn = e.target.closest('.js-reply');
    if(replyBtn){
      e.preventDefault();
      const pid = replyBtn.getAttribute('data-parent-id');
      if(!pid) return;

      // đóng các reply khác
      $$('[data-reply-wrap]').forEach(w => w.classList.add('d-none'));
      const wrap = document.querySelector(`[data-reply-wrap="${pid}"]`);
      if(wrap){
        wrap.classList.remove('d-none');
        const ta = wrap.querySelector('textarea[name="message"]');
        ta && ta.focus();
        // bind preview cho form reply (nếu chưa)
        const form = wrap.querySelector('form');
        form && bindFeedbackFormPreview(form);
      }
      hideAllMenus();
      return;
    }

    // Cancel reply
    const cancelReply = e.target.closest('.js-cancel-reply');
    if(cancelReply){
      e.preventDefault();
      const pid = cancelReply.getAttribute('data-parent-id');
      const wrap = document.querySelector(`[data-reply-wrap="${pid}"]`);
      if(wrap) wrap.classList.add('d-none');
      return;
    }

    // Edit
    const editBtn = e.target.closest('.js-edit');
    if(editBtn){
      e.preventDefault();
      const fid = editBtn.getAttribute('data-fb-id');
      if(!fid) return;

      // đóng tất cả edit khác
      $$('[data-edit-wrap]').forEach(w => w.classList.add('d-none'));

      const ew = document.querySelector(`[data-edit-wrap="${fid}"]`);
      if(ew){
        ew.classList.remove('d-none');
        const ta = ew.querySelector('textarea[name="message"]');
        ta && ta.focus();
      }
      hideAllMenus();
      return;
    }

    // Cancel edit
    const cancelEdit = e.target.closest('.js-cancel-edit');
    if(cancelEdit){
      e.preventDefault();
      const fid = cancelEdit.getAttribute('data-fb-id');
      const ew = document.querySelector(`[data-edit-wrap="${fid}"]`);
      if(ew) ew.classList.add('d-none');
      return;
    }

    // click ngoài menu => đóng menu
    hideAllMenus();
  });

  // ESC đóng menu + đóng reply/edit
  document.addEventListener('keydown', (e) => {
    if(e.key !== 'Escape') return;
    hideAllMenus();
    $$('[data-reply-wrap]').forEach(w => w.classList.add('d-none'));
    $$('[data-edit-wrap]').forEach(w => w.classList.add('d-none'));
  });

})();
</script>
@endpush