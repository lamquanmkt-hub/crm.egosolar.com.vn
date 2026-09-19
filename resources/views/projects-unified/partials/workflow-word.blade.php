@php
    $wfSelected = $workflow['selected'] ?? null;
    $wfDefinition = $wfSelected['definition'] ?? [];
    $wfRow = $wfSelected['row'] ?? null;
    $wfAssignments = $wfSelected['assignments'] ?? collect();
    $wfDocuments = $wfSelected['documents'] ?? collect();
    $wfDocumentState = $wfSelected['document_state'] ?? ['items'=>[], 'missing'=>[], 'complete'=>false];
    $wfMissingFiles = (array) ($wfDocumentState['file_missing'] ?? $wfDocumentState['missing'] ?? []);
    $wfMissingInformation = (array) ($wfDocumentState['information_missing'] ?? []);
    $wfFilesComplete = (bool) ($wfDocumentState['files_complete'] ?? $wfDocumentState['complete'] ?? false);
    $wfApprovals = $wfSelected['approvals'] ?? collect();
    $wfPermissions = $workflow['permissions'] ?? [];
    $wfApprovalPermissions = $workflow['approval_permissions'] ?? [];
    $wfIsAdmin = !empty($wfPermissions['is_admin']);
    $wfStepCode = (string) ($workflow['selected_code'] ?? request('step', 'survey'));
    $wfStatus = (string) ($wfSelected['status'] ?? 'not_assigned');
    $wfStepData = [];
    if ($wfRow && !empty($wfRow->data)) {
        $decoded = json_decode((string) $wfRow->data, true);
        $wfStepData = is_array($decoded) ? $decoded : [];
    }
    $wfMyAssignment = $wfAssignments->first(fn($assignment) => (int)$assignment->user_id === (int)auth()->id());
    $stepMeta = [
        'survey' => ['CÔNG TRÌNH - BƯỚC 1', 'KHẢO SÁT & PHƯƠNG ÁN', 'Chọn nhân sự thực hiện, lưu hồ sơ khảo sát/phương án và gửi duyệt.'],
        'contract' => ['CÔNG TRÌNH - BƯỚC 2', 'HỢP ĐỒNG & PHÁP LÝ', 'Quản lý nhân sự phụ trách, hợp đồng, phụ lục và hồ sơ pháp lý trong cùng một bước.'],
        'construction' => ['CÔNG TRÌNH - BƯỚC 4', 'THI CÔNG', 'Quản lý nhân sự thi công, kế hoạch, tiến độ và báo cáo thực tế tại công trình.'],
        'acceptance' => ['CÔNG TRÌNH - BƯỚC 5', 'NGHIỆM THU', 'Hoàn thiện hồ sơ nghiệm thu, duyệt và chuyển công trình sang Bảo trì/Bảo hành.'],
    ];
    $meta = $stepMeta[$wfStepCode] ?? $stepMeta['survey'];
    $primary = $wfAssignments->first(fn($row) => (string)$row->assignment_role === 'primary');
    $collaborators = $wfAssignments->filter(fn($row) => (string)$row->assignment_role !== 'primary');
    $approvalLabels = ['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','revision'=>'Yêu cầu bổ sung'];
    $wfApprovalDisplay = match ($wfStatus) {
        'approved' => 'Đã duyệt',
        'submitted' => 'Chờ duyệt',
        'revision' => 'Yêu cầu bổ sung',
        'assigned', 'in_progress' => 'Chưa gửi duyệt',
        default => 'Chưa gửi duyệt',
    };
    $wordDocumentItems = collect($wfDocumentState['items'] ?? [])
        ->filter(fn ($item) => empty($item['is_data_requirement']) && empty($item['is_system_requirement']))
        ->values();
    $wfDueAt = $wfSelected['due_at'] ?? null;
    $wfOverdueDays = (int) ($wfSelected['overdue_days'] ?? 0);
    $wfAssignDialogId = 'pword-assign-dialog-'.$site->id.'-'.$wfStepCode;
    $wfSettingsDialogId = 'ewd-settings-dialog-'.$site->id.'-'.$wfStepCode;
    $wfRevisionFiles = $wfDocuments->where('document_code', 'revision_feedback');
    $wfRevisionApproval = $wfApprovals->first(fn($approval) => (string)$approval->status === 'revision');
    $wfRevisionEvent = collect($workflow['events'] ?? [])->first(fn($event) => (string)($event->action ?? '') === 'step_revision_requested');
    $wfRevisionPayload = !empty($wfRevisionEvent?->payload) ? json_decode((string)$wfRevisionEvent->payload, true) : [];
    $wfRevisionRecipient = $wfAssignments->first(fn($assignment) => (int)$assignment->user_id === (int)($wfRevisionPayload['recipient_id'] ?? 0));
