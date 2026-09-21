@extends('layouts.app')

@section('title', $report ? 'Sửa báo cáo ngày' : (($unplannedMode ?? false) ? 'Báo cáo việc phát sinh' : 'Viết báo cáo ngày'))

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/ego-technical-work.css') }}?v={{ file_exists(public_path('css/ego-technical-work.css')) ? filemtime(public_path('css/ego-technical-work.css')) : '1.0.0' }}">
@endpush

@section('content')
@php
    $isEdit = $report !== null;
    $action = $isEdit
        ? route('technical.daily-reports.update', $report)
        : route('technical.daily-reports.store');
@endphp
<div class="container-fluid py-3 tw-wrap">

@php
    /* Báo cáo có thể gắn với dòng KẾ HOẠCH, với đầu việc nguồn, hoặc là việc
       PHÁT SINH ngoài kế hoạch (bắt buộc nhập lý do phát sinh).
       KHÔNG bắt buộc phải có kế hoạch trước mới được báo cáo. */
    $planItem = $planItem ?? null;
    $planItems = $planItems ?? collect();
    $forceUnplanned = (bool) old('is_unplanned', ($unplannedMode ?? false));
    $hasAnySource = $planItem !== null || $planItems->isNotEmpty() || $item !== null || $myItems->isNotEmpty();
    /* Không còn màn hình cụt: khi không có nguồn nào thì mở thẳng chế độ phát sinh. */
    $unplanned = $forceUnplanned || (! $isEdit && ! $hasAnySource);
