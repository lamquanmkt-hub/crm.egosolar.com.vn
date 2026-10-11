@php
    $workspaceSections = [
        'nhan-su/tuyen-dung*' => ['Tuyển dụng', 'hr.recruitment.index'],
        'nhan-su/hc-van-hanh' => ['Hành chính & vận hành', 'hr.operations.index'],
        'nhan-su/employees*' => ['Nhân viên', 'hr.employees.index'],
        'nhan-su/ho-so-nhan-su*' => ['Hồ sơ nhân sự', 'hr.records.index'],
        'nhan-su/departments*' => ['Phòng ban', 'hr.departments.index'],
        'nhan-su/positions*' => ['Chức vụ', 'hr.positions.index'],
        'nhan-su/quy-trinh-ung-vien*' => ['Quy trình ứng viên', 'hr.candidate-processes.index'],
        'nhan-su/quy-trinh-phan-bo-vpp*' => ['Văn phòng phẩm', 'hr.office-supply-process.index'],
        'nhan-su/quy-trinh-giao-nhan-ho-so*' => ['Giao nhận hồ sơ', 'hr.document-handovers.index'],
        'nhan-su/su-co-van-phong*' => ['Sự cố văn phòng', 'hr.incidents.index'],
        'nhan-su/chi-phi-vp*' => ['Chi phí văn phòng', 'hr.office-expenses.index'],
        'nhan-su/cham-cong*' => ['Chấm công', 'hr.attendance.index'],
        'nhan-su/leave-requests*' => ['Nghỉ phép', 'hr.leave.index'],
        'nhan-su/tang-ca*' => ['Tăng ca', 'hr.overtime.index'],
        'finance/assets*' => ['Tài sản', 'finance.assets.index'],
        'finance/salary*' => ['Bảng lương', 'finance.salary'],
        'company-documents*' => ['Hồ sơ công ty', 'company-documents.index'],
        'chat/tasks*' => ['Công việc', 'tasks.index'],
        'de-xuat*' => ['Đề xuất', 'proposals.index'],
        'payment-requests/tam-ung-hoan-ung*' => ['Tạm ứng & hoàn ứng', null],
        'payment-requests*' => ['Đề nghị thanh toán', 'payment-requests.index'],
    ];
    $workspaceSection = ['Tổng quan', null];
    foreach ($workspaceSections as $pattern => $section) {
        if (request()->is($pattern)) { $workspaceSection = $section; break; }
    }
@endphp
<nav class="hcns-breadcrumb" aria-label="Đường dẫn trang">
    <a href="{{ url('/') }}">Trang chủ</a>
    <i class="bi bi-chevron-right" aria-hidden="true"></i>
    @if(request()->is('nhan-su*'))
        <a href="{{ route('hr.dashboard') }}">Nhân sự</a>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
    @endif
    <span aria-current="page">{{ $workspaceSection[0] }}</span>
</nav>
