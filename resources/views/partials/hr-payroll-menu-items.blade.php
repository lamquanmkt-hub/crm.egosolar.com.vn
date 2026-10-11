@foreach($payrollMenuItems as $payrollMenuItem)
    @php
        $payrollRoles = $payrollMenuItem['roles'] ?? [];
        $payrollVisible = !$payrollRoles || (auth()->user() && auth()->user()->hasAnyRole($payrollRoles));
        $payrollChildren = $payrollMenuItem['children'] ?? [];
        $payrollRoute = $payrollMenuItem['route'] ?? null;
        $payrollActive = false;
        foreach ($payrollMenuItem['patterns'] ?? [] as $payrollPattern) {
            $payrollActive = $payrollActive || request()->routeIs($payrollPattern);
        }
        foreach ($payrollMenuItem['query'] ?? [] as $payrollKey => $payrollValue) {
            $payrollActive = $payrollActive && (string) request()->query($payrollKey) === (string) $payrollValue;
        }
    @endphp
    @if($payrollVisible)
        @if($payrollChildren)
            <li class="ego-hr-v4-group">
                <details class="ego-hr-v4-details" {{ $egoHrKpiSettingsOpen ? 'open' : '' }}>
                    <summary class="ego-sublink ego-hr-v4-summary {{ $egoHrKpiSettingsOpen ? 'active' : '' }}">
                        <span>{{ $payrollMenuItem['label'] }}</span>
                        <i class="bi bi-chevron-down ego-hr-v4-chevron"></i>
                    </summary>
                    <ul class="ego-hr-v4-children">
                        @include('partials.hr-payroll-menu-items', ['payrollMenuItems' => $payrollChildren])
                    </ul>
                </details>
            </li>
        @elseif($payrollRoute && \Illuminate\Support\Facades\Route::has($payrollRoute))
            <li><a href="{{ route($payrollRoute, $payrollMenuItem['query'] ?? []) }}" class="ego-sublink {{ $payrollActive ? 'active' : '' }}" data-ego-type="nav">{{ $payrollMenuItem['label'] }}</a></li>
        @endif
    @endif
@endforeach
