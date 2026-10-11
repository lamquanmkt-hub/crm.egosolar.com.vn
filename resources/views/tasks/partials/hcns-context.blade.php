{{--
    Khối "Thủ tục hành chính HCNS" trên form giao/sửa việc (nguồn KPI HC09).
    Chỉ hiện khi DB đã có cột nguồn HCNS và không ở ngữ cảnh Kỹ thuật (TaskController::hcnsFormData).
--}}
@if($hcnsAvailable ?? false)
    @php
        $hcnsTask = $task ?? null;
        $hcnsLocked = $hcnsTask && \App\Services\Hr\Kpi\HcnsAdminTasks::is($hcnsTask);
        $hcnsOn = $hcnsLocked || old('hcns_task') === '1';
        $hcnsType = (string) old('hcns_procedure_type', $hcnsTask->hcns_procedure_type ?? '');
        $hcnsApprover = (string) old('approver_id', $hcnsTask->approver_id ?? '');
        $hcnsTime = (string) old('due_time', $hcnsTask && $hcnsTask->due_at ? \Illuminate\Support\Carbon::parse($hcnsTask->due_at)->format('H:i') : '17:00');
    @endphp
    <div class="hcns-task-context mb-3" data-hcns-context>
        <label class="hcns-task-context__toggle">
            @if($hcnsLocked)
                <input type="hidden" name="hcns_task" value="1">
                <input type="checkbox" checked disabled>
            @else
                <input type="checkbox" name="hcns_task" value="1" @checked($hcnsOn) data-hcns-toggle>
            @endif
            <span>
                <strong>Thủ tục hành chính / pháp lý HCNS — tính KPI HC09</strong>
                <small>Chỉ đánh dấu việc thủ tục được giao theo hạn (giấy phép, hồ sơ pháp lý…). Việc thường không chọn.</small>
            </span>
        </label>

        <div class="hcns-task-context__body" data-hcns-fields @if(! $hcnsOn) hidden @endif>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Loại thủ tục <span class="text-danger">*</span></label>
                    <select name="hcns_procedure_type" class="form-select" data-hcns-required>
                        <option value="">Chọn loại thủ tục</option>
                        @foreach($hcnsProcedureTypes as $code => $label)
                            <option value="{{ $code }}" @selected($hcnsType === (string) $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Giờ hạn <span class="text-danger">*</span></label>
                    <input type="time" name="due_time" class="form-control" value="{{ $hcnsTime }}" data-hcns-required>
                    <div class="form-text">Ngày hạn chọn ở ô “Hạn hoàn thành”.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Người kiểm tra / xác nhận <span class="text-danger">*</span></label>
                    <select name="approver_id" class="form-select" data-hcns-required>
                        <option value="">Chọn người kiểm</option>
                        @foreach($hcnsReviewers as $reviewer)
                            <option value="{{ $reviewer->id }}" @selected($hcnsApprover === (string) $reviewer->id)>{{ $reviewer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="hcns-task-context__note">
                <i class="bi bi-info-circle"></i>
                <span>
                    <b>Người nhận việc</b> lưu nháp hoặc gửi kết quả kèm minh chứng → <b>người kiểm</b> (khác người làm) xác nhận hoặc trả bổ sung.
                    KPI HC09 chỉ xét việc đã được xác nhận hoặc đã quá hạn chưa nộp; mốc đo/kỳ là quy tắc nháp, chưa duyệt.
                    @if($hcnsLocked) Thủ tục đã giao không đổi loại; muốn bỏ thì dùng “Hủy thủ tục” có lý do. Trạng thái chỉ đổi qua Nộp / Duyệt / Trả / Hủy. @endif
                </span>
            </div>
        </div>
    </div>

    @once
        @push('styles')
            <style>
                .hcns-task-context{border:1px solid #d5e3f3;border-radius:14px;background:#f7fbff;padding:12px 14px}
                .hcns-task-context__toggle{display:flex;gap:10px;align-items:flex-start;cursor:pointer;margin:0}
                .hcns-task-context__toggle input{margin-top:4px;width:18px;height:18px}
                .hcns-task-context__toggle strong{display:block;color:#123a64;font-size:14px}
                .hcns-task-context__toggle small{display:block;color:#5b6f86;font-size:12.5px}
                .hcns-task-context__body{margin-top:12px}
                .hcns-task-context__note{display:flex;gap:8px;margin-top:10px;font-size:12.5px;color:#334155;background:#fff;border:1px dashed #c9d8ea;border-radius:10px;padding:8px 10px}
            </style>
        @endpush
        @push('scripts')
            <script>
                document.querySelectorAll('[data-hcns-context]').forEach(function (box) {
                    var toggle = box.querySelector('[data-hcns-toggle]');
                    var fields = box.querySelector('[data-hcns-fields]');
                    if (!toggle || !fields) { return; }
                    var sync = function () {
                        fields.hidden = !toggle.checked;
                        fields.querySelectorAll('[data-hcns-required]').forEach(function (el) { el.required = toggle.checked; });
                    };
                    toggle.addEventListener('change', sync);
                    sync();
                });
            </script>
        @endpush
    @endonce
@endif
