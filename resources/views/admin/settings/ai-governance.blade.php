@extends('layouts.app')

@section('title', 'Quản trị & phân quyền AI')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-ai-settings.css') }}?v={{ file_exists(public_path('css/ego-ai-settings.css')) ? filemtime(public_path('css/ego-ai-settings.css')) : '2.0.0' }}">
<link rel="stylesheet" href="{{ asset('css/ego-ai-governance.css') }}?v={{ file_exists(public_path('css/ego-ai-governance.css')) ? filemtime(public_path('css/ego-ai-governance.css')) : '2.0.0' }}">
@endpush

@section('content')
@php
    $permissionLabels = [
        'ai.use' => ['Sử dụng EGO AI', 'Được mở trang AI và gửi câu hỏi.', 'bi-stars'],
        'ai.search.orders' => ['Đơn hàng & công nợ', 'Tra cứu đơn hàng trong đúng phạm vi dữ liệu.', 'bi-receipt'],
        'ai.search.customers' => ['Khách hàng', 'Tra cứu hồ sơ khách thuộc phạm vi được giao.', 'bi-people'],
        'ai.search.inventory' => ['Sản phẩm & tồn kho', 'Tra cứu sản phẩm, tồn kho và trạng thái hàng.', 'bi-box-seam'],
        'ai.search.tasks' => ['Công việc', 'Tra cứu việc cá nhân hoặc phòng ban theo role.', 'bi-list-check'],
        'ai.search.sites' => ['Công trình & bảo hành', 'Tra cứu công trình, tiến độ và bảo hành.', 'bi-building-gear'],
        'ai.search.payment_requests' => ['Đề nghị thanh toán', 'Tra cứu phiếu thuộc phạm vi được phép.', 'bi-cash-stack'],
        'ai.search.attendance' => ['Chấm công', 'Tra cứu cá nhân, phòng ban hoặc toàn công ty theo role.', 'bi-person-check'],
        'ai.search.marketing' => ['Marketing & lead', 'Tra cứu lead và hoạt động Marketing.', 'bi-megaphone'],
        'ai.search.hr' => ['Nhân sự & tuyển dụng', 'Tra cứu dữ liệu HR theo quyền.', 'bi-person-workspace'],
        'ai.draft.orders' => ['Soạn nháp đơn hàng', 'Cho phép AI chuẩn bị bản nháp, không tự duyệt.', 'bi-file-earmark-plus'],
        'ai.draft.customers' => ['Soạn nháp khách hàng', 'Cho phép AI chuẩn bị dữ liệu khách hàng nháp.', 'bi-person-plus'],
        'ai.draft.tasks' => ['Soạn nháp công việc', 'Cho phép AI chuẩn bị công việc trước khi xác nhận.', 'bi-clipboard-plus'],
        'ai.draft.payment_requests' => ['Soạn nháp ĐNTT', 'Cho phép AI chuẩn bị phiếu nháp, không tự duyệt.', 'bi-file-earmark-text'],
        'ai.providers.manage' => ['Quản lý API AI', 'Thêm, sửa, kiểm tra và chọn nhà cung cấp AI.', 'bi-cpu'],
        'ai.audit.view' => ['Xem nhật ký AI', 'Xem lịch sử công cụ, phạm vi và truy cập bị từ chối.', 'bi-shield-check'],
    ];
    $roleNames = [
        'admin' => 'Quản trị viên', 'management' => 'Ban Giám đốc', 'accounting' => 'Kế toán',
        'warehouse' => 'Kho', 'sales_manager' => 'Quản lý Sales', 'sales' => 'Nhân viên Sales',
        'technical_manager' => 'Quản lý Kỹ thuật', 'ky_thuat' => 'Kỹ thuật',
        'marketing_manager' => 'Quản lý Marketing', 'marketing' => 'Nhân viên Marketing', 'hr' => 'Nhân sự',
    ];
    $scopeMatrix = config('ego_ai.role_scopes', []);
    $scopeLabels = ['company' => 'Toàn công ty', 'department' => 'Phòng ban', 'self' => 'Cá nhân', 'none' => 'Không truy cập'];
