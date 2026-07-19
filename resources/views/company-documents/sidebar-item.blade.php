@php
    $u = auth()->user();
    $canCompanyDocs = false;
    $rolesCompanyDocs = ['admin','sales','sales_manager','marketing','marketing_manager','ky_thuat','accounting','assistant','tro_ly','management', 'warehouse', 'kho', 'warehouse', 'kho'];
    if ($u) {
        if (method_exists($u, 'hasAnyRole')) $canCompanyDocs = $u->hasAnyRole($rolesCompanyDocs);
        elseif (method_exists($u, 'hasRole')) foreach ($rolesCompanyDocs as $r) { if ($u->hasRole($r)) { $canCompanyDocs = true; break; } }
        elseif (isset($u->role)) $canCompanyDocs = in_array((string) $u->role, $rolesCompanyDocs, true);
    }
@endphp
@if($canCompanyDocs)
<a href="{{ route('company-documents.index') }}" class="nav-link {{ request()->routeIs('company-documents.*') ? 'active' : '' }}">
    <span class="nav-icon">📂</span>
    <span>Hồ sơ công ty</span>
</a>
@endif
