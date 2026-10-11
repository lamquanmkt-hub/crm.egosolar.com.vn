<nav class="pr-breadcrumb" aria-label="Đường dẫn">
    <a href="{{ route('dashboard') }}">Trang chủ</a>
    <i class="bi bi-chevron-right" aria-hidden="true"></i>
    <a href="{{ route('hr.payroll.index') }}">Bảng lương</a>
    <i class="bi bi-chevron-right" aria-hidden="true"></i>
    <span aria-current="page">{{ $pageTitle }}</span>
</nav>