@endphp
<div class="ego-ai-governance">
    <div class="ego-ai-governance__shell">
        @if(session('success'))
            <div class="ego-ai-settings-alert"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>
        @endif
        @if($errors->any())
            <div class="ego-ai-settings-alert ego-ai-settings-alert--danger"><i class="bi bi-exclamation-triangle-fill"></i><div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div></div>
        @endif

        <header class="ego-ai-governance-hero">
            <div class="ego-ai-governance-hero__mark"><i class="bi bi-shield-lock-fill"></i></div>
            <div class="ego-ai-governance-hero__copy">
                <span>AI GOVERNANCE CENTER</span>
                <h1>Phân quyền & kiểm soát EGO AI</h1>
                <p>Laravel chặn quyền trước khi truy vấn. CRM không cho xem thì AI tuyệt đối không được biết.</p>
            </div>
            <div class="ego-ai-governance-hero__actions">
                <a href="{{ route('ai.index') }}" class="ego-ai-settings-btn ego-ai-settings-btn--primary"><i class="bi bi-stars"></i>Mở EGO AI</a>
                <a href="{{ route('admin.settings.ai.index') }}" class="ego-ai-settings-btn"><i class="bi bi-cpu"></i>API & model</a>
            </div>
        </header>

        <nav class="ego-ai-settings-nav">
            <a href="{{ route('admin.settings.index') }}"><i class="bi bi-grid-1x2"></i>Tổng quan</a>
            <a href="{{ route('admin.settings.ai.index') }}"><i class="bi bi-cpu"></i>API AI</a>
            <a class="active" href="{{ route('admin.settings.ai.governance.index') }}"><i class="bi bi-shield-lock"></i>Quản trị AI</a>
            <a href="{{ route('admin.settings.roles') }}"><i class="bi bi-people"></i>Phân quyền CRM</a>
        </nav>

        <section class="ego-ai-governance-kpis">
            <article><i class="bi bi-people-fill"></i><div><span>Vai trò được kiểm soát</span><strong>{{ $roles->count() }}</strong><small>Mỗi role một phạm vi riêng</small></div></article>
            <article><i class="bi bi-key-fill"></i><div><span>Quyền AI</span><strong>{{ $aiPermissions->count() }}</strong><small>Chặn trực tiếp ở backend</small></div></article>
            <article><i class="bi bi-send-check-fill"></i><div><span>Yêu cầu 30 ngày</span><strong>{{ number_format((int) ($usageSummary->requests ?? 0), 0, ',', '.') }}</strong><small>{{ number_format((int) ($usageSummary->total_tokens ?? 0), 0, ',', '.') }} token</small></div></article>
            <article><i class="bi bi-shield-exclamation"></i><div><span>Lỗi API 30 ngày</span><strong>{{ number_format((int) ($usageSummary->errors ?? 0), 0, ',', '.') }}</strong><small>Độ trễ TB {{ number_format((float) ($usageSummary->avg_latency ?? 0), 0, ',', '.') }} ms</small></div></article>
        </section>

        <section class="ego-ai-policy-banner">
            <div><i class="bi bi-lock-fill"></i></div>
            <p><strong>Nguyên tắc bất biến:</strong> quyền AI không thay thế quyền CRM. Người dùng phải đồng thời có quyền AI, quyền trang/module CRM và phạm vi dữ liệu hợp lệ.</p>
            <form method="POST" action="{{ route('admin.settings.ai.governance.defaults') }}" onsubmit="return confirm('Áp dụng lại ma trận AI mặc định cho toàn bộ role?')">
                @csrf
                <button type="submit"><i class="bi bi-arrow-repeat"></i>Áp dụng ma trận chuẩn</button>
            </form>
        </section>

        <div class="ego-ai-governance-layout">
            <main>
                <div class="ego-ai-section-title"><div><span>ROLE × AI PERMISSION</span><h2>Ma trận quyền theo vai trò</h2><p>Bật quyền nào thì role mới được sử dụng đúng chức năng đó. Admin luôn có toàn quyền quản trị AI.</p></div></div>

                <div class="ego-ai-role-grid">
                    @foreach($roles as $role)
                        @php
                            $selected = $role->permissions->pluck('name')->all();
                            $roleScope = $scopeMatrix[$role->name] ?? ($scopeMatrix['default'] ?? []);
                            $isAdminRole = $role->name === 'admin';
                        @endphp
                        <article class="ego-ai-role-card {{ $isAdminRole ? 'is-admin' : '' }}">
                            <header>
                                <div class="ego-ai-role-card__avatar"><i class="bi {{ $isAdminRole ? 'bi-shield-fill-check' : 'bi-person-badge' }}"></i></div>
                                <div><span>{{ strtoupper($role->name) }}</span><h3>{{ $roleNames[$role->name] ?? ($role->display_name ?: $role->name) }}</h3></div>
                                <div class="ego-ai-role-card__count">{{ count($selected) }} quyền</div>
                            </header>

                            <div class="ego-ai-scope-map">
                                @foreach($moduleDefinitions as $moduleKey => $module)
                                    @php
                                        $scope = $isAdminRole ? 'company' : ($roleScope[$moduleKey] ?? ($roleScope['*'] ?? 'none'));
                                    @endphp
                                    <div class="scope-{{ $scope }}" title="{{ $module['label'] ?? $moduleKey }}: {{ $scopeLabels[$scope] ?? $scope }}">
                                        <i class="bi {{ $module['icon'] ?? 'bi-circle' }}"></i>
                                        <span>{{ $module['label'] ?? $moduleKey }}</span>
                                        <b>{{ $scopeLabels[$scope] ?? $scope }}</b>
                                    </div>
                                @endforeach
                            </div>

                            <form method="POST" action="{{ route('admin.settings.ai.governance.roles.update', $role) }}">
                                @csrf @method('PUT')
                                <div class="ego-ai-permission-list">
                                    @foreach($aiPermissions as $permission)
                                        @php $meta = $permissionLabels[$permission->name] ?? [$permission->name, $permission->name, 'bi-check2-circle']; @endphp
                                        <label class="{{ $isAdminRole ? 'is-locked' : '' }}">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked($isAdminRole || in_array($permission->name, $selected, true)) @disabled($isAdminRole)>
                                            <span class="ego-ai-permission-toggle"></span>
                                            <i class="bi {{ $meta[2] }}"></i>
                                            <span class="ego-ai-permission-copy"><strong>{{ $meta[0] }}</strong><small>{{ $meta[1] }}</small></span>
                                        </label>
                                    @endforeach
                                </div>
                                <footer>
                                    @if($isAdminRole)
                                        <span><i class="bi bi-lock-fill"></i>Admin được khóa toàn quyền</span>
                                    @else
                                        <span><i class="bi bi-lightning-charge"></i>Có hiệu lực ngay sau khi lưu</span>
                                        <button type="submit"><i class="bi bi-floppy"></i>Lưu quyền role</button>
                                    @endif
                                </footer>
                            </form>
                        </article>
                    @endforeach
                </div>
            </main>

            <aside class="ego-ai-governance-side">
                <section><header><i class="bi bi-diagram-3"></i><strong>5 lớp bảo vệ</strong></header><ol><li>Đăng nhập hợp lệ</li><li>Quyền <code>ai.use</code></li><li>Quyền module AI</li><li>Quyền trang CRM</li><li>Phạm vi cá nhân/phòng ban/công ty</li></ol></section>
                <section class="is-warning"><header><i class="bi bi-exclamation-octagon"></i><strong>Luôn bị khóa</strong></header><p>AI không tự duyệt, xóa, xuất kho, ghi nhận thanh toán, chạy SQL hoặc thay đổi role.</p></section>
                <section><header><i class="bi bi-person-lock"></i><strong>Người có nhiều role</strong></header><p>Chỉ được hưởng tổng quyền thực sự đã cấp trong CRM; mọi lần truy vấn đều lưu scope và module.</p></section>
            </aside>
        </div>

        <section class="ego-ai-audit-section">
            <div class="ego-ai-section-title"><div><span>SECURITY AUDIT TRAIL</span><h2>Nhật ký truy cập công cụ AI</h2><p>Ghi nhận ai hỏi, module nào được gọi, phạm vi nào được dùng và yêu cầu nào bị từ chối.</p></div></div>
            <div class="ego-ai-audit-table-wrap">
                <table class="ego-ai-audit-table">
                    <thead><tr><th>Thời gian</th><th>Người dùng</th><th>Module</th><th>Phạm vi</th><th>Kết quả</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    @forelse($toolLogs as $log)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                            <td><strong>{{ $log->user_name ?? 'Không xác định' }}</strong><small>{{ $log->user_email ?? '' }}</small></td>
                            <td><span class="ego-ai-audit-module"><i class="bi {{ $moduleDefinitions[$log->module]['icon'] ?? 'bi-database' }}"></i>{{ $moduleDefinitions[$log->module]['label'] ?? $log->module }}</span></td>
                            <td><span class="ego-ai-audit-scope scope-{{ $log->scope ?: 'none' }}">{{ $scopeLabels[$log->scope] ?? ($log->scope ?: 'Không có') }}</span></td>
                            <td>{{ number_format((int) $log->result_count, 0, ',', '.') }} bản ghi @if($log->period_label)<small>{{ $log->period_label }}</small>@endif</td>
                            <td><span class="ego-ai-audit-status status-{{ $log->status }}"><i class="bi {{ $log->status === 'denied' ? 'bi-shield-x' : 'bi-check-circle' }}"></i>{{ $log->status === 'denied' ? 'Bị chặn' : 'Thành công' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ego-ai-audit-empty"><i class="bi bi-journal-check"></i>Chưa có nhật ký công cụ AI.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
