@php
    $departmentIcons = ['hcns' => 'people', 'ke_toan_kho' => 'box-seam', 'technical' => 'tools', 'sales' => 'graph-up-arrow', 'marketing' => 'megaphone'];
    $departmentScoreRoutes = ['technical' => 'ky-thuat.kpis.index', 'sales' => 'sales.kpi.index', 'marketing' => 'marketing.kpi-payroll.index'];
    $availableDepartments = collect(config('hr_payroll_departments', []))->filter(fn ($d) =>
        auth()->user()->hasAnyRole($d['roles']) && \Illuminate\Support\Facades\Route::has($d['route'])
    );
@endphp
<nav class="pk-departments" aria-label="Chọn bộ KPI phòng ban">
    @foreach($availableDepartments as $code => $department)
        @php
            $destination = $department['route'];
            $query = ($department['query'] ?? []) + ['month' => $month];
            $canOpenScores = $code !== 'technical' || auth()->user()->hasAnyRole(['admin', 'super_admin', 'director', 'ceo', 'management', 'technical_manager', 'technical_leader', 'truong_phong_ky_thuat']);
            if ($destination === 'hr.kpi.index') {
                $query['tab'] = $tab;
            } elseif ($tab === 'scores' && $canOpenScores && isset($departmentScoreRoutes[$code]) && \Illuminate\Support\Facades\Route::has($departmentScoreRoutes[$code])) {
                $destination = $departmentScoreRoutes[$code];
            }
            $selected = $template->code === $code;
        @endphp
        <a class="pk-department {{ $selected ? 'is-selected' : '' }}" href="{{ route($destination, $query) }}" @if($selected) aria-current="page" @endif>
            <span class="pk-department-icon"><i class="bi bi-{{ $departmentIcons[$code] ?? 'diagram-3' }}" aria-hidden="true"></i></span>
            <span><strong>{{ $department['label'] }}</strong><small>{{ $selected ? 'Đang xem bộ KPI' : ($tab === 'settings' || ! $canOpenScores ? 'Cài đặt bộ tiêu chí' : 'Xem KPI phòng ban') }}</small></span>
            <i class="bi bi-{{ $selected ? 'check-circle-fill' : 'arrow-right' }} pk-department-arrow" aria-hidden="true"></i>
        </a>
    @endforeach
</nav>
