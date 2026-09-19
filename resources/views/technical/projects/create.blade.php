@extends('layouts.app')

@section('title', 'Tạo công trình Kỹ thuật')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/project-test.css') }}?v={{ file_exists(public_path('css/project-test.css')) ? filemtime(public_path('css/project-test.css')) : time() }}">

<style>
.pt-tech-assignment-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    align-items: start;
}

.pt-collab-panel {
    margin-top: 16px;
    padding: 18px;
    border: 1px solid #dce7f3;
    border-radius: 16px;
    background: #f8fbff;
}

.pt-collab-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
}

.pt-collab-head h3 {
    margin: 0 0 4px;
    color: #102b48;
    font-size: 15px;
}

.pt-collab-head p {
    margin: 0;
    color: #75869b;
    font-size: 12px;
    line-height: 1.55;
}

.pt-collab-count {
    flex: 0 0 auto;
    padding: 7px 11px;
    border-radius: 999px;
    background: #e5f7f2;
    color: #087b68;
    font-size: 12px;
    font-weight: 700;
}

.pt-tech-search {
    position: relative;
    margin-bottom: 12px;
}

.pt-tech-search i {
    position: absolute;
    top: 50%;
    left: 14px;
    transform: translateY(-50%);
    color: #8192a8;
    pointer-events: none;
}

.pt-tech-search input {
    padding-left: 40px;
}

.pt-collab-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    max-height: 290px;
    overflow-y: auto;
    padding: 2px;
}

.pt-collab-card {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid #dbe6f1;
    border-radius: 13px;
    background: #fff;
    cursor: pointer;
    transition:
        border-color .18s ease,
        box-shadow .18s ease,
        background .18s ease;
}

.pt-collab-card:hover {
    border-color: #50b7a8;
    box-shadow: 0 5px 16px rgba(15, 72, 94, .08);
}

.pt-collab-card.is-selected {
    border-color: #0ba58c;
    background: #effbf8;
}

.pt-collab-card.is-lead {
    display: none;
}

.pt-collab-card input {
    width: 18px;
    height: 18px;
    margin: 0;
    flex: 0 0 auto;
    accent-color: #0b9f88;
}

.pt-collab-person {
    min-width: 0;
}

.pt-collab-person strong {
    display: block;
    overflow: hidden;
    color: #132e4a;
    font-size: 13px;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.pt-collab-person span {
    display: block;
    margin-top: 2px;
    color: #8493a7;
    font-size: 11px;
}

.pt-collab-selected {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 12px;
}

.pt-collab-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 9px;
    border-radius: 999px;
    background: #e5f7f2;
    color: #087b68;
    font-size: 11px;
    font-weight: 700;
}

.pt-collab-empty {
    grid-column: 1 / -1;
    padding: 18px;
    border: 1px dashed #cad8e7;
    border-radius: 12px;
    background: #fff;
    color: #8795a8;
    text-align: center;
}

@media (max-width: 900px) {
    .pt-tech-assignment-grid {
        grid-template-columns: 1fr;
    }

    .pt-collab-list {
        grid-template-columns: 1fr;
        max-height: none;
    }

    .pt-collab-panel {
        padding: 14px;
    }

    .pt-collab-head {
        flex-direction: column;
        gap: 8px;
    }
}
</style>
@endpush

@section('content')
<?php
$selectedTechnicianIds = array_values(array_unique(
    array_map('intval', (array) old('technician_ids', []))
));

$selectedLeadTechnicianId = (int) old(
    'lead_technician_id',
    0
);

