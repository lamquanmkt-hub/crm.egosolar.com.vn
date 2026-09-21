@extends('layouts.app')

@section('title', 'Chi tiết báo cáo ngày')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/ego-technical-work.css') }}?v={{ file_exists(public_path('css/ego-technical-work.css')) ? filemtime(public_path('css/ego-technical-work.css')) : '1.0.0' }}">
@endpush

@section('content')
<div class="container-fluid py-3 tw-wrap">

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">
                Báo cáo ngày {{ optional($report->report_date)->format('d/m/Y') }}
                <span class="badge text-bg-{{ $report->statusTone() }} align-middle ms-1">{{ $report->statusLabel() }}</span>
            </h1>
            <p class="tw-head__sub">
                {{ $report->work_title ?: $report->sourceLabel() }}
                @if($report->site_name) · {{ $report->site_name }} @endif
                · người báo cáo: {{ $report->user?->name ?: $report->user_name }}
            </p>
        </div>
        <div class="tw-head__actions">
            <a href="{{ route('technical.daily-reports.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Danh sách
            </a>
            @if($canEdit)
                <a href="{{ route('technical.daily-reports.edit', $report) }}" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Sửa báo cáo
                </a>
            @endif
        </div>
    </div>

    @include('technical.workboard.partials.flash')

    @if($report->status === \App\Models\Technical\TechnicalDailyReport::STATUS_REVISION && $report->review_note)
        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="bi bi-arrow-counterclockwise"></i>
            <div><strong>Yêu cầu sửa:</strong> {{ $report->review_note }}</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="tw-card">
                <div class="tw-card__head"><h2 class="tw-card__title">Nội dung</h2></div>
                <div class="tw-card__body">
                    <dl class="tw-def">
                        <dt>Nguồn công việc</dt>
                        <dd>
                            <span class="tw-source tw-source--{{ $report->source_type === 'task' ? 'task' : ($report->source_type === 'maintenance' ? 'maintenance' : 'project') }}">
                                {{ $report->sourceLabel() }}
                            </span>
                        </dd>

                        <dt>Đã thực hiện</dt>
                        <dd>{{ $report->content }}</dd>

                        <dt>% hoàn thành</dt>
                        <dd>{{ (int) $report->progress_percent }}%</dd>

                        <dt>Số giờ thực hiện</dt>
                        <dd>{{ $report->work_hours !== null ? rtrim(rtrim((string) $report->work_hours, '0'), '.').' giờ' : 'Không ghi nhận' }}</dd>

                        <dt>Vật tư đã dùng</dt>
                        <dd>{{ $report->materials_note ?: 'Không có' }}</dd>

                        <dt>Vấn đề phát sinh</dt>
                        <dd>{{ $report->issues_note ?: 'Không có' }}</dd>

                        <dt>Kế hoạch tiếp theo</dt>
                        <dd>{{ $report->next_plan ?: 'Không có' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="tw-card">
                <div class="tw-card__head"><h2 class="tw-card__title">Ảnh / file minh chứng</h2></div>
                <div class="tw-card__body">
                    @if($report->files->isEmpty())
                        <p class="text-muted mb-0 small">Báo cáo này không có file đính kèm.</p>
                    @else
                        <div class="tw-files">
                            @foreach($report->files as $file)
                                <span class="d-inline-flex align-items-center gap-1">
                                    <a class="tw-file" href="{{ route('technical.daily-reports.files.download', [$report, $file]) }}">
                                        <i class="bi {{ $file->isImage() ? 'bi-image' : 'bi-file-earmark-arrow-down' }}"></i>
                                        <span title="{{ $file->original_name }}">{{ $file->original_name }}</span>
                                        <small class="text-muted">{{ $file->humanSize() }}</small>
                                    </a>
                                    @if($canEdit)
                                        <form method="POST"
                                              action="{{ route('technical.daily-reports.files.destroy', [$report, $file]) }}"
                                              onsubmit="return confirm('Xoá file {{ $file->original_name }}?');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger px-1" type="submit" title="Xoá file">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            @if($canEdit || $canReview || $canReopen)
                <div class="tw-card">
                    <div class="tw-card__head"><h2 class="tw-card__title">Xử lý</h2></div>
                    <div class="tw-card__body d-grid gap-3">

                        @if($canEdit)
                            <form method="POST" action="{{ route('technical.daily-reports.submit', $report) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-send"></i> Gửi duyệt
                                </button>
                            </form>
                        @endif

                        @if($canReview)
                            <form method="POST" action="{{ route('technical.daily-reports.approve', $report) }}">
                                @csrf
                                <label class="form-label small" for="approve-note">Ý kiến duyệt (không bắt buộc)</label>
                                <textarea id="approve-note" name="note" rows="2" class="form-control mb-2" maxlength="2000"></textarea>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-check2-circle"></i> Duyệt báo cáo
                                </button>
                            </form>

                            <form method="POST" action="{{ route('technical.daily-reports.request-revision', $report) }}">
                                @csrf
                                <label class="form-label small" for="revision-note">
                                    Ý kiến yêu cầu sửa <span class="text-danger">*</span>
                                </label>
                                <textarea id="revision-note" name="note" rows="2" required minlength="5" maxlength="2000"
                                          class="form-control mb-2"
                                          placeholder="Nêu rõ cần bổ sung gì…"></textarea>
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-arrow-counterclockwise"></i> Yêu cầu sửa
                                </button>
                            </form>
                        @endif

                        @if($canReopen)
                            <form method="POST" action="{{ route('technical.daily-reports.reopen', $report) }}">
                                @csrf
                                <label class="form-label small" for="reopen-note">
                                    Lý do mở lại <span class="text-danger">*</span>
                                </label>
                                <textarea id="reopen-note" name="note" rows="2" required minlength="5" maxlength="2000"
                                          class="form-control mb-2"
                                          placeholder="Bắt buộc nêu lý do mở lại báo cáo đã duyệt…"></textarea>
                                <button type="submit" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-unlock"></i> Mở lại báo cáo
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <div class="tw-card">
                <div class="tw-card__head"><h2 class="tw-card__title">Duyệt</h2></div>
                <div class="tw-card__body">
                    <dl class="tw-def">
                        <dt>Gửi lúc</dt>
                        <dd>{{ optional($report->submitted_at)->format('d/m/Y H:i') ?: 'Chưa gửi' }}</dd>
                        <dt>Người duyệt</dt>
                        <dd>{{ $report->approver?->name ?: 'Chưa duyệt' }}</dd>
                        <dt>Duyệt lúc</dt>
                        <dd>{{ optional($report->approved_at)->format('d/m/Y H:i') ?: '—' }}</dd>
                        <dt>Ý kiến</dt>
                        <dd>{{ $report->review_note ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="tw-card">
                <div class="tw-card__head"><h2 class="tw-card__title">Lịch sử</h2></div>
                <div class="tw-card__body">
                    @if($report->histories->isEmpty())
                        <p class="text-muted small mb-0">Chưa có lịch sử.</p>
                    @else
                        <ul class="tw-timeline">
                            @foreach($report->histories as $history)
                                <li>
                                    <div class="fw-semibold small">{{ $history->actionLabel() }}</div>
                                    <div class="small text-muted">
                                        {{ $history->user?->name ?: $history->user_name ?: 'Hệ thống' }}
                                        · {{ $history->created_at?->format('d/m/Y H:i') }}
                                    </div>
                                    @if($history->status_before || $history->status_after)
                                        <div class="small text-muted">
                                            {{ \App\Models\Technical\TechnicalDailyReport::STATUS_LABELS[$history->status_before] ?? $history->status_before ?? '—' }}
                                            <i class="bi bi-arrow-right"></i>
                                            {{ \App\Models\Technical\TechnicalDailyReport::STATUS_LABELS[$history->status_after] ?? $history->status_after ?? '—' }}
                                        </div>
                                    @endif
                                    @if($history->note)
                                        <div class="small mt-1">{{ $history->note }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