@endphp

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">
                {{ $isEdit ? 'Sửa báo cáo ngày' : ($unplanned ? 'Báo cáo việc phát sinh' : 'Viết báo cáo ngày') }}
            </h1>
            <p class="tw-head__sub">
                @if($isEdit)
                    {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($report->work_title) }} · {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($report->site_name ?: 'Chưa gắn công trình') }}
                @elseif($unplanned)
                    Việc làm ngoài kế hoạch tuần. Bạn không cần có kế hoạch trước, chỉ cần nêu rõ lý do phát sinh.
                @else
                    Chọn đúng đầu việc bạn đang làm. Chỉ những đầu việc được giao cho bạn mới xuất hiện ở đây.
                @endif
            </p>
        </div>
        <div class="tw-head__actions">
            {{-- Form phát sinh dẫn tới bài riêng vì có ràng buộc lý do bắt buộc. --}}
            @include('technical.guides.partials.help-button', [
                'slug' => $unplanned ? 'nhan-vien-bao-cao-phat-sinh' : 'nhan-vien-bao-cao-ngay',
            ])
            @if(! $isEdit && ! $unplanned)
                <a href="{{ route('technical.daily-reports.create', ['mode' => 'phat-sinh']) }}" class="btn btn-outline-primary">
                    <i class="bi bi-lightning-charge"></i> Báo cáo việc phát sinh
                </a>
            @endif
            <a href="{{ route('technical.daily-reports.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Danh sách báo cáo
            </a>
        </div>
    </div>

    @include('technical.workboard.partials.flash')

    @if(! $isEdit && ! $hasAnySource)
        <div class="tw-note mb-3">
            <i class="bi bi-info-circle"></i>
            <div>
                Hôm nay chưa có công việc trong kế hoạch. Bạn vẫn có thể báo cáo việc phát sinh hoặc lập kế hoạch mới.
                <div class="mt-2">
                    <a href="{{ route('technical.week-plan.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-calendar-week"></i> Lập kế hoạch
                    </a>
                </div>
            </div>
        </div>
    @endif

    @if(true)
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" novalidate>
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-12 col-lg-8">
                    <div class="tw-card">
                        <div class="tw-card__head">
                            <h2 class="tw-card__title">Nội dung báo cáo</h2>
                        </div>
                        <div class="tw-card__body">

                            @if(! $isEdit)
                                {{-- 1) Công việc theo KẾ HOẠCH của ngày --}}
                                @if($planItem)
                                    <input type="hidden" name="plan_item_id" value="{{ $planItem->id }}">
                                    <div class="mb-3">
                                        <label class="form-label">Công việc theo kế hoạch</label>
                                        <div class="border rounded p-3 bg-light">
                                            <div class="fw-semibold">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($planItem->title) }}</div>
                                            <div class="small text-muted mt-1">
                                                {{ $planItem->sourceLabel() }} ·
                                                {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($planItem->site_name ?: 'Chưa gắn công trình') }} ·
                                                {{ $planItem->plan_date->format('d/m/Y') }} · {{ $planItem->dayPartLabel() }}
                                            </div>
                                            @if($planItem->objective)
                                                <div class="small mt-2"><strong>Mục tiêu:</strong> {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($planItem->objective) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($planItems->isNotEmpty() && ! $unplanned)
                                    <div class="mb-3">
                                        <label class="form-label" for="plan-item">Công việc theo kế hoạch</label>
                                        <select id="plan-item" name="plan_item_id" class="form-select">
                                            <option value="">— Không theo kế hoạch —</option>
                                            @foreach($planItems as $option)
                                                <option value="{{ $option->id }}" @selected((int) old('plan_item_id') === (int) $option->id)>
                                                    [{{ $option->dayPartLabel() }}] {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($option->title) }}
                                                    @if($option->site_name) · {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($option->site_name) }} @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">
                                            Chọn đúng dòng kế hoạch để hệ thống so sánh được kế hoạch với kết quả.
                                        </div>
                                    </div>
                                @endif

                                {{-- 2) Việc phát sinh ngoài kế hoạch --}}
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1"
                                               id="is_unplanned" name="is_unplanned" @checked($unplanned)>
                                        <label class="form-check-label" for="is_unplanned">
                                            Công việc phát sinh (ngoài kế hoạch tuần)
                                        </label>
                                    </div>
                                    <div class="mt-2">
                                        <label class="form-label" for="unplanned_reason">
                                            Lý do phát sinh @if($unplanned)<span class="text-danger">*</span>@endif
                                        </label>
                                        <input type="text" id="unplanned_reason" name="unplanned_reason"
                                               maxlength="2000"
                                               class="form-control @error('unplanned_reason') is-invalid @enderror"
                                               value="{{ old('unplanned_reason', $report->unplanned_reason ?? '') }}"
                                               placeholder="Bắt buộc khi đánh dấu công việc phát sinh">
                                        @error('unplanned_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            @endif

                            @if(! $isEdit && $planItem === null && ($item !== null || $myItems->isNotEmpty()))
                                <div class="mb-3">
                                    <label class="form-label" for="work-item">Đầu việc</label>

                                    @if($item)
                                        <input type="hidden" name="source_type" value="{{ $item->sourceType }}">
                                        <input type="hidden" name="source_id" value="{{ $item->sourceId }}">
                                        <div class="border rounded p-3 bg-light">
                                            <div class="fw-semibold">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->title) }}</div>
                                            <div class="small text-muted mt-1">
                                                <span class="tw-source tw-source--{{ $item->sourceTone() }}">{{ $item->sourceLabel() }}</span>
                                                <span class="ms-2"><i class="bi bi-buildings"></i> {{ $item->siteName ?: 'Chưa gắn công trình' }}</span>
                                                @if($item->dueDate)
                                                    <span class="ms-2"><i class="bi bi-calendar-event"></i> Hạn {{ $item->dueDate->format('d/m/Y') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <select id="work-item" name="work_item_key" class="form-select"
                                                onchange="egoSplitWorkItem(this)">
                                            <option value="">— Chọn đầu việc —</option>
                                            @foreach($myItems as $option)
                                                <option value="{{ $option->sourceType }}|{{ $option->sourceId }}"
                                                    @selected(old('source_type').'|'.old('source_id') === $option->sourceType.'|'.$option->sourceId)>
                                                    [{{ $option->sourceLabel() }}]
                                                    {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($option->title) }}
                                                    @if($option->siteName) · {{ $option->siteName }} @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="source_type" id="source_type" value="{{ old('source_type') }}">
                                        <input type="hidden" name="source_id" id="source_id" value="{{ old('source_id') }}">
                                        @error('source_type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        @error('source_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                    @endif
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label" for="content">Nội dung đã thực hiện <span class="text-danger">*</span></label>
                                <textarea id="content" name="content" rows="6" required
                                          class="form-control @error('content') is-invalid @enderror"
                                          placeholder="Mô tả cụ thể công việc đã làm trong ngày…">{{ old('content', $report->content ?? '') }}</textarea>
                                @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="result_achieved">Kết quả đạt được</label>
                                <textarea id="result_achieved" name="result_achieved" rows="3"
                                          class="form-control @error('result_achieved') is-invalid @enderror"
                                          placeholder="Kết quả cụ thể so với mục tiêu đã đặt ra…">{{ old('result_achieved', $report->result_achieved ?? '') }}</textarea>
                                @error('result_achieved') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="not_done_reason">Lý do chưa hoàn thành</label>
                                <textarea id="not_done_reason" name="not_done_reason" rows="2"
                                          class="form-control @error('not_done_reason') is-invalid @enderror"
                                          placeholder="Bỏ trống nếu công việc đã hoàn thành">{{ old('not_done_reason', $report->not_done_reason ?? '') }}</textarea>
                                @error('not_done_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="materials_note">Vật tư đã dùng / ghi chú vật tư</label>
                                <textarea id="materials_note" name="materials_note" rows="3"
                                          class="form-control @error('materials_note') is-invalid @enderror"
                                          placeholder="Ví dụ: 12m cáp DC 4mm2, 4 kẹp giữa…">{{ old('materials_note', $report->materials_note ?? '') }}</textarea>
                                @error('materials_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="issues_note">Vấn đề phát sinh</label>
                                <textarea id="issues_note" name="issues_note" rows="3"
                                          class="form-control @error('issues_note') is-invalid @enderror"
                                          placeholder="Khó khăn, sự cố, hạng mục cần hỗ trợ…">{{ old('issues_note', $report->issues_note ?? '') }}</textarea>
                                @error('issues_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <label class="form-label" for="next_plan">Kế hoạch tiếp theo</label>
                                <textarea id="next_plan" name="next_plan" rows="3"
                                          class="form-control @error('next_plan') is-invalid @enderror"
                                          placeholder="Dự kiến làm gì trong buổi/ngày tới…">{{ old('next_plan', $report->next_plan ?? '') }}</textarea>
                                @error('next_plan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="tw-card">
                        <div class="tw-card__head">
                            <h2 class="tw-card__title">Thông tin chung</h2>
                        </div>
                        <div class="tw-card__body">
                            <div class="mb-3">
                                <label class="form-label" for="report_date">Ngày báo cáo <span class="text-danger">*</span></label>
                                <input type="date" id="report_date" name="report_date" required
                                       max="{{ now()->toDateString() }}"
                                       min="{{ now()->subDays(60)->toDateString() }}"
                                       class="form-control @error('report_date') is-invalid @enderror"
                                       value="{{ old('report_date', optional($reportDate)->toDateString()) }}">
                                @error('report_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Không nhận ngày tương lai; tối đa lùi 60 ngày.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="progress_percent">% hoàn thành <span class="text-danger">*</span></label>
                                <input type="number" id="progress_percent" name="progress_percent" required
                                       min="0" max="100" step="1"
                                       class="form-control @error('progress_percent') is-invalid @enderror"
                                       value="{{ old('progress_percent', $report->progress_percent ?? 0) }}">
                                @error('progress_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-0">
                                <label class="form-label" for="work_hours">Số giờ thực hiện</label>
                                <input type="number" id="work_hours" name="work_hours"
                                       min="0" max="24" step="0.25"
                                       class="form-control @error('work_hours') is-invalid @enderror"
                                       value="{{ old('work_hours', $report->work_hours ?? '') }}">
                                @error('work_hours') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Không bắt buộc.</div>
                            </div>
                        </div>
                    </div>

                    <div class="tw-card">
                        <div class="tw-card__head">
                            <h2 class="tw-card__title">Ảnh / file minh chứng</h2>
                        </div>
                        <div class="tw-card__body">
                            <input type="file" name="files[]" multiple
                                   class="form-control @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror"
                                   accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx">
                            @error('files') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @error('files.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="form-text">
                                Tối đa {{ $maxFiles }} file, mỗi file ≤ {{ (int) ($maxFileKb / 1024) }} MB.
                                Chấp nhận ảnh, PDF, Word, Excel. File lưu ở khu vực riêng tư, chỉ tải được qua hệ thống.
                            </div>

                            @if($isEdit && $report->files->isNotEmpty())
                                <hr>
                                <div class="tw-files">
                                    @foreach($report->files as $file)
                                        <span class="tw-file">
                                            <i class="bi {{ $file->isImage() ? 'bi-image' : 'bi-file-earmark' }}"></i>
                                            <span title="{{ $file->original_name }}">{{ $file->original_name }}</span>
                                            <small class="text-muted">{{ $file->humanSize() }}</small>
                                        </span>
                                    @endforeach
                                </div>
                                <div class="form-text mt-2">Xoá file ở màn hình chi tiết báo cáo.</div>
                            @endif
                        </div>
                    </div>

                    <div class="tw-card">
                        <div class="tw-card__body d-grid gap-2">
                            <button type="submit" name="submit" value="1" class="btn btn-primary">
                                <i class="bi bi-check2-circle"></i> Hoàn tất báo cáo
                            </button>
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="bi bi-save"></i> Lưu nháp
                            </button>
                            <div class="form-text">
                                "Hoàn tất báo cáo" là trạng thái đã nộp — hệ thống dùng ngay để so sánh
                                kế hoạch với kết quả, không cần chờ Ban giám đốc duyệt.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endif

</div>
@endsection

@push('scripts')
<script>
    /* Tách "source_type|source_id" từ ô chọn đầu việc thành 2 input ẩn. */
    function egoSplitWorkItem(select) {
        var parts = String(select.value || '').split('|');
        var typeInput = document.getElementById('source_type');
        var idInput = document.getElementById('source_id');
        if (typeInput) { typeInput.value = parts[0] || ''; }
        if (idInput) { idInput.value = parts[1] || ''; }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var select = document.getElementById('work-item');
        if (select && select.value) { egoSplitWorkItem(select); }
    });
</script>
@endpush