@endphp

<section class="pword-panel" id="workflow">
    @if(empty($workflow['available']))
        <div class="pword-flash danger">Workflow chưa được khởi tạo.</div>
    @else
        <div class="pword-panel-head">
            <div><small>{{ $meta[0] }}</small><h2>{{ $meta[1] }}</h2><p>{{ $meta[2] }}</p></div>
            <span class="pword-status">{{ $wfSelected['status_label'] ?? $wfStatus }}</span>
        </div>

        <div class="ewd-assignment-bar">
            <div class="ewd-assignment-cell"><small>Phụ trách chính</small><strong>{{ $primary->user_name ?? 'Chưa phân công' }}</strong></div>
            <div class="ewd-assignment-cell"><small>Phối hợp</small><strong>{{ $collaborators->isNotEmpty() ? $collaborators->pluck('user_name')->filter()->implode(', ') : '—' }}</strong></div>
            <div class="ewd-assignment-cell {{ $wfOverdueDays > 0 ? 'is-overdue' : '' }}"><small>Hạn hoàn thành</small><strong>{{ $wfDueAt ? $wfDueAt->format('H:i · d/m/Y') : 'Chưa thiết lập' }}</strong></div>
            @if(!empty($wfPermissions['can_assign']) && ($wfStatus !== 'approved' || $wfIsAdmin))
                <button class="pword-btn light ewd-assignment-button" type="button" data-pword-open-dialog="{{ $wfAssignDialogId }}"><i class="bi bi-person-gear"></i> Phân công</button>
            @endif
        </div>

        @if(!empty($wfPermissions['can_assign']) && ($wfStatus !== 'approved' || $wfIsAdmin))
            <dialog class="pword-assign-dialog" id="{{ $wfAssignDialogId }}">
                <div class="pword-assign-dialog-card">
                    <header><div><small>PHÂN CÔNG CÔNG VIỆC</small><h3>Chọn nhân sự thực hiện</h3></div><button type="button" data-pword-close-dialog aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
                <form method="POST" action="{{ route('projects-unified.workflow.assign', [$site,$wfStepCode]) }}" class="pword-form grid">
                    @csrf
                    @if($wfStatus === 'approved')
                        <div class="pword-assign-warning wide"><i class="bi bi-info-circle"></i> Bước đã duyệt. Admin chọn giữ nguyên trạng thái hoặc mở lại bước.</div>
                        <label class="wide">Sau khi thay đổi nhân sự
                            <select name="approved_action"><option value="preserve">Giữ nguyên trạng thái đã duyệt</option><option value="reopen">Mở lại bước để tiếp tục xử lý</option></select>
                        </label>
                    @endif
                    <label>Người thực hiện chính
                        <select name="primary_assignee_id" required>
                            <option value="">Chọn nhân sự</option>
                            @foreach($workflow['assignee_options'] ?? collect() as $option)
                                <option value="{{ $option->id }}" @selected((int)old('primary_assignee_id',$primary->user_id ?? 0)===(int)$option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <fieldset class="pword-assignee-picker">
                        <legend>Nhân sự phối hợp <small>(chọn nhiều người)</small></legend>
                        <div class="pword-assignee-options">
                            @foreach($workflow['assignee_options'] ?? collect() as $option)
                                <label><input type="checkbox" name="collaborator_ids[]" value="{{ $option->id }}" @checked($collaborators->pluck('user_id')->contains($option->id))><span>{{ $option->name }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label>Hạn hoàn thành<input type="datetime-local" name="due_at" value="{{ $wfSelected['due_at']?->format('Y-m-d\TH:i') }}" required></label>
                    <label>Yêu cầu công việc<textarea name="requirement" rows="3" required>{{ old('requirement',$wfRow?->requirement) }}</textarea></label>
                    <button class="pword-btn primary" type="submit">Phân công {{ ($workflow['assignee_options'] ?? collect())->count() > 1 ? 'nhiều người' : '' }}</button>
                </form>
                </div>
            </dialog>
        @endif

        @if($wfStepCode === 'construction')
            <h3 class="pword-section-title">KẾ HOẠCH THI CÔNG</h3>
            <div class="pword-read-grid">
                <div><small>Hạng mục thực hiện</small><strong>{{ $wfRow?->requirement ?: 'Chưa cập nhật' }}</strong></div>
                <div><small>Ngày bắt đầu</small><strong>{{ !empty($wfRow?->started_at) ? \Illuminate\Support\Carbon::parse($wfRow->started_at)->format('d/m/Y') : 'Chưa bắt đầu' }}</strong></div>
                <div><small>Dự kiến hoàn thành</small><strong>{{ $wfSelected['due_at']?->format('d/m/Y') ?? 'Chưa đặt hạn' }}</strong></div>
            </div>
            <h3 class="pword-section-title">BÁO CÁO THI CÔNG</h3>
            @if($wfMyAssignment)
                <form method="POST" action="{{ route('projects-unified.workflow.progress', [$site,$wfStepCode]) }}" class="pword-form grid">
                    @csrf
                    <label>Tiến độ thực hiện (%)<input type="number" name="progress_percent" min="0" max="100" value="{{ (int)($wfMyAssignment->progress_percent ?? 0) }}" required></label>
                    <label class="wide">Ghi chú<textarea name="submission_summary" rows="3">{{ $wfMyAssignment->submission_summary }}</textarea></label>
                    <button class="pword-btn primary" type="submit">Lưu cập nhật</button>
                </form>
            @else
                <div class="pword-note">Cần phân công nhân sự trước khi cập nhật tiến độ.</div>
            @endif
        @endif

        <section class="ewd-doc-section">
            <header class="ewd-doc-section-head">
                <div><h3>HỒ SƠ {{ $wfStepCode === 'survey' ? 'KHẢO SÁT & PHƯƠNG ÁN' : ($wfStepCode === 'contract' ? 'HỢP ĐỒNG & PHÁP LÝ' : ($wfStepCode === 'construction' ? 'THI CÔNG' : 'NGHIỆM THU')) }}</h3><small>{{ $wordDocumentItems->count() }} danh mục · {{ count($wfMissingFiles) }} mục còn thiếu</small></div>
                @if($wfIsAdmin)<button type="button" class="pword-btn light ewd-settings-button" data-pword-open-dialog="{{ $wfSettingsDialogId }}"><i class="bi bi-sliders"></i> Cài đặt hồ sơ</button>@endif
            </header>
            <div class="ewd-document-table">
                <div class="ewd-document-row is-header"><span>Hồ sơ</span><span>Yêu cầu</span><span>Đã tải</span><span>Trạng thái</span><span>Thao tác</span></div>
                @forelse($wordDocumentItems as $item)
                    @php
                        $itemDocuments = $wfDocuments->where('document_code', (string) $item['code']);
                        $itemCount = (int) ($item['count'] ?? 0);
                        $itemMinimum = (int) ($item['minimum'] ?? 1);
                        $itemMaximum = !empty($item['maximum']) ? (int) $item['maximum'] : null;
                        $itemMissing = !empty($item['required']) ? max(0, $itemMinimum - $itemCount) : 0;
                        $itemCanUpload = !empty($wfPermissions['can_upload']) && ($itemMaximum === null || $itemCount < $itemMaximum);
                    @endphp
                    <div class="ewd-document-row {{ $itemMissing > 0 ? 'is-missing' : ($itemCount > 0 ? 'is-complete' : '') }}">
                        <div class="ewd-document-name"><strong>{{ $item['label'] }}</strong><small>{{ !empty($item['extensions']) ? strtoupper(implode(', ', $item['extensions'])) : 'Mọi định dạng' }}</small></div>
                        <div><span class="ewd-requirement {{ !empty($item['required']) ? 'is-required' : '' }}">{{ !empty($item['required']) ? 'Bắt buộc' : 'Tùy chọn' }}</span><small>{{ $itemMinimum }}{{ $itemMaximum ? '–'.$itemMaximum : '+' }} tệp</small></div>
                        <div class="ewd-document-count"><strong>{{ $itemCount }}</strong><small>/ {{ $itemMinimum }}</small></div>
                        <div><span class="ewd-document-status {{ $itemMissing > 0 ? 'is-pending' : ($itemCount > 0 ? 'is-done' : 'is-optional') }}">{{ $itemMissing > 0 ? 'Thiếu '.$itemMissing : ($itemCount > 0 ? 'Đã đủ' : 'Tùy chọn') }}</span></div>
                        <div class="ewd-document-actions">
                            @if($itemCanUpload)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('projects-unified.workflow.documents.upload', [$site, $wfStepCode]) }}" data-pword-quick-upload>
                                    @csrf<input type="hidden" name="document_code" value="{{ $item['code'] }}">
                                    <label class="ewd-upload-button"><i class="bi bi-cloud-arrow-up"></i><span data-pword-upload-status>Tải lên</span><input type="file" name="file" required @if(!empty($item['extensions'])) accept="{{ collect($item['extensions'])->map(fn($extension) => '.'.ltrim((string)$extension, '.'))->implode(',') }}" @endif></label>
                                </form>
                            @elseif($itemMaximum !== null && $itemCount >= $itemMaximum)<span class="ewd-limit-reached">Đã tối đa</span>@endif
                            @if($itemDocuments->isNotEmpty())
                                <details class="ewd-files-menu"><summary><i class="bi bi-folder2-open"></i> {{ $itemDocuments->count() }}</summary><div class="ewd-files-list">@foreach($itemDocuments as $document)<a href="{{ asset('storage/'.$document->path) }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-check"></i><span>{{ $document->original_name ?: $document->title }}<small>v{{ $document->version }} · {{ $document->uploader_name ?: 'Hệ thống' }}</small></span></a>@endforeach</div></details>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="ewd-doc-empty">Chưa có danh mục hồ sơ. @if($wfIsAdmin)Bấm “Cài đặt hồ sơ” để thêm.@endif</div>
                @endforelse
            </div>
        </section>
        @if($wfIsAdmin)
            @include('projects-unified.partials.workflow-document-settings')
        @endif

        @if(!empty($wfPermissions['can_save_data']))
            <form method="POST" action="{{ route('projects-unified.workflow.data.save', [$site,$wfStepCode]) }}" class="pword-form pword-update-form">
                @csrf
                @if($wfStepCode === 'construction')
                    <label class="check"><input type="checkbox" name="has_incident" value="1" @checked(!empty($wfStepData['has_incident']))>Có phát sinh/sự cố cần biên bản giải trình</label>
                @endif
                @if($wfStepCode === 'acceptance')
                    <label>Ngày bàn giao dự kiến/thực tế<input type="date" name="handover_at" value="{{ old('handover_at',$site->handover_at?->format('Y-m-d') ?? ($wfStepData['handover_at'] ?? '')) }}"></label>
                @endif
                <label>{{ $wfStepCode === 'construction' ? 'Ghi chú thi công' : 'Ghi chú' }}<textarea name="step_note" rows="3">{{ $wfStepData['step_note'] ?? '' }}</textarea></label>
                <button class="pword-btn primary" type="submit">{{ $wfStepCode === 'construction' ? 'Lưu cập nhật' : 'Lưu' }}</button>
            </form>
        @endif

        @if(!empty($wfStepData['step_note']))
            <div class="pword-saved-note">
                <i class="bi bi-journal-check"></i>
                <div><small>GHI CHÚ ĐÃ LƯU{{ !empty($wfRow?->updated_at) ? ' · '.\Illuminate\Support\Carbon::parse($wfRow->updated_at)->format('H:i d/m/Y') : '' }}</small><p>{{ $wfStepData['step_note'] }}</p></div>
            </div>
        @endif

        @if($wfStatus === 'revision' && !empty($wfRow?->returned_reason))
            <div class="pword-revision-card">
                <i class="bi bi-arrow-counterclockwise"></i>
                <div>
                    <small>HỒ SƠ ĐƯỢC HOÀN TRẢ{{ !empty($wfRevisionApproval?->reviewed_at) ? ' · '.\Illuminate\Support\Carbon::parse($wfRevisionApproval->reviewed_at)->format('H:i d/m/Y') : '' }}</small>
                    <strong>Người trả: {{ $wfRevisionApproval?->reviewer_name ?: 'Người duyệt' }}{{ $wfRevisionRecipient ? ' · Người nhận: '.$wfRevisionRecipient->user_name : '' }}</strong>
                    <p>{{ $wfRow->returned_reason }}</p>
                    @foreach($wfRevisionFiles as $revisionFile)
                        <a href="{{ asset('storage/'.$revisionFile->path) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i>{{ $revisionFile->original_name }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        @if($wfIsAdmin && !$wfFilesComplete && $wfStatus !== 'approved')
            <div class="pword-approval-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <span><strong>File hồ sơ chưa đầy đủ.</strong> Còn thiếu: {{ implode('; ', $wfMissingFiles) }}. Admin vẫn có quyền duyệt ngoại lệ.</span>
            </div>
        @endif
        @if($wfMissingInformation !== [] && $wfStatus !== 'approved')
            <div class="pword-approval-warning">
                <i class="bi bi-info-circle"></i>
                <span><strong>Thông tin cần bổ sung:</strong> {{ implode('; ', $wfMissingInformation) }}. Nội dung này chỉ cảnh báo, không chặn duyệt.</span>
            </div>
        @endif

        <div class="pword-approval-row">
            <div><small>TRẠNG THÁI DUYỆT</small><strong>{{ $wfApprovalDisplay }}</strong></div>
            <div class="pword-actions">
                @if($wfMyAssignment && !$wfIsAdmin && $wfStatus !== 'approved')
                    <form method="POST" action="{{ route('projects-unified.workflow.assignment.submit', [$site,$wfStepCode]) }}">@csrf<input type="hidden" name="submission_summary" value="Nộp kết quả theo hồ sơ đã cập nhật"><button class="pword-btn primary" type="submit" @disabled(!$wfFilesComplete)>Gửi duyệt</button></form>
                @endif
                @foreach($wfDefinition['approval_tracks'] ?? [] as $approvalType => $track)
                    @if($wfStatus !== 'approved' && ($wfStatus === 'submitted' || $wfIsAdmin) && (!empty($wfApprovalPermissions[$approvalType]) || $wfIsAdmin))
                        <form method="POST" action="{{ route('projects-unified.workflow.approve', [$site,$wfStepCode]) }}">@csrf<input type="hidden" name="approval_type" value="{{ $approvalType }}"><button class="pword-btn primary" type="submit" @if($wfIsAdmin && !$wfFilesComplete) onclick="return confirm('Hồ sơ đang thiếu file bắt buộc. Bạn xác nhận duyệt ngoại lệ?')" @endif>{{ $wfIsAdmin && !$wfFilesComplete ? 'Duyệt ngoại lệ' : 'Duyệt' }}</button></form>
                    @endif
                    @if(($wfStatus === 'submitted' && !empty($wfApprovalPermissions[$approvalType])) || $wfIsAdmin)
                        @php($wfRevisionDialogId = 'pword-revision-dialog-'.$site->id.'-'.$wfStepCode.'-'.$approvalType)
                        <button class="pword-btn danger" type="button" data-pword-open-dialog="{{ $wfRevisionDialogId }}"><i class="bi bi-arrow-counterclockwise"></i> Trả hồ sơ cần sửa</button>
                        <dialog class="pword-assign-dialog pword-revision-dialog" id="{{ $wfRevisionDialogId }}">
                            <div class="pword-assign-dialog-card">
                                <header><div><small>ADMIN PHẢN HỒI HỒ SƠ</small><h3>Trả hồ sơ · yêu cầu chỉnh sửa</h3></div><button type="button" data-pword-close-dialog aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
                                <form method="POST" enctype="multipart/form-data" action="{{ route('projects-unified.workflow.revise', [$site,$wfStepCode]) }}" class="pword-form grid">
                                    @csrf
                                    <input type="hidden" name="approval_type" value="{{ $approvalType }}">
                                    @if($wfStatus === 'approved')<div class="pword-assign-warning wide"><i class="bi bi-exclamation-triangle"></i> Bước đã duyệt sẽ được mở lại để nhân sự chỉnh sửa.</div>@endif
                                    <label>Người trả hồ sơ<input type="text" value="{{ auth()->user()->name }}" disabled></label>
                                    <label>Người nhận xử lý
                                        <select name="recipient_id" required>
                                            <option value="">Chọn người cần sửa</option>
                                            @if($wfIsAdmin)
                                                @foreach(($workflow['assignee_options'] ?? collect()) as $option)
                                                    <option value="{{ $option->id }}" @selected((int)($primary->user_id ?? 0)===(int)$option->id)>{{ $option->name }}</option>
                                                @endforeach
                                            @else
                                                @foreach($wfAssignments as $assignment)
                                                    <option value="{{ $assignment->user_id }}" @selected((int)($primary->user_id ?? 0)===(int)$assignment->user_id)>{{ $assignment->user_name }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </label>
                                    <label class="wide">Nội dung yêu cầu sửa<textarea name="reason" rows="5" required placeholder="Ghi rõ nội dung cần sửa, hồ sơ cần thay thế hoặc thông tin cần bổ sung..."></textarea></label>
                                    <label>File phản hồi đính kèm <small>(không bắt buộc, tối đa 20 MB)</small><input type="file" name="attachment"></label>
                                    <label>Hạn xử lý lại <small>(không bắt buộc)</small><input type="datetime-local" name="revision_due_at"></label>
                                    <div class="wide pword-dialog-actions"><button class="pword-btn light" type="button" data-pword-close-dialog>Hủy</button><button class="pword-btn danger" type="submit">Trả hồ sơ cần sửa</button></div>
                                </form>
                            </div>
                        </dialog>
                    @endif
                @endforeach
                @if($wfStepCode === 'acceptance' && $wfStatus === 'approved' && Route::has('projects-unified.maintenance.index'))
                    @if(Route::has('projects-unified.maintenance.activate'))
                        <form method="POST" action="{{ route('projects-unified.maintenance.activate', $site) }}">@csrf<button class="pword-btn primary" type="submit"><i class="bi bi-shield-check"></i> Kích hoạt Bảo trì/Bảo hành</button></form>
                    @else
                        <a class="pword-btn primary" href="{{ route('projects-unified.maintenance.index',['site_id'=>$site->id]) }}">Kích hoạt Bảo trì/Bảo hành</a>
                    @endif
                @endif
            </div>
        </div>
    @endif
</section>

@once
    <script>
        document.addEventListener('click', function (event) {
            const deleteSetting = event.target.closest('[data-ewd-delete-setting]');
            if (deleteSetting) {
                const row = deleteSetting.closest('[data-ewd-setting-row]');
                if (!row) return;

                const code = row.querySelector('input[name$="[code]"]');
                if (code && code.value === '__new__') {
                    row.remove();
                    return;
                }

                const active = row.querySelector('input[type="checkbox"][name$="[is_active]"]');
                if (active) active.checked = false;
                row.hidden = true;
                row.style.display = 'none';
                return;
            }

            const addSetting = event.target.closest('[data-ewd-add-setting]');
            if (addSetting) {
                const form = addSetting.closest('[data-ewd-settings-form]');
                const rows = form && form.querySelector('[data-ewd-setting-rows]');
                const template = form && form.querySelector('[data-ewd-setting-template]');
                if (rows && template) {
                    const index = Array.from(rows.querySelectorAll('input[name$="[code]"]')).reduce(function(maximum, input) {
                        const match = input.name.match(/^documents\[(\d+)\]/);
                        return match ? Math.max(maximum, Number(match[1])) : maximum;
                    }, -1) + 1;
                    rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)).replaceAll('__ORDER__', String((index + 1) * 10)));
                    const last = rows.lastElementChild;
                    if (last) { last.scrollIntoView({block:'nearest'}); const label = last.querySelector('input[type="text"]'); if (label) label.focus(); }
                }
                return;
            }

            const opener = event.target.closest('[data-pword-open-dialog]');
            if (opener) {
                const dialog = document.getElementById(opener.dataset.pwordOpenDialog);
                if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
                return;
            }

            const closer = event.target.closest('[data-pword-close-dialog]');
            if (closer) {
                const dialog = closer.closest('dialog');
                if (dialog) dialog.close();
            }
        });

        document.addEventListener('click', function (event) {
            if (event.target.matches('dialog.pword-assign-dialog')) event.target.close();
        });

        document.addEventListener('change', function (event) {
            const input = event.target.closest('[data-pword-quick-upload] input[type="file"]');
            if (!input || !input.files || input.files.length === 0) return;

            const form = input.closest('[data-pword-quick-upload]');
            if (!form || form.dataset.uploading === '1') return;

            form.dataset.uploading = '1';
            form.classList.add('is-uploading');
            input.disabled = true;

            const status = form.querySelector('[data-pword-upload-status]');
            if (status) status.textContent = 'Đang tải lên...';

            input.disabled = false;
            form.requestSubmit();
        });
    </script>
@endonce
