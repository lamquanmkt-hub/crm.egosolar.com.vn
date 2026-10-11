@php
    $kpiDepartments = collect(config('hr_payroll_departments', []))->filter(fn ($department) =>
        auth()->user() && auth()->user()->hasAnyRole($department['roles'])
        && \Illuminate\Support\Facades\Route::has($department['route'])
    );
    $kpiSelectedDepartment = match (true) {
        request()->routeIs('ky-thuat.kpis.config*') => 'technical',
        request()->routeIs('sales.kpi.settings*') => 'sales',
        request()->routeIs('marketing.kpi-payroll.settings*') => 'marketing',
        default => $template->code ?? (string) request()->query('template', 'hcns'),
    };
@endphp
<div class="at-panel pr-body" style="margin-bottom:12px;padding:12px 16px;border:1px solid #d2e2e8;border-radius:10px;background:#fff;display:flex;align-items:center;flex-wrap:wrap;gap:12px">
    <label for="payroll-kpi-department" style="font-size:12px;font-weight:700;color:#304b5e">Phòng ban</label>
    <select id="payroll-kpi-department" aria-label="Phòng ban KPI" onchange="window.location.assign(this.value)" style="border:1px solid #d2e2e8;border-radius:8px;padding:7px 12px;background:#fff;color:#1d3a50;font-size:13px">
        @foreach($kpiDepartments as $code => $department)
            @php
                $departmentQuery = $department['query'] ?? [];
                if ($department['route'] === 'hr.kpi.index') {
                    $departmentQuery += ['tab' => $tab ?? 'settings'] + request()->only('month');
                }
            @endphp
            <option value="{{ route($department['route'], $departmentQuery) }}" @selected($kpiSelectedDepartment === $code)>{{ $department['label'] }}</option>
        @endforeach
    </select>
    @if(\App\Services\Hr\Payroll\PayrollAccess::canManage(auth()->user()))
        <a href="{{ route('hr.payroll.index') }}" style="margin-left:auto;font-size:12px;color:#087e92">Bảng lương tháng</a>
    @endif
</div>
