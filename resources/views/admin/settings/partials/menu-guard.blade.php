@auth
    @php
        $egoMenuAccessService = app(\App\Services\RolePermission\PageAccessService::class);
        $egoDeniedMenuPermissions = $egoMenuAccessService->deniedMenuPermissions(auth()->user());
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
