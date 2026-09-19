@extends('layouts.app')

@section('title', 'Tạo công trình · EGO Solar')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/projects-unified-v1.css') }}?v={{ file_exists(public_path('css/projects-unified-v1.css')) ? filemtime(public_path('css/projects-unified-v1.css')) : time() }}">
@endpush

@section('content')
@php
    $typeLabels = [
        'solar_farm' => 'Solar Farm',
        'factory' => 'Nhà xưởng',
        'industrial' => 'Công trình công nghiệp',
        'large_residential' => 'Dân dụng quy mô lớn',
        'other' => 'Khác',
    ];
    $priorityLabels = ['low'=>'Thấp','normal'=>'Bình thường','high'=>'Cao','urgent'=>'Khẩn cấp'];
@endphp

<div class="pu-page">
    <div class="pu-shell">
        <header class="pu-header">
            <div>
                <div class="pu-eyebrow">Khởi tạo hồ sơ công trình</div>
                <h1 class="pu-title">Tạo công trình mới</h1>
                <p class="pu-subtitle">Sales và Kỹ thuật cùng sử dụng một hồ sơ công trình. Tiến độ và tài chính được theo dõi tại đây.</p>
            </div>
            <div class="pu-header-actions"><a class="pu-btn pu-btn-soft" href="{{ route('projects-unified.index') }}"><i class="bi bi-arrow-left"></i>Quay lại</a></div>
        </header>

        @if($errors->any())
            <div class="alert alert-danger py-2 px-3 mb-2"><strong>Chưa thể tạo công trình:</strong> {{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('projects-unified.store') }}" id="projectCreateForm">
            @csrf
            <div class="pu-create-layout">
                <main class="pu-panel pu-form-card">
                    <section class="pu-form-section">
                        <div class="pu-form-label-wrap">
                            <span class="pu-step-badge">1</span>
                            <div><div class="pu-form-section-title">Thông tin công trình</div><div class="pu-form-section-sub">Tên, loại, địa điểm và quy mô dự kiến.</div></div>
                        </div>
                        <div class="pu-fields">
                            <div class="pu-field full"><label>Tên công trình <span class="text-danger">*</span></label><input id="projectName" name="name" value="{{ old('name') }}" required placeholder="Ví dụ: Solar Farm 6MW – Sekong, Lào"></div>
                            <div class="pu-field"><label>Loại công trình <span class="text-danger">*</span></label><select id="projectType" name="project_type" required>@foreach($typeLabels as $key=>$label)<option value="{{ $key }}" @selected(old('project_type','industrial')===$key)>{{ $label }}</option>@endforeach</select></div>
                            @if(!$isSalesScope)
                            <div class="pu-field"><label>Công ty</label><select name="company_id"><option value="">Theo công ty đang chọn</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((int) old('company_id',$activeCompanyId)===(int)$company->id)>{{ $company->code ?: $company->name }}</option>@endforeach</select></div>
                            @else
                            <div class="pu-field"><label>Nguồn công trình</label><input value="Sales · {{ auth()->user()->name }}" disabled></div>
                            @endif
                            <div class="pu-field full"><label>Địa điểm <span class="text-danger">*</span></label><input name="address" value="{{ old('address') }}" required placeholder="Địa chỉ/khu vực triển khai công trình"></div>
                            <div class="pu-field"><label>Chủ đầu tư / người liên hệ</label><input name="contact_name" value="{{ old('contact_name') }}" placeholder="Tên người liên hệ"></div>
                            <div class="pu-field"><label>Số điện thoại</label><input name="contact_phone" value="{{ old('contact_phone') }}" placeholder="Số điện thoại liên hệ"></div>
                            <div class="pu-field"><label>Quy mô dự kiến (kWp)</label><input id="projectCapacity" type="number" step="0.01" min="0" name="system_kwp" value="{{ old('system_kwp') }}" placeholder="0"></div>
                            <div class="pu-field"><label>Loại hệ thống</label><input name="system_type" value="{{ old('system_type') }}" placeholder="On-grid, Hybrid, Solar Farm..."></div>
                        </div>
                    </section>

                    <section class="pu-form-section">
                        <div class="pu-form-label-wrap">
                            <span class="pu-step-badge">2</span>
                            <div><div class="pu-form-section-title">Nhân sự &amp; mốc dự kiến</div><div class="pu-form-section-sub">{{ $isSalesScope ? 'Sales tạo hồ sơ, phòng Kỹ thuật phân công sau khi tiếp nhận.' : 'Chọn kỹ sư chịu trách nhiệm chính.' }}</div></div>
                        </div>
                        <div class="pu-fields">
                            @if(!$isSalesScope)
                            <div class="pu-field full"><label>Kỹ sư phụ trách chính <span class="text-danger">*</span></label><select id="projectEngineer" name="lead_engineer_id" required><option value="">Chọn kỹ sư phụ trách</option>@foreach($engineers as $engineer)<option value="{{ $engineer->id }}" data-name="{{ $engineer->name }}" @selected((int)old('lead_engineer_id')===(int)$engineer->id)>{{ $engineer->name }}{{ $engineer->email ? ' · '.$engineer->email : '' }}</option>@endforeach</select></div>
                            @else
                            <div class="pu-field full"><label>Phụ trách kỹ thuật</label><input value="Chờ phòng Kỹ thuật tiếp nhận và phân công" disabled></div>
                            @endif
                            <div class="pu-field"><label>Hạn hoàn thành dự kiến</label><input id="projectDue" type="date" name="target_completion_at" value="{{ old('target_completion_at') }}"></div>
                            <div class="pu-field"><label>Mức ưu tiên</label><select id="projectPriority" name="priority">@foreach($priorityLabels as $key=>$label)<option value="{{ $key }}" @selected(old('priority','normal')===$key)>{{ $label }}</option>@endforeach</select></div>
                            <div class="pu-field full"><label>Ghi chú ban đầu</label><textarea name="note" placeholder="Mục tiêu, phạm vi, hồ sơ đang có, rủi ro hoặc yêu cầu quan trọng...">{{ old('note') }}</textarea></div>
                        </div>
                    </section>

                    @if($canEnterContract)
                        <section class="pu-form-section">
                            <div class="pu-form-label-wrap">
                                <span class="pu-step-badge">3</span>
                                <div><div class="pu-form-section-title">Thông tin quản trị</div><div class="pu-form-section-sub">Tài chính chỉ hiển thị cho vai trò được cấp quyền.</div></div>
                            </div>
                            <div class="pu-fields">
                                <div class="pu-field"><label>Giá trị hợp đồng dự kiến</label><input id="projectContract" type="number" min="0" step="1000" name="contract_amount" value="{{ old('contract_amount') }}" placeholder="0"></div>
                                <div class="pu-field"><label>Trạng thái khởi tạo</label><input value="Khởi tạo & phân công" disabled></div>
                            </div>
                        </section>
                    @endif
                </main>

                <aside class="pu-panel pu-summary-card">
                    <div class="pu-eyebrow">Xem trước hồ sơ</div>
                    <div class="pu-summary-name" id="previewName">Chưa nhập tên công trình</div>
                    <div class="pu-summary-list">
                        <div class="pu-summary-item"><small>Loại công trình</small><strong id="previewType">Công trình công nghiệp</strong></div>
                        <div class="pu-summary-item"><small>Kỹ sư phụ trách</small><strong id="previewEngineer">Chưa chọn</strong></div>
                        <div class="pu-summary-item"><small>Quy mô dự kiến</small><strong id="previewCapacity">Chưa nhập</strong></div>
                        <div class="pu-summary-item"><small>Hạn hoàn thành</small><strong id="previewDue">Chưa đặt</strong></div>
                        @if($canEnterContract)<div class="pu-summary-item"><small>Giá trị hợp đồng</small><strong id="previewContract">Chưa nhập</strong></div>@endif
                    </div>
                    <div class="pu-side-title mt-4">Luồng xử lý sau khi tạo</div>
                    <div class="pu-mini-flow">
                        @foreach($phases as $phase)
                            <div class="pu-mini-step {{ $loop->first ? 'active' : '' }}"><span>{{ $loop->iteration }}</span><strong>{{ $phase['label'] }}</strong></div>
                        @endforeach
                    </div>
                </aside>
            </div>

            <div class="pu-form-footer">
                <a class="pu-btn pu-btn-soft" href="{{ route('projects-unified.index') }}">Hủy</a>
                <button class="pu-btn pu-btn-primary" type="submit"><i class="bi bi-check2-circle"></i>Tạo công trình</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const name = document.getElementById('projectName');
    const type = document.getElementById('projectType');
    const engineer = document.getElementById('projectEngineer');
    const capacity = document.getElementById('projectCapacity');
    const due = document.getElementById('projectDue');
    const contract = document.getElementById('projectContract');
    const money = new Intl.NumberFormat('vi-VN');

    function sync() {
        document.getElementById('previewName').textContent = name.value.trim() || 'Chưa nhập tên công trình';
        document.getElementById('previewType').textContent = type.options[type.selectedIndex]?.text || 'Chưa chọn';
        document.getElementById('previewEngineer').textContent = engineer?.options[engineer.selectedIndex]?.dataset.name || 'Chưa chọn';
        document.getElementById('previewCapacity').textContent = capacity.value ? money.format(Number(capacity.value)) + ' kWp' : 'Chưa nhập';
        document.getElementById('previewDue').textContent = due.value ? due.value.split('-').reverse().join('/') : 'Chưa đặt';
        if (contract && document.getElementById('previewContract')) {
            document.getElementById('previewContract').textContent = contract.value ? money.format(Number(contract.value)) + ' đ' : 'Chưa nhập';
        }
    }
    [name,type,engineer,capacity,due,contract].filter(Boolean).forEach(el => el.addEventListener('input', sync));
    sync();
});
</script>
@endpush
@endsection
