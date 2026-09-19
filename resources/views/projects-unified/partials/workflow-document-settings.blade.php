@php($ewdSettings = collect($workflow['document_settings'] ?? []))
<dialog class="pword-assign-dialog ewd-settings-dialog" id="{{ $wfSettingsDialogId }}">
    <div class="ewd-settings-card">
        <header class="ewd-settings-header"><div><small>CHỈ QUẢN TRỊ VIÊN</small><h3>Cài đặt hồ sơ · {{ $wfDefinition['label'] ?? $wfStepCode }}</h3><p>Thêm, đổi tên, sắp xếp và quy định số lượng hồ sơ theo từng giai đoạn.</p></div><button type="button" data-pword-close-dialog aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
        <form method="POST" action="{{ route('projects-unified.workflow.documents.settings.save', [$site, $wfStepCode]) }}" data-ewd-settings-form>
            @csrf
            <div class="ewd-settings-scope"><label>Phạm vi áp dụng<select name="scope"><option value="project">Chỉ công trình này</option><option value="global">Tất cả công trình</option></select></label><span><i class="bi bi-shield-check"></i> Tệp đã tải được giữ nguyên khi đổi cấu hình.</span></div>
            <div class="ewd-settings-grid is-header"><span>Tên hồ sơ</span><span>Bắt buộc</span><span>Tối thiểu</span><span>Tối đa</span><span>Định dạng</span><span>Hiện</span><span>Xóa</span></div>
            <div class="ewd-settings-rows" data-ewd-setting-rows>
                @foreach($ewdSettings as $setting)
                    <div class="ewd-settings-grid" data-ewd-setting-row>
                        <input type="hidden" name="documents[{{ $loop->index }}][code]" value="{{ $setting['code'] }}">
                        <input type="hidden" name="documents[{{ $loop->index }}][conditional_key]" value="{{ $setting['conditional_key'] ?? '' }}">
                        <input type="hidden" name="documents[{{ $loop->index }}][responsible_group]" value="{{ $setting['responsible_group'] ?? '' }}">
                        <input type="hidden" name="documents[{{ $loop->index }}][sort_order]" value="{{ $setting['sort_order'] ?? (($loop->index + 1) * 10) }}">
                        <label><input type="text" name="documents[{{ $loop->index }}][label]" value="{{ $setting['label'] }}" maxlength="255" required><small>{{ ($setting['source'] ?? 'default') === 'global' ? 'Mẫu dùng chung' : (($setting['source'] ?? 'default') === 'project' ? 'Riêng công trình' : 'Mặc định') }}</small></label>
                        <label class="ewd-toggle"><input type="hidden" name="documents[{{ $loop->index }}][required]" value="0"><input type="checkbox" name="documents[{{ $loop->index }}][required]" value="1" @checked(!empty($setting['required']))></label>
                        <input type="number" name="documents[{{ $loop->index }}][minimum]" min="1" max="100" value="{{ $setting['minimum'] ?? 1 }}" required>
                        <input type="number" name="documents[{{ $loop->index }}][maximum]" min="1" max="1000" value="{{ $setting['maximum'] ?? '' }}" placeholder="∞">
                        <input type="text" name="documents[{{ $loop->index }}][extensions]" value="{{ implode(', ', $setting['extensions'] ?? []) }}" placeholder="pdf, jpg, xlsx">
                        <label class="ewd-toggle"><input type="hidden" name="documents[{{ $loop->index }}][is_active]" value="0"><input type="checkbox" name="documents[{{ $loop->index }}][is_active]" value="1" @checked(!empty($setting['is_active']))></label>
                        <button type="button" class="ewd-delete-setting" data-ewd-delete-setting title="Xóa danh mục hồ sơ" aria-label="Xóa danh mục hồ sơ"><i class="bi bi-trash3"></i></button>
                    </div>
                @endforeach
            </div>
            <template data-ewd-setting-template>
                <div class="ewd-settings-grid" data-ewd-setting-row>
                    <input type="hidden" name="documents[__INDEX__][code]" value="__new__"><input type="hidden" name="documents[__INDEX__][conditional_key]" value=""><input type="hidden" name="documents[__INDEX__][responsible_group]" value=""><input type="hidden" name="documents[__INDEX__][sort_order]" value="__ORDER__">
                    <label><input type="text" name="documents[__INDEX__][label]" maxlength="255" placeholder="Tên hồ sơ mới" required><small>Danh mục mới</small></label>
                    <label class="ewd-toggle"><input type="hidden" name="documents[__INDEX__][required]" value="0"><input type="checkbox" name="documents[__INDEX__][required]" value="1" checked></label>
                    <input type="number" name="documents[__INDEX__][minimum]" min="1" max="100" value="1" required>
                    <input type="number" name="documents[__INDEX__][maximum]" min="1" max="1000" placeholder="∞">
                    <input type="text" name="documents[__INDEX__][extensions]" value="pdf, jpg, png" placeholder="pdf, jpg, xlsx">
                    <label class="ewd-toggle"><input type="hidden" name="documents[__INDEX__][is_active]" value="0"><input type="checkbox" name="documents[__INDEX__][is_active]" value="1" checked></label>
                    <button type="button" class="ewd-delete-setting" data-ewd-delete-setting title="Xóa danh mục hồ sơ" aria-label="Xóa danh mục hồ sơ"><i class="bi bi-trash3"></i></button>
                </div>
            </template>
            <footer class="ewd-settings-footer"><button type="button" class="pword-btn light" data-ewd-add-setting><i class="bi bi-plus-lg"></i> Thêm hồ sơ</button><span></span><button type="button" class="pword-btn light" data-pword-close-dialog>Hủy</button><button type="submit" class="pword-btn primary"><i class="bi bi-check2-circle"></i> Lưu cấu hình</button></footer>
        </form>
    </div>
</dialog>
