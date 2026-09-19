@php
    $wfSelected = $workflow['selected'] ?? null;
    $wfDefinition = $wfSelected['definition'] ?? [];
    $wfRow = $wfSelected['row'] ?? null;
    $wfAssignments = $wfSelected['assignments'] ?? collect();
    $wfDocuments = $wfSelected['documents'] ?? collect();
    $wfDocumentState = $wfSelected['document_state'] ?? ['items' => [], 'missing' => [], 'complete' => false];
    $wfApprovals = $wfSelected['approvals'] ?? collect();
    $wfPermissions = $workflow['permissions'] ?? [];
    $wfApprovalPermissions = $workflow['approval_permissions'] ?? [];
    $wfDocumentSettings = $workflow['document_settings'] ?? [];
    $wfCanManageDocuments = !empty($wfPermissions['can_manage_documents']);
    $wfCanManageGlobalDocuments = !empty($wfPermissions['can_manage_global_documents']);
    $wfStepCode = (string) ($workflow['selected_code'] ?? 'survey');
    $wfStatus = (string) ($wfSelected['status'] ?? 'not_assigned');
    $wfStepData = [];
    if ($wfRow && !empty($wfRow->data)) {
        $decodedStepData = json_decode((string) $wfRow->data, true);
        $wfStepData = is_array($decodedStepData) ? $decodedStepData : [];
    }
    $wfMyAssignment = $wfAssignments->first(fn ($assignment) => (int) $assignment->user_id === (int) auth()->id());
    $wfAssignmentLabels = [
        'assigned' => 'Đã giao việc',
        'accepted' => 'Đã nhận việc',
        'in_progress' => 'Đang thực hiện',
        'submitted' => 'Đã nộp kết quả',
    ];
    $wfApprovalLabels = [
        'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'revision' => 'Yêu cầu bổ sung',
    ];
@endphp

