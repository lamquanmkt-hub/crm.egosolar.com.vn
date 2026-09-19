@auth
    @php
        $egoMenuAccessService = app(\App\Services\RolePermission\PageAccessService::class);
        $egoRoleDeniedMenuPermissions = $egoMenuAccessService->deniedMenuPermissions(auth()->user());
        $egoWorkspaceDeniedMenuPermissions = $egoWorkspaceDeniedMenuPermissions
            ?? app(\App\Services\Workspace\WorkspaceContextService::class)->deniedMenuPermissions(auth()->user());
        $egoDeniedMenuPermissions = array_values(array_unique(array_merge(
            $egoRoleDeniedMenuPermissions,
            $egoWorkspaceDeniedMenuPermissions
        )));
    @endphp

    @if(count($egoDeniedMenuPermissions))
        <style id="ego-menu-permission-guard">
            @foreach($egoDeniedMenuPermissions as $egoDeniedMenuPermission)
                #sidebar [data-ego-menu-permission="{{ $egoDeniedMenuPermission }}"] {
                    display: none !important;
                }
            @endforeach
        </style>
    @endif
@endauth
