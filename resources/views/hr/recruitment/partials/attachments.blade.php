@php
    $attachmentKey = $attachmentEntity.'-'.($attachmentRecord ?? 'new');
    $savedFiles = $recruitmentFiles[$attachmentEntity.':'.($attachmentRecord ?? 0)] ?? collect();
@endphp
<section class="rec-attachments" id="rec-files-{{ $attachmentKey }}" data-rec-attachments>
    <h3>Tệp đính kèm <span class="rec-list-count">{{ $savedFiles->count() }}</span></h3>
    <label class="rec-label" for="{{ $attachmentKey }}-files">Thêm tệp từ máy tính</label>
    <input class="rec-input" id="{{ $attachmentKey }}-files" type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx,.xls,.csv,.txt" data-rec-files data-max-size="{{ \App\Services\Hr\RecruitmentAttachmentService::maxKilobytes() * 1024 }}" data-max-batch="{{ \App\Services\Hr\RecruitmentAttachmentService::maxBatchBytes() }}">
    <p class="rec-card-note">PDF, ảnh, Word (.docx), Excel, CSV, TXT · Tối đa 8 tệp/lần, {{ \App\Services\Hr\RecruitmentAttachmentService::maxKilobytes() / 1024 }} MB/tệp. Bấm nút lưu bên dưới để tải lên hệ thống. Với Word .doc, lưu thành .docx hoặc PDF trước.</p>
    <div data-rec-selected aria-live="polite"></div>
    @if($attachmentRestore ?? false)
        @foreach($errors->getMessages() as $key=>$messages)
            @if($key === 'attachments' || str_starts_with($key,'attachments.'))<p class="rec-field-error" role="alert">{{ $messages[0] }} Chọn lại tệp sau khi sửa lỗi.</p>@endif
        @endforeach
    @endif
    @forelse($savedFiles as $attachment)
        <div class="rec-file-row"><div class="rec-file-name"><i class="bi bi-paperclip" aria-hidden="true"></i> <strong>{{ $attachment->original_name }}</strong><small>{{ strtoupper($attachment->extension) }} · {{ number_format($attachment->size / 1024, 0) }} KB · Đã lưu</small></div><div class="rec-file-actions"><a class="rec-btn-outline" href="{{ route('hr.recruitment.attachments.preview',[$attachmentEntity,$attachmentRecord,$attachment->id,'stage'=>$active]) }}" target="_blank" rel="noopener">Xem trước<span class="visually-hidden"> {{ $attachment->original_name }} (tab mới)</span></a><a class="rec-btn-outline" href="{{ route('hr.recruitment.attachments.download',[$attachmentEntity,$attachmentRecord,$attachment->id]) }}">Tải xuống<span class="visually-hidden"> {{ $attachment->original_name }}</span></a></div></div>
    @empty
        <p class="rec-card-note">Chưa có tệp trên hệ thống. Đường dẫn file:/// trên máy cá nhân không phải tệp đã tải lên.</p>
    @endforelse
</section>