<div class="wf2-workspace" data-wf2-workspace>
    @if(empty($workflow['available']))
        <div class="wf2-alert wf2-alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>Chưa khởi tạo database Workflow 7 bước</strong>
                <p>Chạy migration của gói nâng cấp để kích hoạt giao việc, hồ sơ bắt buộc và phê duyệt.</p>
            </div>
        </div>
    @else
        <header class="wf2-step-hero" style="--wf2-tone: var(--wf2-{{ $wfDefinition['tone'] ?? 'blue' }});">
            <div class="wf2-step-identity">
                <span class="wf2-step-icon">
                    <i class="bi {{ $wfDefinition['icon'] ?? 'bi-diagram-3' }}"></i>
                </span>
                <div>
                    <span>Bước {{ $wfDefinition['sequence'] ?? '—' }} / 7</span>
                    <h2>{{ $wfDefinition['label'] ?? $wfStepCode }}</h2>
                    <p>{{ $wfDefinition['objective'] ?? '' }}</p>
                </div>
            </div>

            <div class="wf2-step-status">
                <span class="wf2-status wf2-status-{{ $wfStatus }}">
                    {{ $wfSelected['status_label'] ?? $wfStatus }}
                </span>
                <strong>{{ (int) ($wfSelected['status_percent'] ?? 0) }}%</strong>
                @if(!empty($wfSelected['overdue_days']))
                    <em><i class="bi bi-exclamation-triangle"></i> Trễ {{ $wfSelected['overdue_days'] }} ngày</em>
                @elseif(!empty($wfSelected['due_at']))
                    <em>Hạn {{ $wfSelected['due_at']->format('d/m/Y H:i') }}</em>
                @else
                    <em>Chưa đặt hạn</em>
                @endif
            </div>
        </header>

        @if(in_array($wfStepCode, ['construction', 'warranty'], true))
            <div class="wf2-horizontal-flow">
                <div>
                    <i class="bi bi-box-seam"></i>
                    <span>
                        <strong>Luồng vật tư ngang</strong>
                        <small>
                            {{ $wfStepCode === 'construction'
                                ? 'Vật tư ban đầu hoặc phát sinh thi công phải qua thẩm định Kỹ thuật, Kho và Admin.'
                                : 'Vật tư thay thế bảo hành phải xác định rõ trong hoặc ngoài phạm vi bảo hành.'
                            }}
                        </small>
                    </span>
                </div>
                <button type="button" class="wf2-btn wf2-btn-primary" data-open-tab="materials">
                    <i class="bi bi-box-arrow-up-right"></i>
                    Mở luồng vật tư
                </button>
            </div>
        @endif

        @if(!empty($wfSelected['overdue_days']))
            <div class="wf2-alert wf2-alert-danger wf2-delay-panel">
                <i class="bi bi-clock-history"></i>
                <div>
                    <strong>{{ $wfSelected['delay_label'] ?? 'Bước đang chậm tiến độ' }} · {{ $wfSelected['overdue_days'] }} ngày</strong>
                    <p>
                        @if(($wfSelected['overdue_days'] ?? 0) <= 15)
                            Bắt buộc nhập lý do và ngày cam kết mới.
                        @elseif(($wfSelected['overdue_days'] ?? 0) <= 30)
                            Trưởng phòng Kỹ thuật phải lập phương án khắc phục trước khi tiếp tục cập nhật tiến độ.
                        @else
                            Rủi ro cao; Admin/Ban lãnh đạo quyết định thay người hoặc bổ sung nhân lực.
                        @endif
                    </p>
                    @if(!empty($wfPermissions['can_work']) || !empty($wfPermissions['can_assign']) || !empty($wfPermissions['can_request_exception']))
                        <form method="POST" action="{{ route('projects-unified.workflow.delay.update', [$site, $wfStepCode]) }}" class="wf2-delay-form">
                            @csrf
                            <label class="wf2-field">
                                <span>Lý do chậm</span>
                                <textarea name="delay_reason" rows="2" required>{{ $wfRow?->delay_reason }}</textarea>
                            </label>
                            <label class="wf2-field">
                                <span>Ngày cam kết mới</span>
                                <input type="datetime-local" name="recommitted_due_at" value="{{ !empty($wfRow?->recommitted_due_at) ? \Illuminate\Support\Carbon::parse($wfRow->recommitted_due_at)->format('Y-m-d\TH:i') : '' }}" required>
                            </label>
                            <label class="wf2-field full">
                                <span>Phương án khắc phục {{ ($wfSelected['overdue_days'] ?? 0) > 15 ? '(bắt buộc)' : '' }}</span>
                                <textarea name="recovery_plan" rows="2" {{ ($wfSelected['overdue_days'] ?? 0) > 15 ? 'required' : '' }}>{{ $wfRow?->recovery_plan }}</textarea>
                            </label>
                            <button type="submit" class="wf2-btn wf2-btn-primary"><i class="bi bi-calendar-check"></i>Lưu xử lý trễ</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        @if(empty($wfSelected['is_unlocked']))
            <div class="wf2-alert wf2-alert-warning">
                <i class="bi bi-lock-fill"></i>
                <div>
                    <strong>Bước này chưa được mở</strong>
                    <p>Phải duyệt hoàn tất bước trước. Chỉ Admin mới được chuyển bước ngoại lệ và bắt buộc ghi lý do.</p>
                </div>
            </div>
        @endif

        @if($wfStatus === 'revision' && !empty($wfRow?->returned_reason))
            <div class="wf2-alert wf2-alert-danger">
                <i class="bi bi-arrow-counterclockwise"></i>
                <div>
                    <strong>Người duyệt yêu cầu bổ sung</strong>
                    <p>{{ $wfRow->returned_reason }}</p>
                </div>
            </div>
        @endif

        <div class="wf2-grid">
            <section class="wf2-card wf2-card-info">
                <header>
                    <div>
                        <span class="wf2-kicker">Khối 1</span>
                        <h3>Thông tin bước</h3>
                    </div>
                    <span class="wf2-chip">SLA {{ $wfDefinition['sla_days'] ?? 'Theo hợp đồng' }}{{ !empty($wfDefinition['sla_days']) ? ' ngày' : '' }}</span>
                </header>

                <dl class="wf2-info-list">
                    <div>
                        <dt>Người thực hiện chính</dt>
                        <dd>{{ $wfDefinition['main_role'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Người giao việc</dt>
                        <dd>{{ $wfDefinition['assigner'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Người duyệt</dt>
                        <dd>{{ $wfDefinition['approver'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Yêu cầu công việc</dt>
                        <dd>{{ $wfRow?->requirement ?: 'Chưa có nội dung giao việc cụ thể.' }}</dd>
                    </div>
                </dl>

                @if(!empty($wfPermissions['can_save_data']))
                    <form method="POST"
                          action="{{ route('projects-unified.workflow.data.save', [$site, $wfStepCode]) }}"
                          class="wf2-data-form">
                        @csrf

                        @if($wfStepCode === 'proposal')
                            <label class="wf2-field full">
                                <span>Phản hồi/ý kiến của khách hàng</span>
                                <textarea name="customer_feedback_note" rows="3" placeholder="Tóm tắt phản hồi, nội dung cần điều chỉnh...">{{ $wfStepData['customer_feedback_note'] ?? '' }}</textarea>
                            </label>
                        @endif

                        @if($wfStepCode === 'contract')
                            <div class="wf2-form-grid">
                                <label class="wf2-check full">
                                    <input type="checkbox" name="technical_feasibility_confirmed" value="1" @checked(!empty($wfStepData['technical_feasibility_confirmed']))>
                                    <span>Trưởng phòng Kỹ thuật xác nhận tiến độ khả thi</span>
                                </label>
                                <label class="wf2-field">
                                    <span>Ngày ký hợp đồng</span>
                                    <input type="date" name="contract_signed_at" value="{{ old('contract_signed_at', $site->contract_signed_at?->format('Y-m-d') ?? ($wfStepData['contract_signed_at'] ?? '')) }}">
                                </label>
                                <label class="wf2-field">
                                    <span>Ngày cam kết hoàn thành</span>
                                    <input type="date" name="target_completion_at" value="{{ old('target_completion_at', $site->target_completion_at?->format('Y-m-d') ?? ($wfStepData['target_completion_at'] ?? '')) }}">
                                </label>
                                <label class="wf2-field">
                                    <span>Giá trị trước VAT</span>
                                    <input type="number" step="1000" min="0" name="contract_amount_before_vat" value="{{ old('contract_amount_before_vat', $site->contract_amount_before_vat ?? ($wfStepData['contract_amount_before_vat'] ?? '')) }}">
                                </label>
                                <label class="wf2-field">
                                    <span>VAT (%)</span>
                                    <input type="number" step="0.1" min="0" max="100" name="vat_rate" value="{{ old('vat_rate', $site->vat_rate ?? ($wfStepData['vat_rate'] ?? '')) }}">
                                </label>
                                <label class="wf2-field full">
                                    <span>Tổng giá trị sau VAT</span>
                                    <input type="number" step="1000" min="0" name="contract_amount_after_vat" value="{{ old('contract_amount_after_vat', $site->contract_amount_after_vat ?? $site->contract_amount ?? ($wfStepData['contract_amount_after_vat'] ?? '')) }}">
                                </label>
                            </div>
                            @if($canSeeFinance)
                                <button type="button" class="wf2-inline-link" data-open-tab="materials">
                                    <i class="bi bi-cash-stack"></i>
                                    Mở tài chính dự án và các đợt thanh toán
                                </button>
                            @endif
                        @endif

                        @if($wfStepCode === 'legal')
                            <div class="wf2-form-grid">
                                <label class="wf2-check">
                                    <input type="checkbox" name="requires_grid_connection" value="1" @checked(!empty($wfStepData['requires_grid_connection']))>
                                    <span>Công trình cần hồ sơ đấu nối điện lực</span>
                                </label>
                                <label class="wf2-check">
                                    <input type="checkbox" name="requires_insurance" value="1" @checked(!empty($wfStepData['requires_insurance']))>
                                    <span>Công trình cần bảo hiểm</span>
                                </label>
                            </div>
                        @endif

                        @if($wfStepCode === 'construction')
                            <label class="wf2-check full">
                                <input type="checkbox" name="has_incident" value="1" @checked(!empty($wfStepData['has_incident']))>
                                <span>Có phát sinh/sự cố cần biên bản giải trình</span>
                            </label>
                        @endif

                        @if($wfStepCode === 'acceptance')
                            <label class="wf2-field">
                                <span>Ngày bàn giao dự kiến/thực tế</span>
                                <input type="date" name="handover_at" value="{{ old('handover_at', $site->handover_at?->format('Y-m-d') ?? ($wfStepData['handover_at'] ?? '')) }}">
                            </label>
                        @endif

                        @if($wfStepCode === 'warranty')
                            <div class="wf2-form-grid">
                                <label class="wf2-field">
                                    <span>Ngày bắt đầu bảo hành</span>
                                    <input type="date" name="warranty_started_at" value="{{ old('warranty_started_at', $site->warranty_started_at?->format('Y-m-d') ?? ($wfStepData['warranty_started_at'] ?? '')) }}">
                                </label>
                                <label class="wf2-field">
                                    <span>Ngày kết thúc bảo hành</span>
                                    <input type="date" name="warranty_to" value="{{ old('warranty_to', $site->warranty_to?->format('Y-m-d') ?? ($wfStepData['warranty_to'] ?? '')) }}">
                                </label>
                                <label class="wf2-field">
                                    <span>Phạm vi xử lý</span>
                                    <select name="warranty_scope">
                                        <option value="pending" @selected(($wfStepData['warranty_scope'] ?? '') === 'pending')>Chờ xác định</option>
                                        <option value="in_scope" @selected(($wfStepData['warranty_scope'] ?? '') === 'in_scope')>Trong phạm vi bảo hành</option>
                                        <option value="out_of_scope" @selected(($wfStepData['warranty_scope'] ?? '') === 'out_of_scope')>Ngoài phạm vi bảo hành</option>
                                    </select>
                                </label>
                                <label class="wf2-check">
                                    <input type="checkbox" name="uses_replacement_material" value="1" @checked(!empty($wfStepData['uses_replacement_material']))>
                                    <span>Có sử dụng vật tư thay thế</span>
                                </label>
                            </div>
                            @if(Route::has('projects-unified.maintenance.index'))
                                <a class="wf2-inline-link" href="{{ route('projects-unified.maintenance.index', ['site_id' => $site->id]) }}">
                                    <i class="bi bi-calendar2-week"></i>
                                    Mở lịch bảo trì và bảo hành
                                </a>
                            @endif
                        @endif

                        <label class="wf2-field full">
                            <span>Ghi chú cập nhật</span>
                            <textarea name="step_note" rows="2" placeholder="Nội dung cập nhật, lý do thay đổi...">{{ $wfStepData['step_note'] ?? '' }}</textarea>
                        </label>

                        <button class="wf2-btn wf2-btn-soft" type="submit">
                            <i class="bi bi-save"></i>
                            Lưu dữ liệu bước
                        </button>
                    </form>
                @endif
            </section>

            <section class="wf2-card wf2-card-assignment">
                @php
                    $wfPrimaryAssignment = $wfAssignments->first(fn ($row) => (string) $row->assignment_role === 'primary');
                    $wfPrimaryAssigneeId = (int) ($wfPrimaryAssignment->user_id ?? 0);
                    $wfCollaboratorAssignments = $wfAssignments->filter(fn ($row) => (string) $row->assignment_role !== 'primary');
                    $wfCollaboratorIds = $wfCollaboratorAssignments
                        ->pluck('user_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                    $wfOldCollaborators = collect(old('collaborator_ids', $wfCollaboratorIds))
                        ->map(fn ($id) => (int) $id)
                        ->all();
                    $wfOldPrimary = (int) old('primary_assignee_id', $wfPrimaryAssigneeId);
                    $wfAssignmentModalShouldOpen = $errors->hasAny([
                        'primary_assignee_id',
                        'collaborator_ids',
                        'collaborator_ids.*',
                        'due_at',
                        'requirement',
                    ]);
                @endphp

                <header>
                    <div>
                        <span class="wf2-kicker">Khối 2</span>
                        <h3>Nhân sự thực hiện bước này</h3>
                    </div>

                    <div class="wf2-card-actions">
                        <span class="wf2-chip">{{ $wfAssignments->count() }} người</span>

                        @if(!empty($wfPermissions['can_assign']) && $wfStatus !== 'approved')
                            <button type="button"
                                    class="wf2-btn wf2-btn-primary wf2-btn-compact"
                                    data-wf2-open-assignment-modal>
                                <i class="bi {{ $wfAssignments->isEmpty() ? 'bi-person-plus' : 'bi-pencil-square' }}"></i>
                                {{ $wfAssignments->isEmpty() ? 'Giao việc' : 'Chỉnh sửa giao việc' }}
                            </button>
                        @endif
                    </div>
                </header>

                @if($wfAssignments->isEmpty())
                    <div class="wf2-assignment-summary-empty">
                        <span class="wf2-assignment-summary-empty__icon"><i class="bi bi-person-plus"></i></span>
                        <div>
                            <strong>Chưa giao việc cho bước này</strong>
                            <small>Chọn người thực hiện chính, người phối hợp, hạn hoàn thành và yêu cầu cụ thể.</small>
                        </div>
                    </div>
                @else
                    <div class="wf2-assignment-list">
                        @foreach($wfAssignments as $assignment)
                            <article class="wf2-assignment">
                                <span class="wf2-avatar">{{ mb_strtoupper(mb_substr((string) ($assignment->user_name ?: 'NV'), 0, 1)) }}</span>
                                <div>
                                    <strong>{{ $assignment->user_name ?: 'Nhân viên #'.$assignment->user_id }}</strong>
                                    <small>{{ $assignment->assignment_role === 'primary' ? 'Phụ trách chính' : 'Phối hợp' }} · {{ $wfAssignmentLabels[$assignment->status] ?? $assignment->status }}</small>
                                    @if(!empty($assignment->submission_summary))
                                        <p>{{ $assignment->submission_summary }}</p>
                                    @endif
                                </div>
                                <em>{{ (int) ($assignment->progress_percent ?? 0) }}%</em>
                            </article>
                        @endforeach
                    </div>

                    <dl class="wf2-assignment-summary">
                        <div>
                            <dt>Người thực hiện chính</dt>
                            <dd>{{ $wfPrimaryAssignment?->user_name ?: 'Chưa xác định' }}</dd>
                        </div>
                        <div>
                            <dt>Người phối hợp</dt>
                            <dd>
                                {{ $wfCollaboratorAssignments->isNotEmpty()
                                    ? $wfCollaboratorAssignments->pluck('user_name')->filter()->join(', ')
                                    : 'Không có'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Hạn hoàn thành</dt>
                            <dd>{{ $wfSelected['due_at']?->format('d/m/Y H:i') ?? 'Chưa đặt hạn' }}</dd>
                        </div>
                        <div class="full">
                            <dt>Yêu cầu công việc</dt>
                            <dd>{{ filled($wfRow?->requirement) ? $wfRow->requirement : 'Chưa có yêu cầu cụ thể.' }}</dd>
                        </div>
                    </dl>
                @endif

                @if(!empty($wfPermissions['can_assign']) && $wfStatus !== 'approved')
                    <div class="wf2-modal"
                         data-wf2-assignment-modal
                         data-auto-open="{{ $wfAssignmentModalShouldOpen ? '1' : '0' }}"
                         hidden>
                        <button type="button"
                                class="wf2-modal__backdrop"
                                data-wf2-close-assignment-modal
                                tabindex="-1"
                                aria-label="Đóng cửa sổ"></button>

                        <div class="wf2-modal__dialog"
                             role="dialog"
                             aria-modal="true"
                             aria-labelledby="wf2-assignment-modal-title-{{ $wfStepCode }}">
                            <form method="POST"
                                  action="{{ route('projects-unified.workflow.assign', [$site, $wfStepCode]) }}"
                                  class="wf2-assignment-form wf2-assignment-modal-form">
                                @csrf

                                <header class="wf2-modal__header">
                                    <div>
                                        <span class="wf2-kicker">{{ $wfAssignments->isEmpty() ? 'Giao việc' : 'Chỉnh sửa giao việc' }}</span>
                                        <h3 id="wf2-assignment-modal-title-{{ $wfStepCode }}">
                                            Nhân sự thực hiện bước {{ $wfDefinition['label'] ?? $wfStepCode }}
                                        </h3>
                                        <p>Chỉ người được phân công mới nhận việc, cập nhật tiến độ và nộp kết quả.</p>
                                    </div>

                                    <button type="button"
                                            class="wf2-modal__close"
                                            data-wf2-close-assignment-modal
                                            aria-label="Đóng">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </header>

                                <div class="wf2-modal__body">
                                    <label class="wf2-field full">
                                        <span>Người thực hiện chính</span>
                                        <select name="primary_assignee_id" required data-wf2-primary-assignee>
                                            <option value="">Chọn người chịu trách nhiệm chính</option>
                                            @foreach($workflow['assignee_options'] ?? collect() as $option)
                                                <option value="{{ $option->id }}" @selected((int) old('primary_assignee_id', $wfPrimaryAssigneeId) === (int) $option->id)>
                                                    {{ $option->name }}{{ $option->email ? ' · '.$option->email : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="wf2-field-hint">Người này chịu trách nhiệm chính và là đầu mối của riêng bước đang mở.</small>
                                        @error('primary_assignee_id')
                                            <small class="wf2-field-error">{{ $message }}</small>
                                        @enderror
                                    </label>

                                    <div class="wf2-field full">
                                        <span>Người phối hợp</span>
                                        <div class="wf2-assignee-options" data-wf2-collaborator-list>
                                            @forelse($workflow['assignee_options'] ?? collect() as $option)
                                                <label class="wf2-assignee-option">
                                                    <input type="checkbox"
                                                           name="collaborator_ids[]"
                                                           value="{{ $option->id }}"
                                                           data-wf2-collaborator-assignee
                                                           @checked(in_array((int) $option->id, $wfOldCollaborators, true))
                                                           @disabled($wfOldPrimary === (int) $option->id)>
                                                    <span>
                                                        <strong>{{ $option->name }}</strong>
                                                        <small>{{ $option->email ?: 'Không có email' }}</small>
                                                    </span>
                                                </label>
                                            @empty
                                                <div class="wf2-empty-inline">Chưa có nhân sự phù hợp để giao việc.</div>
                                            @endforelse
                                        </div>
                                        <small class="wf2-field-hint">Có thể chọn nhiều người phối hợp. Không chọn lại người thực hiện chính.</small>
                                        @error('collaborator_ids')
                                            <small class="wf2-field-error">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <label class="wf2-field full">
                                        <span>Hạn hoàn thành</span>
                                        <input type="datetime-local"
                                               name="due_at"
                                               value="{{ old('due_at', $wfSelected['due_at']?->format('Y-m-d\TH:i')) }}"
                                               required>
                                        @error('due_at')
                                            <small class="wf2-field-error">{{ $message }}</small>
                                        @enderror
                                    </label>

                                    <label class="wf2-field full">
                                        <span>Yêu cầu cụ thể</span>
                                        <textarea name="requirement"
                                                  rows="5"
                                                  required
                                                  placeholder="Phạm vi công việc, đầu ra cần nộp, lưu ý an toàn...">{{ old('requirement', $wfRow?->requirement) }}</textarea>
                                        @error('requirement')
                                            <small class="wf2-field-error">{{ $message }}</small>
                                        @enderror
                                    </label>
                                </div>

                                <footer class="wf2-modal__footer">
                                    <button type="button"
                                            class="wf2-btn wf2-btn-soft"
                                            data-wf2-close-assignment-modal>
                                        Hủy
                                    </button>
                                    <button class="wf2-btn wf2-btn-primary" type="submit">
                                        <i class="bi bi-person-check"></i>
                                        {{ $wfAssignments->isEmpty() ? 'Giao việc' : 'Lưu thay đổi' }}
                                    </button>
                                </footer>
                            </form>
                        </div>
                    </div>
                @endif

                @if($wfMyAssignment && $wfStatus !== 'approved')
                    <div class="wf2-my-work">
                        <h4>Phần việc của tôi</h4>
                        <div class="wf2-my-actions">
                            @if(in_array($wfMyAssignment->status, ['assigned', 'accepted'], true))
                                <form method="POST" action="{{ route('projects-unified.workflow.accept', [$site, $wfStepCode]) }}">
                                    @csrf
                                    <button class="wf2-btn wf2-btn-soft" type="submit"><i class="bi bi-hand-thumbs-up"></i>Nhận việc</button>
                                </form>
                                <form method="POST" action="{{ route('projects-unified.workflow.start', [$site, $wfStepCode]) }}">
                                    @csrf
                                    <button class="wf2-btn wf2-btn-primary" type="submit"><i class="bi bi-play-fill"></i>Bắt đầu</button>
                                </form>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('projects-unified.workflow.progress', [$site, $wfStepCode]) }}" class="wf2-progress-form">
                            @csrf
                            <label class="wf2-field">
                                <span>Tiến độ phần việc (%)</span>
                                <input type="number" name="progress_percent" min="0" max="100" value="{{ (int) ($wfMyAssignment->progress_percent ?? 0) }}" required>
                            </label>
                            <label class="wf2-field full">
                                <span>Cập nhật đang làm gì</span>
                                <textarea name="submission_summary" rows="2">{{ $wfMyAssignment->submission_summary }}</textarea>
                            </label>
                            <button class="wf2-btn wf2-btn-soft" type="submit"><i class="bi bi-arrow-repeat"></i>Cập nhật</button>
                        </form>

                        @if($wfMyAssignment->status !== 'submitted')
                            <form method="POST" action="{{ route('projects-unified.workflow.assignment.submit', [$site, $wfStepCode]) }}" class="wf2-submit-form">
                                @csrf
                                <label class="wf2-field full">
                                    <span>Tóm tắt kết quả đã thực hiện</span>
                                    <textarea name="submission_summary" rows="4" required placeholder="Đã hoàn thành những gì, kết quả chính và nội dung cần người duyệt lưu ý..."></textarea>
                                </label>
                                <button class="wf2-btn wf2-btn-primary" type="submit" @disabled(empty($wfDocumentState['complete']))>
                                    <i class="bi bi-send-check"></i>
                                    Nộp kết quả của tôi
                                </button>
                                @if(empty($wfDocumentState['complete']))
                                    <small class="wf2-form-warning">Chưa thể nộp. Còn thiếu: {{ implode('; ', $wfDocumentState['missing'] ?? []) }}.</small>
                                @endif
                            </form>
                        @endif
                    </div>
                @endif
            </section>

            <section class="wf2-card wf2-card-documents">
                <header>
                    <div>
                        <span class="wf2-kicker">Khối 3</span>
                        <h3>Hồ sơ bắt buộc</h3>
                    </div>
                    <div class="wf2-card-header-actions">
                        <span class="wf2-chip {{ !empty($wfDocumentState['complete']) ? 'is-success' : 'is-warning' }}">
                            {{ collect($wfDocumentState['items'] ?? [])->where('complete', true)->count() }}/{{ count($wfDocumentState['items'] ?? []) }} đủ
                        </span>
                        @if($wfCanManageDocuments)
                            <button type="button" class="wf2-btn wf2-btn-soft wf2-btn-sm" data-wf2-open-document-settings>
                                <i class="bi bi-gear"></i>
                                Cài đặt hồ sơ
                            </button>
                        @endif
                    </div>
                </header>

                <div class="wf2-document-guide">
                    <i class="bi bi-hand-index-thumb"></i>
                    <span>Bấm vào từng dòng hồ sơ để chọn đúng loại và mở cửa sổ tải file. Dấu hoàn thành được hệ thống tự cập nhật theo file thực tế.</span>
                </div>

                <div class="wf2-document-checklist">
                    @foreach($wfDocumentState['items'] ?? [] as $documentRequirement)
                        @php
                            $wfIsFileRequirement = empty($documentRequirement['is_data_requirement'])
                                && empty($documentRequirement['is_system_requirement']);
                            $wfCanSelectDocument = $wfIsFileRequirement && !empty($wfPermissions['can_upload']);
                            $wfAcceptedExtensions = collect($documentRequirement['extensions'] ?? [])
                                ->map(fn ($extension) => '.'.ltrim((string) $extension, '.'))
                                ->implode(',');
                        @endphp
                        <article class="wf2-doc-requirement {{ !empty($documentRequirement['complete']) ? 'is-complete' : 'is-missing' }} {{ $wfCanSelectDocument ? 'is-selectable' : 'is-automatic' }}"
                                 @if($wfCanSelectDocument)
                                     role="button"
                                     tabindex="0"
                                     data-wf2-document-requirement
                                     data-document-code="{{ $documentRequirement['code'] }}"
                                     data-document-label="{{ $documentRequirement['label'] }}"
                                     data-document-accept="{{ $wfAcceptedExtensions }}"
                                     aria-label="Chọn và tải hồ sơ {{ $documentRequirement['label'] }}"
                                 @endif>
                            <span class="wf2-doc-status">
                                <i class="bi {{ !empty($documentRequirement['complete']) ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
                            </span>
                            <div>
                                <strong>{{ $documentRequirement['label'] }}</strong>
                                <small>
                                    @if(!empty($documentRequirement['is_data_requirement']))
                                        Dữ liệu nhập trực tiếp trên hệ thống
                                    @elseif(!empty($documentRequirement['is_system_requirement']))
                                        Điều kiện được đối chiếu tự động
                                    @else
                                        {{ !empty($documentRequirement['required']) ? 'Bắt buộc' : 'Khi có phát sinh' }}
                                        · Hiện có {{ $documentRequirement['count'] }}/{{ $documentRequirement['minimum'] }}
                                        · {{ $wfCanSelectDocument ? 'Bấm để tải file' : 'Chưa có quyền tải' }}
                                    @endif
                                </small>
                            </div>
                            @if($wfCanSelectDocument)
                                <i class="bi bi-cloud-arrow-up wf2-doc-action-icon"></i>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if(!empty($wfPermissions['can_upload']))
                    <form method="POST"
                          enctype="multipart/form-data"
                          action="{{ route('projects-unified.workflow.documents.upload', [$site, $wfStepCode]) }}"
                          class="wf2-upload-form" data-wf2-upload-form>
                        @csrf
                        <label class="wf2-field">
                            <span>Loại hồ sơ</span>
                            <select name="document_code" required data-wf2-document-select>
                                <option value="">Chọn hồ sơ cần nộp</option>
                                @foreach($wfDefinition['documents'] ?? [] as $documentCode => $documentDefinition)
                                    <option value="{{ $documentCode }}">{{ $documentDefinition['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="wf2-field">
                            <span>Tên hiển thị</span>
                            <input name="title" placeholder="Để trống sẽ lấy tên hồ sơ chuẩn" data-wf2-document-title>
                        </label>
                        <label class="wf2-field full">
                            <span>Chọn file</span>
                            <input type="file" name="file" required data-wf2-document-file>
                        </label>
                        <button class="wf2-btn wf2-btn-primary" type="submit">
                            <i class="bi bi-cloud-arrow-up"></i>
                            Tải hồ sơ
                        </button>
                    </form>
                @endif

                @if($wfDocuments->isNotEmpty())
                    <div class="wf2-files">
                        @foreach($wfDocuments as $document)
                            <a href="{{ asset('storage/'.$document->path) }}" target="_blank" class="wf2-file">
                                <i class="bi bi-file-earmark-check"></i>
                                <span>
                                    <strong>{{ $document->title }}</strong>
                                    <small>v{{ $document->version }} · {{ $document->uploader_name ?: '—' }} · {{ optional($document->created_at ? \Illuminate\Support\Carbon::parse($document->created_at) : null)->format('d/m/Y H:i') }}</small>
                                </span>
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>


            @if($wfCanManageDocuments)
                <div class="wf2-modal wf2-document-settings-modal"
                     data-wf2-document-settings-modal
                     hidden
                     aria-hidden="true">
                    <button type="button"
                            class="wf2-modal__backdrop"
                            data-wf2-close-document-settings
                            aria-label="Đóng cài đặt hồ sơ"></button>

                    <div class="wf2-modal__dialog wf2-document-settings-dialog"
                         role="dialog"
                         aria-modal="true"
                         aria-labelledby="wf2-document-settings-title">
                        <form method="POST"
                              action="{{ route('projects-unified.workflow.documents.settings.save', [$site, $wfStepCode]) }}"
                              data-wf2-document-settings-form>
                            @csrf
                            <header class="wf2-modal__header">
                                <div>
                                    <span class="wf2-kicker">Cấu hình hồ sơ</span>
                                    <h3 id="wf2-document-settings-title">{{ $wfDefinition['label'] ?? $wfStepCode }}</h3>
                                    <p>Thêm, đổi tên, sắp xếp hoặc ngừng áp dụng từng loại hồ sơ. File đã tải vẫn được giữ nguyên lịch sử.</p>
                                </div>
                                <button type="button" class="wf2-modal__close" data-wf2-close-document-settings aria-label="Đóng">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </header>

                            <div class="wf2-document-settings-toolbar">
                                <label class="wf2-field">
                                    <span>Phạm vi áp dụng</span>
                                    <select name="scope" data-wf2-document-settings-scope>
                                        <option value="project">Riêng dự án này</option>
                                        @if($wfCanManageGlobalDocuments)
                                            <option value="global">Mẫu dùng chung cho mọi dự án</option>
                                        @endif
                                    </select>
                                </label>
                                <div class="wf2-document-settings-note">
                                    <i class="bi bi-shield-check"></i>
                                    <span>Bấm nút thùng rác để xóa loại hồ sơ khỏi bước. File đã tải không bị xóa và vẫn được giữ trong lịch sử, phiên bản và audit log.</span>
                                </div>
                            </div>

                            <div class="wf2-document-settings-head" aria-hidden="true">
                                <span>Hồ sơ</span>
                                <span>Bắt buộc</span>
                                <span>Số file</span>
                                <span>Định dạng</span>
                                <span>Người nộp</span>
                                <span>Thao tác</span>
                            </div>

                            <div class="wf2-document-settings-list" data-wf2-document-settings-list>
                                @foreach($wfDocumentSettings as $setting)
                                    <div class="wf2-document-setting-row {{ empty($setting['is_active']) ? 'is-inactive' : '' }}"
                                         data-wf2-document-setting-row>
                                        <input type="hidden" name="documents[{{ $loop->index }}][code]" value="{{ $setting['code'] }}" data-doc-field="code">
                                        <input type="hidden" name="documents[{{ $loop->index }}][conditional_key]" value="{{ $setting['conditional_key'] ?? '' }}" data-doc-field="conditional_key">
                                        <input type="hidden" name="documents[{{ $loop->index }}][sort_order]" value="{{ $setting['sort_order'] ?? (($loop->index + 1) * 10) }}" data-doc-field="sort_order">
                                        <input type="hidden" name="documents[{{ $loop->index }}][is_active]" value="{{ !empty($setting['is_active']) ? 1 : 0 }}" data-doc-field="is_active">

                                        <div class="wf2-document-setting-main">
                                            <span class="wf2-document-setting-handle" title="Sắp xếp">
                                                <i class="bi bi-grip-vertical"></i>
                                            </span>
                                            <input name="documents[{{ $loop->index }}][label]"
                                                   value="{{ $setting['label'] }}"
                                                   maxlength="255"
                                                   required
                                                   data-doc-field="label"
                                                   aria-label="Tên hồ sơ">
                                            <small>{{ ($setting['source'] ?? 'default') === 'default' ? 'Mặc định hệ thống' : (($setting['source'] ?? '') === 'global' ? 'Mẫu dùng chung' : 'Cấu hình dự án') }}</small>
                                        </div>

                                        <label class="wf2-document-setting-check">
                                            <input type="hidden" name="documents[{{ $loop->index }}][required]" value="0">
                                            <input type="checkbox" name="documents[{{ $loop->index }}][required]" value="1" @checked(!empty($setting['required'])) data-doc-field="required">
                                            <span>Bắt buộc</span>
                                        </label>

                                        <input type="number"
                                               min="1"
                                               max="100"
                                               name="documents[{{ $loop->index }}][minimum]"
                                               value="{{ $setting['minimum'] ?? 1 }}"
                                               required
                                               data-doc-field="minimum"
                                               aria-label="Số file tối thiểu">

                                        <input name="documents[{{ $loop->index }}][extensions]"
                                               value="{{ implode(', ', $setting['extensions'] ?? []) }}"
                                               placeholder="pdf, xlsx, jpg"
                                               data-doc-field="extensions"
                                               aria-label="Định dạng cho phép">

                                        <input name="documents[{{ $loop->index }}][responsible_group]"
                                               value="{{ $setting['responsible_group'] ?? '' }}"
                                               placeholder="Kỹ sư khảo sát"
                                               data-doc-field="responsible_group"
                                               aria-label="Người chịu trách nhiệm nộp">

                                        <div class="wf2-document-setting-actions">
                                            <button type="button" class="wf2-icon-btn" data-wf2-doc-setting-up title="Đưa lên"><i class="bi bi-arrow-up"></i></button>
                                            <button type="button" class="wf2-icon-btn" data-wf2-doc-setting-down title="Đưa xuống"><i class="bi bi-arrow-down"></i></button>
                                            <button type="button"
                                                    class="wf2-icon-btn wf2-icon-btn-danger"
                                                    data-wf2-doc-setting-toggle
                                                    title="{{ !empty($setting['is_active']) ? 'Xóa khỏi bước' : 'Khôi phục mục đã xóa' }}"
                                                    aria-label="{{ !empty($setting['is_active']) ? 'Xóa loại hồ sơ khỏi bước' : 'Khôi phục loại hồ sơ' }}">
                                                <i class="bi {{ !empty($setting['is_active']) ? 'bi-trash' : 'bi-arrow-counterclockwise' }}"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <template data-wf2-document-setting-template>
                                <div class="wf2-document-setting-row is-new" data-wf2-document-setting-row>
                                    <input type="hidden" name="documents[__INDEX__][code]" value="__new__" data-doc-field="code">
                                    <input type="hidden" name="documents[__INDEX__][conditional_key]" value="" data-doc-field="conditional_key">
                                    <input type="hidden" name="documents[__INDEX__][sort_order]" value="__ORDER__" data-doc-field="sort_order">
                                    <input type="hidden" name="documents[__INDEX__][is_active]" value="1" data-doc-field="is_active">
                                    <div class="wf2-document-setting-main">
                                        <span class="wf2-document-setting-handle"><i class="bi bi-grip-vertical"></i></span>
                                        <input name="documents[__INDEX__][label]" value="" maxlength="255" required data-doc-field="label" placeholder="Tên loại hồ sơ mới">
                                        <small>Loại hồ sơ mới</small>
                                    </div>
                                    <label class="wf2-document-setting-check">
                                        <input type="hidden" name="documents[__INDEX__][required]" value="0">
                                        <input type="checkbox" name="documents[__INDEX__][required]" value="1" checked data-doc-field="required">
                                        <span>Bắt buộc</span>
                                    </label>
                                    <input type="number" min="1" max="100" name="documents[__INDEX__][minimum]" value="1" required data-doc-field="minimum">
                                    <input name="documents[__INDEX__][extensions]" value="pdf" placeholder="pdf, xlsx, jpg" data-doc-field="extensions">
                                    <input name="documents[__INDEX__][responsible_group]" value="" placeholder="Người chịu trách nhiệm" data-doc-field="responsible_group">
                                    <div class="wf2-document-setting-actions">
                                        <button type="button" class="wf2-icon-btn" data-wf2-doc-setting-up title="Đưa lên"><i class="bi bi-arrow-up"></i></button>
                                        <button type="button" class="wf2-icon-btn" data-wf2-doc-setting-down title="Đưa xuống"><i class="bi bi-arrow-down"></i></button>
                                        <button type="button" class="wf2-icon-btn wf2-icon-btn-danger" data-wf2-doc-setting-toggle title="Xóa dòng mới"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                            </template>

                            <footer class="wf2-modal__footer wf2-document-settings-footer">
                                <button type="button" class="wf2-btn wf2-btn-soft" data-wf2-add-document-setting>
                                    <i class="bi bi-plus-lg"></i>
                                    Thêm loại hồ sơ
                                </button>
                                <button type="button" class="wf2-btn wf2-btn-soft" data-wf2-reset-document-settings>
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                    Khôi phục mặc định
                                </button>
                                <button type="button"
                                        class="wf2-btn wf2-btn-soft wf2-deleted-documents-toggle"
                                        data-wf2-toggle-deleted-document-settings
                                        hidden>
                                    <i class="bi bi-trash3"></i>
                                    <span data-wf2-deleted-document-count>Mục đã xóa (0)</span>
                                </button>
                                <span class="wf2-document-settings-spacer"></span>
                                <button type="button" class="wf2-btn wf2-btn-soft" data-wf2-close-document-settings>Hủy</button>
                                <button type="submit" class="wf2-btn wf2-btn-primary">
                                    <i class="bi bi-check2-circle"></i>
                                    Lưu cấu hình
                                </button>
                            </footer>
                        </form>

                        <form method="POST"
                              action="{{ route('projects-unified.workflow.documents.settings.reset', [$site, $wfStepCode]) }}"
                              data-wf2-document-settings-reset-form
                              hidden>
                            @csrf
                            <input type="hidden" name="scope" value="project" data-wf2-reset-scope>
                        </form>
                    </div>
                </div>
            @endif

            <section class="wf2-card wf2-card-approval">
                <header>
                    <div>
                        <span class="wf2-kicker">Khối 4</span>
                        <h3>Nộp kết quả và duyệt</h3>
                    </div>
                    <span class="wf2-chip">{{ count($wfDefinition['approval_tracks'] ?? []) }} cấp duyệt</span>
                </header>

                @if(!empty($wfDocumentState['missing']))
                    <div class="wf2-missing-box">
                        <strong>Chưa thể hoàn tất bước vì còn thiếu:</strong>
                        <ul>
                            @foreach($wfDocumentState['missing'] as $missingItem)
                                <li>{{ $missingItem }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="wf2-approval-tracks">
                    @foreach($wfDefinition['approval_tracks'] ?? [] as $approvalType => $approvalTrack)
                        @php
                            $approval = $wfApprovals->firstWhere('approval_type', $approvalType);
                            $approvalStatus = (string) ($approval->status ?? 'pending');
                        @endphp
                        <article class="wf2-approval-track wf2-approval-{{ $approvalStatus }}">
                            <div>
                                <span>{{ $approvalTrack['label'] }}</span>
                                <strong>{{ $wfApprovalLabels[$approvalStatus] ?? $approvalStatus }}</strong>
                                @if(!empty($approval?->reviewer_name))
                                    <small>{{ $approval->reviewer_name }} · {{ !empty($approval->reviewed_at) ? \Illuminate\Support\Carbon::parse($approval->reviewed_at)->format('d/m/Y H:i') : '' }}</small>
                                @endif
                                @if(!empty($approval?->note))
                                    <p>{{ $approval->note }}</p>
                                @endif
                            </div>

                            @if($wfStatus === 'submitted' && !empty($wfApprovalPermissions[$approvalType]))
                                <div class="wf2-approval-actions">
                                    <form method="POST" action="{{ route('projects-unified.workflow.approve', [$site, $wfStepCode]) }}">
                                        @csrf
                                        <input type="hidden" name="approval_type" value="{{ $approvalType }}">
                                        <input type="text" name="note" placeholder="Ý kiến duyệt (không bắt buộc)">
                                        <button class="wf2-btn wf2-btn-success" type="submit">
                                            <i class="bi bi-check2-circle"></i>
                                            Duyệt
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('projects-unified.workflow.revise', [$site, $wfStepCode]) }}">
                                        @csrf
                                        <input type="hidden" name="approval_type" value="{{ $approvalType }}">
                                        <textarea name="reason" rows="2" required placeholder="Lý do trả lại và nội dung cần sửa"></textarea>
                                        <button class="wf2-btn wf2-btn-danger" type="submit">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                            Trả lại
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if(!empty($wfPermissions['can_request_exception']) && $wfStatus !== 'approved')
                    <details class="wf2-exception">
                        <summary>Duyệt ngoại lệ chuyển bước</summary>
                        <form method="POST" action="{{ route('projects-unified.workflow.exception', [$site, $wfStepCode]) }}" onsubmit="return confirm('Chuyển bước ngoại lệ sẽ được lưu vết. Tiếp tục?');">
                            @csrf
                            <label class="wf2-field">
                                <span>Chuyển tới bước</span>
                                <select name="to_step" required>
                                    @foreach($workflow['definitions'] ?? [] as $targetCode => $targetDefinition)
                                        @if(($targetDefinition['sequence'] ?? 0) > ($wfDefinition['sequence'] ?? 0))
                                            <option value="{{ $targetCode }}">Bước {{ $targetDefinition['sequence'] }} · {{ $targetDefinition['label'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </label>
                            <label class="wf2-field full">
                                <span>Lý do ngoại lệ</span>
                                <textarea name="reason" rows="3" required></textarea>
                            </label>
                            <button class="wf2-btn wf2-btn-danger" type="submit">Duyệt ngoại lệ</button>
                        </form>
                    </details>
                @endif
            </section>
        </div>

        <section class="wf2-card wf2-history">
            <header>
                <div>
                    <span class="wf2-kicker">Audit log</span>
                    <h3>Lịch sử bước</h3>
                </div>
                <span class="wf2-chip">{{ ($workflow['events'] ?? collect())->count() }} hoạt động gần nhất</span>
            </header>

            @if(($workflow['events'] ?? collect())->isEmpty())
                <div class="wf2-empty-inline">Chưa có lịch sử thao tác.</div>
            @else
                <div class="wf2-timeline">
                    @foreach($workflow['events'] as $event)
                        <article>
                            <span><i class="bi bi-clock-history"></i></span>
                            <div>
                                <strong>{{ str_replace('_', ' ', ucfirst((string) $event->action)) }}</strong>
                                <small>{{ $event->actor_name ?: 'Hệ thống' }} · {{ !empty($event->created_at) ? \Illuminate\Support\Carbon::parse($event->created_at)->format('d/m/Y H:i') : '—' }}</small>
                                @if(!empty($event->note))
                                    <p>{{ $event->note }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