$selectedCollaboratorIds = array_values(array_filter(
    $selectedTechnicianIds,
    fn ($id) => (int) $id !== $selectedLeadTechnicianId
));
?>
<div class="pt-page">
    <div class="pt-shell pt-intake-page">
        <section class="pt-hero pt-hero--technical">
            <div class="pt-hero__row">
                <div>
                    <div class="pt-kicker"><i class="bi bi-tools"></i> PHÒNG KỸ THUẬT <span class="pt-new">TỰ TẠO</span></div>
                    <h1>Tạo công trình Kỹ thuật</h1>
                    <p>Dùng cho công trình nội bộ, khảo sát độc lập, đo kiểm, sửa chữa, bảo trì, bảo hành hoặc công trình không thông qua Sales.</p>
                </div>
                <div class="pt-actions">
                    <a href="{{ route('technical-projects.created') }}" class="pt-btn pt-btn--light"><i class="bi bi-arrow-left"></i> Công trình Kỹ thuật</a>
                </div>
            </div>
        </section>

        <?php if ($errors->any()): ?>
            <div class="pt-alert"><strong>Chưa thể lưu:</strong> {{ $errors->first() }}</div>
        <?php endif; ?>

        <form method="POST" action="{{ route('technical-projects.store') }}" class="pt-card pt-section pt-intake-form">
            @csrf
            <input type="hidden" name="sales_user_id" value="">

            <div class="pt-section__head">
                <div><h2>1. Phân loại nghiệp vụ</h2><p>Không cần Sales, đơn hàng hoặc hợp đồng. Trưởng phòng Kỹ thuật có thể phân công ngay khi tạo.</p></div>
                <span class="pt-source-chip pt-source-chip--technical">KỸ THUẬT TẠO</span>
            </div>

            <div class="pt-form-grid pt-form-grid--3">
                <div><label class="pt-label">Nguồn công trình</label><select class="pt-select" name="request_source" required><?php foreach ($technicalRequestSources as $value => $label): ?><option value="{{ $value }}" {{ old('request_source', 'technical') === $value ? 'selected' : '' }}>{{ $label }}</option><?php endforeach; ?></select></div>
                <div><label class="pt-label">Loại nghiệp vụ</label><select class="pt-select" name="project_type" required><?php foreach ($technicalProjectTypes as $value => $label): ?><option value="{{ $value }}" {{ old('project_type', 'survey') === $value ? 'selected' : '' }}>{{ $label }}</option><?php endforeach; ?></select></div>
                <div><label class="pt-label">Mức ưu tiên</label><select class="pt-select" name="priority"><option value="normal">Bình thường</option><option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>Cao</option><option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Khẩn</option><option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Thấp</option></select></div>
                <div><label class="pt-label">Công ty</label><select class="pt-select" name="company_id"><option value="">-- Công ty hiện tại --</option><?php foreach ($companies as $company): ?><option value="{{ $company->id }}" {{ (int) old('company_id', $activeCompanyId) === (int) $company->id ? 'selected' : '' }}>{{ $company->name }}</option><?php endforeach; ?></select></div>
                <div><label class="pt-label">Khách hàng CRM (nếu có)</label><select class="pt-select" name="customer_id" data-customer-select><option value="">-- Không bắt buộc --</option><?php foreach ($customers as $customer): ?><option value="{{ $customer->id }}" data-name="{{ $customer->name }}" data-phone="{{ $customer->phone }}" data-address="{{ $customer->address }}" {{ (int) old('customer_id') === (int) $customer->id ? 'selected' : '' }}>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option><?php endforeach; ?></select></div>
                <div><label class="pt-label">Cần xác nhận bên yêu cầu trước triển khai</label><label class="pt-switch-row"><input type="checkbox" name="customer_confirmation_required" value="1" {{ old('customer_confirmation_required') ? 'checked' : '' }}><span>Có bước xác nhận phương án / chi phí</span></label></div>
            </div>

            <div class="pt-divider"></div>
            <div class="pt-section__head"><div><h2>2. Nội dung công trình</h2><p>Có thể là địa điểm khách hàng, công ty, kho, văn phòng hoặc khu vực cần hỗ trợ hiện trường.</p></div></div>
            <div class="pt-form-grid pt-form-grid--3">
                <div class="pt-field--full"><label class="pt-label">Tên công trình / công việc <span class="pt-required">*</span></label><input class="pt-input" name="name" value="{{ old('name') }}" required placeholder="VD: Đo kiểm hệ thống kho EGO – Bình Dương"></div>
                <div class="pt-field--full"><label class="pt-label">Địa điểm thực hiện <span class="pt-required">*</span></label><input class="pt-input" name="address" value="{{ old('address') }}" data-address required></div>
                <div><label class="pt-label">Đơn vị / người yêu cầu</label><input class="pt-input" name="contact_name" value="{{ old('contact_name') }}" data-contact-name></div>
                <div><label class="pt-label">Số điện thoại</label><input class="pt-input" name="contact_phone" value="{{ old('contact_phone') }}" data-contact-phone></div>
                <div><label class="pt-label">Loại hệ thống</label><input class="pt-input" name="system_type" value="{{ old('system_type') }}"></div>
                <div><label class="pt-label">Công suất dự kiến (kWp)</label><input class="pt-input" type="number" step="0.01" min="0" name="estimated_kwp" value="{{ old('estimated_kwp') }}"></div>
                <div><label class="pt-label">Mục tiêu hoàn thành</label><input class="pt-input" type="date" name="target_completion_at" value="{{ old('target_completion_at') }}"></div>
                <div class="pt-field--full"><label class="pt-label">Nội dung cần thực hiện <span class="pt-required">*</span></label><textarea class="pt-textarea" name="customer_need" required placeholder="Khảo sát, đo kiểm, sửa chữa, bảo trì, lắp đặt hoặc hỗ trợ kỹ thuật...">{{ old('customer_need') }}</textarea></div>
                <div class="pt-field--full"><label class="pt-label">Yêu cầu đầu ra / ghi chú</label><textarea class="pt-textarea" name="note" placeholder="Biên bản, ảnh hiện trường, báo cáo đo kiểm, phương án xử lý...">{{ old('note') }}</textarea></div>
            </div>

            <div class="pt-divider"></div>
            <div class="pt-section__head"><div><h2>3. Điều phối ban đầu</h2><p>Có thể phân công sau hoặc chọn kỹ thuật viên và lịch khảo sát ngay.</p></div></div>
            <div class="pt-tech-assignment-grid">
                <div>
                    <label class="pt-label">
                        Trưởng phòng phụ trách
                    </label>

                    <select
                        class="pt-select"
                        name="technical_manager_id"
                    >
                        <option value="">
                            -- Người tạo tiếp nhận --
                        </option>

                        <?php foreach ($technicalManagers as $user): ?>
                            <option
                                value="{{ $user->id }}"
                                {{ (int) old('technical_manager_id', $defaultTechnicalManagerId) === (int) $user->id ? 'selected' : '' }}
                            >
                                {{ $user->name }}
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="pt-label">
                        Kỹ thuật chính
                    </label>

                    <select
                        class="pt-select"
                        name="lead_technician_id"
                        data-lead-technician
                    >
                        <option value="">
                            -- Phân công sau --
                        </option>

                        <?php foreach ($technicians as $user): ?>
                            <option
                                value="{{ $user->id }}"
                                {{ $selectedLeadTechnicianId === (int) $user->id ? 'selected' : '' }}
                            >
                                {{ $user->name }}
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small class="pt-help">
                        Người chịu trách nhiệm chính của công trình.
                    </small>
                </div>

                <div>
                    <label class="pt-label">
                        Lịch dự kiến
                    </label>

                    <input
                        class="pt-input"
                        type="datetime-local"
                        name="proposed_survey_at"
                        value="{{ old('proposed_survey_at') }}"
                    >
                </div>
            </div>

            <div
                class="pt-collab-panel"
                data-collaborator-picker
            >
                <div class="pt-collab-head">
                    <div>
                        <h3>Kỹ thuật phối hợp</h3>

                        <p>
                            Tích chọn những người cùng tham gia.
                            Kỹ thuật chính tự động được loại khỏi danh sách.
                        </p>
                    </div>

                    <span class="pt-collab-count">
                        <span data-collab-count>0</span>
                        người đã chọn
                    </span>
                </div>

                <div class="pt-tech-search">
                    <i class="bi bi-search"></i>

                    <input
                        type="search"
                        class="pt-input"
                        placeholder="Tìm kỹ thuật viên..."
                        data-collab-search
                    >
                </div>

                <div
                    class="pt-collab-list"
                    data-collab-list
                >
                    <?php foreach ($technicians as $user): ?>
                        <?php
                        $isSelected = in_array(
                            (int) $user->id,
                            $selectedCollaboratorIds,
                            true
                        );
                        ?>

                        <label
                            class="pt-collab-card {{ $isSelected ? 'is-selected' : '' }}"
                            data-collaborator-card
                            data-search-name="{{ $user->name }}"
                        >
                            <input
                                type="checkbox"
                                name="technician_ids[]"
                                value="{{ $user->id }}"
                                data-collaborator-checkbox
                                {{ $isSelected ? 'checked' : '' }}
                            >

                            <span class="pt-collab-person">
                                <strong>{{ $user->name }}</strong>

                                <span>
                                    Kỹ thuật viên phối hợp
                                </span>
                            </span>
                        </label>
                    <?php endforeach; ?>

                    <div
                        class="pt-collab-empty"
                        data-collab-empty
                        hidden
                    >
                        Không tìm thấy kỹ thuật viên phù hợp.
                    </div>
                </div>

                <div
                    class="pt-collab-selected"
                    data-collab-selected
                ></div>

                <small class="pt-help">
                    Khi chọn người phối hợp, vui lòng chọn Kỹ thuật chính.
                </small>
            </div>

            <div class="pt-intake-footer">
                <div><strong>Không thông qua Sales</strong><span>Hồ sơ thuộc quyền điều phối của Phòng Kỹ thuật và không xuất hiện trong danh sách Sales.</span></div>
                <button class="pt-btn pt-btn--brand" type="submit"><i class="bi bi-plus-circle"></i> Tạo công trình Kỹ thuật</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const leadSelect = document.querySelector(
        '[data-lead-technician]'
    );

    const searchInput = document.querySelector(
        '[data-collab-search]'
    );

    const cards = Array.from(
        document.querySelectorAll(
            '[data-collaborator-card]'
        )
    );

    const countNode = document.querySelector(
        '[data-collab-count]'
    );

    const selectedNode = document.querySelector(
        '[data-collab-selected]'
    );

    const emptyNode = document.querySelector(
        '[data-collab-empty]'
    );

    const normalize = (value) => (value || '')
        .toLocaleLowerCase('vi-VN')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    function refreshCollaborators() {
        const leadId = leadSelect
            ? leadSelect.value
            : '';

        const keyword = normalize(
            searchInput
                ? searchInput.value
                : ''
        );

        let visibleCount = 0;
        const selectedPeople = [];

        cards.forEach((card) => {
            const checkbox = card.querySelector(
                '[data-collaborator-checkbox]'
            );

            if (!checkbox) {
                return;
            }

            const isLead = Boolean(leadId)
                && String(checkbox.value) === String(leadId);

            if (isLead) {
                checkbox.checked = false;
            }

            checkbox.disabled = isLead;

            card.classList.toggle(
                'is-lead',
                isLead
            );

            card.classList.toggle(
                'is-selected',
                checkbox.checked
            );

            const matchesSearch = !keyword
                || normalize(
                    card.dataset.searchName || ''
                ).includes(keyword);

            const shouldShow = !isLead && matchesSearch;

            card.hidden = !shouldShow;

            if (shouldShow) {
                visibleCount++;
            }

            if (checkbox.checked && !isLead) {
                const name = card
                    .querySelector('strong')
                    ?.textContent
                    .trim() || 'Kỹ thuật viên';

                selectedPeople.push(name);
            }
        });

        if (countNode) {
            countNode.textContent = String(
                selectedPeople.length
            );
        }

        if (emptyNode) {
            emptyNode.hidden = visibleCount > 0;
        }

        if (selectedNode) {
            selectedNode.innerHTML = '';

            selectedPeople.forEach((name) => {
                const chip = document.createElement('span');

                chip.className = 'pt-collab-chip';

                chip.innerHTML =
                    '<i class="bi bi-check2-circle"></i>';

                chip.appendChild(
                    document.createTextNode(name)
                );

                selectedNode.appendChild(chip);
            });
        }
    }

    leadSelect?.addEventListener(
        'change',
        refreshCollaborators
    );

    searchInput?.addEventListener(
        'input',
        refreshCollaborators
    );

    cards.forEach((card) => {
        card.querySelector(
            '[data-collaborator-checkbox]'
        )?.addEventListener(
            'change',
            refreshCollaborators
        );
    });

    refreshCollaborators();

    const customer = document.querySelector(
        '[data-customer-select]'
    );

    customer?.addEventListener('change', () => {
        const option =
            customer.options[customer.selectedIndex];

        if (!option) {
            return;
        }

        const name = document.querySelector(
            '[data-contact-name]'
        );

        const phone = document.querySelector(
            '[data-contact-phone]'
        );

        const address = document.querySelector(
            '[data-address]'
        );

        if (name && !name.value) {
            name.value = option.dataset.name || '';
        }

        if (phone && !phone.value) {
            phone.value = option.dataset.phone || '';
        }

        if (address && !address.value) {
            address.value = option.dataset.address || '';
        }
    });
})();
</script>
@endpush
