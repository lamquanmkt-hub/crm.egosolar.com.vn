@php
    $w = $wf['selected'] ?? null;
    $def = $w['definition'] ?? [];
    $row = $w['row'] ?? null;
    $assignments = $w['assignments'] ?? collect();
    $documents = $w['documents'] ?? collect();
    $docState = $w['document_state'] ?? ['items'=>[], 'missing'=>[], 'complete'=>false];
    $permissions = $wf['permissions'] ?? [];
    $approvalPermissions = $wf['approval_permissions'] ?? [];
    $status = (string)($w['status'] ?? 'not_assigned');
    $statusLabels = [
        'not_assigned'=>'Chưa phân công','assigned'=>'Đã phân công','accepted'=>'Đã nhận việc',
        'in_progress'=>'Đang thực hiện','submitted'=>'Chờ duyệt','revision'=>'Cần bổ sung','approved'=>'Đã duyệt'
    ];
    $myAssignment = $assignments->first(fn($a)=>(int)$a->user_id === (int)auth()->id());
@endphp

<div class="ego-step-unit">
    <div class="ego-step-unit-head">
        <div>
            <span>{{ $eyebrow ?? 'HỒ SƠ' }}</span>
            <h3>{{ $title ?? ($def['label'] ?? $stepCode) }}</h3>
            @if(!empty($subtitle))<p>{{ $subtitle }}</p>@endif
        </div>
        <div class="ego-step-status {{ $status }}">{{ $statusLabels[$status] ?? $status }}</div>
    </div>

    @if(empty($w['is_unlocked']))
        <div class="ego-doc-alert warning">Bước này chưa được mở. Cần hoàn tất và duyệt bước trước.</div>
    @else
        <section class="ego-form-section">
            <h4>CHỌN NHÂN SỰ {{ $assigneeHeading ?? 'PHỤ TRÁCH' }}</h4>
            <div class="ego-person-chips">
                @forelse($assignments as $person)
                    <span><i class="bi bi-person-check"></i>{{ $person->user_name ?: 'Nhân sự #'.$person->user_id }}<small>{{ $person->assignment_role === 'primary' ? 'Chính' : 'Phối hợp' }}</small></span>
                @empty
                    <em>Chưa phân công nhân sự.</em>
                @endforelse
            </div>

            @if(!empty($permissions['can_assign']) && $status !== 'approved')
                <details class="ego-inline-details" {{ $assignments->isEmpty() ? 'open' : '' }}>
                    <summary><i class="bi bi-person-plus"></i> Chọn / thay đổi nhân sự</summary>
                    <form method="POST" action="{{ route('projects-unified.workflow.assign', [$site, $stepCode]) }}" class="ego-grid-form cols-2">
                        @csrf
                        <label><span>Phụ trách chính *</span><select name="primary_assignee_id" required><option value="">Chọn nhân sự</option>@foreach($wf['assignee_options'] ?? collect() as $userOption)<option value="{{ $userOption->id }}" @selected((int)optional($assignments->firstWhere('assignment_role','primary'))->user_id === (int)$userOption->id)>{{ $userOption->name }}</option>@endforeach</select></label>
                        <label><span>Nhân sự phối hợp</span><select name="collaborator_ids[]" multiple size="4">@foreach($wf['assignee_options'] ?? collect() as $userOption)<option value="{{ $userOption->id }}" @selected($assignments->where('assignment_role','collaborator')->pluck('user_id')->contains($userOption->id))>{{ $userOption->name }}</option>@endforeach</select></label>
                        <label><span>Hạn hoàn thành *</span><input type="datetime-local" name="due_at" value="{{ !empty($w['due_at']) ? $w['due_at']->format('Y-m-d\TH:i') : '' }}" required></label>
                        <label class="full"><span>Nội dung / yêu cầu công việc *</span><textarea name="requirement" rows="3" required>{{ $row?->requirement }}</textarea></label>
                        <div class="full ego-actions"><button class="ego-doc-btn primary" type="submit"><i class="bi bi-save"></i> Lưu phân công</button></div>
                    </form>
                </details>
            @endif
        </section>

        <section class="ego-form-section">
            @php
                $canUploadDocuments = !empty($permissions['can_upload']);
                $requirementCodes = collect($docState['items'] ?? [])
                    ->map(fn($item) => (string)($item['code'] ?? ''))
                    ->filter(fn($code) => $code !== '' && !str_starts_with($code, 'data:') && !str_starts_with($code, 'system:'))
                    ->values();
                $unmappedDocuments = $documents->filter(fn($doc) => !$requirementCodes->contains((string)($doc->document_code ?? '')));
            @endphp
            <div class="ego-section-title-row">
                <h4>{{ $documentHeading ?? 'HỒ SƠ / FILE ĐÍNH KÈM' }}</h4>
                <span>{{ $docState['complete'] ? 'Đủ hồ sơ' : 'Còn thiếu hồ sơ' }}</span>
            </div>

            @if($status === 'approved')
                <div class="ego-doc-alert success compact-note">
                    <i class="bi bi-shield-check"></i>
                    <span>Bước đã duyệt. Vẫn có thể <strong>bổ sung hoặc thay phiên bản file hồ sơ</strong>; trạng thái duyệt được giữ nguyên.</span>
                </div>
            @endif

            <div class="ego-doc-checklist ego-doc-checklist-live">
                @forelse($docState['items'] ?? [] as $docItem)
                    @php
                        $itemCode = (string)($docItem['code'] ?? '');
                        $isDataRequirement = !empty($docItem['is_data_requirement']);
                        $isSystemRequirement = !empty($docItem['is_system_requirement']);
                        $isFileRequirement = !$isDataRequirement && !$isSystemRequirement && $itemCode !== '';
                        $filesForRequirement = $isFileRequirement
                            ? $documents->where('document_code', $itemCode)->sortByDesc('version')->values()
                            : collect();
                        $accept = collect($docItem['extensions'] ?? [])->map(fn($ext)=>'.'.ltrim((string)$ext,'.'))->implode(',');
                    @endphp
                    <div class="ego-doc-requirement-card {{ !empty($docItem['complete']) ? 'done' : '' }}">
                        <div class="ego-doc-requirement-main">
                            <i class="bi {{ !empty($docItem['complete']) ? 'bi-check-square-fill' : 'bi-square' }}"></i>
                            <span>
                                <strong>{{ $docItem['label'] ?? $itemCode }}</strong>
                                @if($isFileRequirement)
                                    <small>{{ !empty($docItem['required']) ? 'Bắt buộc' : 'Khi có' }} · Hiện có {{ (int)($docItem['count'] ?? 0) }} file</small>
                                @else
                                    <small>{{ !empty($docItem['complete']) ? 'Đã có dữ liệu' : 'Chưa có dữ liệu' }} · hệ thống tự đối chiếu</small>
                                @endif
                            </span>
                        </div>

                        @if($filesForRequirement->isNotEmpty())
                            <div class="ego-inline-file-list">
                                @foreach($filesForRequirement as $document)
                                    <a href="{{ asset('storage/'.$document->path) }}" target="_blank" title="Mở file">
                                        <i class="bi bi-file-earmark-check"></i>
                                        <span>
                                            <strong>{{ $document->original_name ?: $document->title ?: 'Tài liệu' }}</strong>
                                            <small>{{ $document->title ?: ($docItem['label'] ?? $itemCode) }} · v{{ (int)($document->version ?? 1) }}</small>
                                        </span>
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if($isFileRequirement && $canUploadDocuments)
                            <form method="POST" enctype="multipart/form-data" action="{{ route('projects-unified.workflow.documents.upload', [$site, $stepCode]) }}" class="ego-inline-file-upload">
                                @csrf
                                <input type="hidden" name="document_code" value="{{ $itemCode }}">
                                <input type="hidden" name="title" value="{{ $docItem['label'] ?? $itemCode }}">
                                <label>
                                    <span>{{ $filesForRequirement->isNotEmpty() ? 'Bổ sung / thay phiên bản' : 'Chọn file' }}</span>
                                    <input type="file" name="file" required @if($accept !== '') accept="{{ $accept }}" @endif>
                                </label>
                                <button class="ego-doc-btn {{ $filesForRequirement->isEmpty() ? 'primary' : '' }}" type="submit">
                                    <i class="bi bi-cloud-arrow-up"></i> {{ $filesForRequirement->isNotEmpty() ? 'Tải bản mới' : 'Tải lên' }}
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    @foreach($def['documents'] ?? [] as $docCode => $docDef)
                        @php $filesForRequirement = $documents->where('document_code', $docCode)->sortByDesc('version')->values(); @endphp
                        <div class="ego-doc-requirement-card {{ $filesForRequirement->isNotEmpty() ? 'done' : '' }}">
                            <div class="ego-doc-requirement-main">
                                <i class="bi {{ $filesForRequirement->isNotEmpty() ? 'bi-check-square-fill' : 'bi-square' }}"></i>
                                <span><strong>{{ $docDef['label'] ?? $docCode }}</strong><small>{{ !empty($docDef['required']) ? 'Bắt buộc' : 'Khi có' }} · {{ $filesForRequirement->count() }} file</small></span>
                            </div>
                            @if($canUploadDocuments)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('projects-unified.workflow.documents.upload', [$site, $stepCode]) }}" class="ego-inline-file-upload">
                                    @csrf<input type="hidden" name="document_code" value="{{ $docCode }}"><input type="hidden" name="title" value="{{ $docDef['label'] ?? $docCode }}">
                                    <label><span>Chọn file</span><input type="file" name="file" required></label>
                                    <button class="ego-doc-btn primary" type="submit"><i class="bi bi-cloud-arrow-up"></i> Tải lên</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                @endforelse
            </div>

            @if($unmappedDocuments->isNotEmpty())
                <div class="ego-section-title-row ego-extra-files-title"><h4>FILE HỒ SƠ KHÁC TRONG BƯỚC</h4><span>{{ $unmappedDocuments->count() }} file</span></div>
                <div class="ego-file-list">
                    @foreach($unmappedDocuments as $document)
                        <a href="{{ asset('storage/'.$document->path) }}" target="_blank"><i class="bi bi-paperclip"></i><span>{{ $document->original_name ?: $document->title }}<small>{{ $document->title ?: $document->document_code }} · v{{ (int)($document->version ?? 1) }}</small></span><i class="bi bi-box-arrow-up-right"></i></a>
                    @endforeach
                </div>
            @endif
        </section>

        @if(!empty($permissions['can_save_data']) && $status !== 'approved')
            <section class="ego-form-section">
                <h4>GHI CHÚ NGHIỆP VỤ</h4>
                <form method="POST" action="{{ route('projects-unified.workflow.data.save', [$site, $stepCode]) }}">
                    @csrf
                    <textarea name="step_note" rows="3" placeholder="Ghi chú, nội dung cần lưu cho bước này...">{{ data_get(json_decode((string)($row?->data ?? '{}'), true), 'step_note') }}</textarea>
                    <div class="ego-actions"><button class="ego-doc-btn" type="submit"><i class="bi bi-save"></i> Lưu</button></div>
                </form>
            </section>
        @endif

        @if($stepCode === 'construction' && $myAssignment && !in_array($status, ['submitted','approved'], true))
            <section class="ego-form-section">
                <h4>BÁO CÁO THI CÔNG</h4>
                <form method="POST" action="{{ route('projects-unified.workflow.progress', [$site, $stepCode]) }}" class="ego-grid-form cols-2">
                    @csrf
                    <label><span>Tiến độ thực hiện</span><input type="number" name="progress_percent" min="0" max="100" value="{{ (int)($myAssignment->progress_percent ?? 0) }}" required></label>
                    <label class="full"><span>Ghi chú / kết quả</span><textarea name="submission_summary" rows="3">{{ $myAssignment->submission_summary }}</textarea></label>
                    <div class="full ego-actions"><button class="ego-doc-btn primary" type="submit">Lưu cập nhật</button></div>
                </form>
            </section>
        @endif

        <section class="ego-form-section compact">
            <div class="ego-section-title-row"><h4>DUYỆT</h4><span class="ego-step-status {{ $status }}">{{ $statusLabels[$status] ?? $status }}</span></div>
            <div class="ego-actions wrap">
                @if($myAssignment && in_array((string)$myAssignment->status, ['assigned','accepted'], true))
                    @if((string)$myAssignment->status === 'assigned')<form method="POST" action="{{ route('projects-unified.workflow.accept', [$site,$stepCode]) }}">@csrf<button class="ego-doc-btn" type="submit">Nhận việc</button></form>@endif
                    <form method="POST" action="{{ route('projects-unified.workflow.start', [$site,$stepCode]) }}">@csrf<button class="ego-doc-btn" type="submit">Bắt đầu</button></form>
                @endif

                @if($myAssignment && !in_array($status, ['submitted','approved'], true))
                    <form method="POST" action="{{ route('projects-unified.workflow.assignment.submit', [$site,$stepCode]) }}" class="ego-submit-inline">
                        @csrf<input name="submission_summary" required placeholder="Tóm tắt kết quả trước khi gửi duyệt"><button class="ego-doc-btn primary" type="submit"><i class="bi bi-send-check"></i> Gửi duyệt</button>
                    </form>
                @endif

                @if($status === 'submitted')
                    @foreach($def['approval_tracks'] ?? [] as $approvalType => $track)
                        @if(!empty($approvalPermissions[$approvalType]))
                            <form method="POST" action="{{ route('projects-unified.workflow.approve', [$site,$stepCode]) }}">@csrf<input type="hidden" name="approval_type" value="{{ $approvalType }}"><input name="note" placeholder="Ghi chú duyệt"><button class="ego-doc-btn success" type="submit">Duyệt</button></form>
                            <form method="POST" action="{{ route('projects-unified.workflow.revise', [$site,$stepCode]) }}">@csrf<input type="hidden" name="approval_type" value="{{ $approvalType }}"><input name="reason" required placeholder="Nội dung cần bổ sung"><button class="ego-doc-btn danger" type="submit">Trả lại</button></form>
                        @endif
                    @endforeach
                @endif
            </div>
        </section>
    @endif
</div>
