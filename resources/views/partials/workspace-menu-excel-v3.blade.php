@php
    $egoExcelUser = auth()->user();
    $egoExcelContext = app(\App\Services\Workspace\WorkspaceContextService::class);
    $egoExcelWorkspace = $egoExcelUser ? $egoExcelContext->current($egoExcelUser) : 'general';
    $egoExcelSupported = in_array($egoExcelWorkspace, (array) config('ego_menu_v4.supported_workspaces', []), true);

    $egoExcelNormalize = static function (string $value): string {
        return \Illuminate\Support\Str::of($value)
            ->ascii()
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->value();
    };

    $egoExcelIsTechnicalHead = false;
    if ($egoExcelUser && $egoExcelWorkspace === 'technical') {
        $egoExcelUser->loadMissing(['roles', 'position']);
        $roleNames = $egoExcelUser->roles->pluck('name')->map(fn ($name) => (string) $name)->all();
        $headRoles = (array) config('ego_menu_v4.technical_head_roles', []);
        $positionName = $egoExcelNormalize((string) ($egoExcelUser->position->name ?? ''));
        $headPositions = array_map($egoExcelNormalize, (array) config('ego_menu_v4.technical_head_positions', []));
        $positionMatched = $positionName !== '' && collect($headPositions)
            ->contains(fn (string $needle): bool => $needle !== '' && str_contains($positionName, $needle));
        $egoExcelIsTechnicalHead = array_intersect($roleNames, $headRoles) !== [] || $positionMatched;
    }

    $egoExcelMenuKey = match ($egoExcelWorkspace) {
        'technical' => $egoExcelIsTechnicalHead ? 'technical_head' : 'technical_staff',
        'admin' => 'admin',
        'management' => 'management',
        'accounting' => 'accounting',
        'hr' => 'hr',
        'warehouse' => 'warehouse',
        'sales' => 'sales',
        'marketing' => 'marketing',
        default => 'general',
    };

    $egoExcelItems = array_merge(
        (array) config('ego_menu_v4.menus.'.$egoExcelMenuKey, []),
        (array) config('ego_menu_v4.common_sections', [])
    );

    /* EGO_PAYMENT_ADVANCE_ALL_DEPARTMENT_MENUS_V3_START
     * Module dung chung: hien trong menu cua moi Workspace/phong ban.
     * Chi them loi vao; route/controller van quyet dinh quyen nghiep vu va du lieu.
     */
    if (\Illuminate\Support\Facades\Route::has('payment_advances.index')) {
        $hasPaymentAdvance = collect($egoExcelItems)->contains(function ($item): bool {
            if (!is_array($item)) return false;
            if (($item['route'] ?? null) === 'payment_advances.index') return true;
            foreach ((array) ($item['children'] ?? []) as $child) {
                if (is_array($child) && ($child['route'] ?? null) === 'payment_advances.index') return true;
                foreach ((array) (($child['children'] ?? [])) as $grandchild) {
                    if (is_array($grandchild) && ($grandchild['route'] ?? null) === 'payment_advances.index') return true;
                }
            }
            return false;
        });

        if (!$hasPaymentAdvance) {
            $egoExcelItems[] = [
                'label' => 'Tạm ứng & Hoàn ứng',
                'icon' => 'bi-cash-coin',
                'route' => 'payment_advances.index',
                'fallback' => '/payment-requests/tam-ung-hoan-ung',
                'patterns' => ['payment_advances.*'],
                'description' => 'Đề nghị tạm ứng và hoàn ứng',
            ];
        }
    }
    /* EGO_PAYMENT_ADVANCE_ALL_DEPARTMENT_MENUS_V3_END */

    $egoExcelResolveUrl = static function (array $item): string {
        $routeName = $item['route'] ?? null;
        $query = (array) ($item['query'] ?? []);

        if (is_string($routeName) && $routeName !== '' && \Illuminate\Support\Facades\Route::has($routeName)) {
            return route($routeName, $query);
        }

        return url((string) ($item['fallback'] ?? '/workspace'));
    };

    $egoExcelIsActive = static function (array $item): bool {
        foreach ((array) ($item['patterns'] ?? []) as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }
        foreach ((array) ($item['children'] ?? []) as $child) {
            foreach ((array) ($child['patterns'] ?? []) as $pattern) {
                if (request()->routeIs($pattern)) {
                    return true;
                }
            }
            foreach ((array) ($child['children'] ?? []) as $grandchild) {
                foreach ((array) ($grandchild['patterns'] ?? []) as $pattern) {
                    if (request()->routeIs($pattern)) {
                        return true;
                    }
                }
            }
        }
        return false;
    };

    $egoExcelWorkspaceLabels = (array) config('ego_menu_v4.workspace_labels', []);
    $egoExcelWorkspaceLabelKey = $egoExcelWorkspace === 'technical'
        ? ($egoExcelIsTechnicalHead ? 'technical_head' : 'technical_staff')
        : $egoExcelWorkspace;
@endphp

@if($egoExcelSupported)
    <link rel="stylesheet" href="{{ asset('css/ego-workspace-menu-excel-v3.css') }}?v={{ @filemtime(public_path('css/ego-workspace-menu-excel-v3.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('css/ego-workspace-menu-v4.css') }}?v={{ @filemtime(public_path('css/ego-workspace-menu-v4.css')) ?: time() }}">

    <li class="ego-excel-item ego-excel-context ego-v4-workspace-card"
    data-ego-excel-menu="true">

    <div class="ego-v4-workspace-card__label">
        WORKSPACE ĐANG SỬ DỤNG
    </div>

    <div class="ego-v4-workspace-card__current">
        <span class="ego-v4-workspace-card__icon">
            <i class="bi bi-building"></i>
        </span>

        <strong>
            {{ $egoExcelWorkspaceLabels[$egoExcelWorkspaceLabelKey] ?? strtoupper($egoExcelWorkspace) }}
        </strong>
    </div>

    <div class="ego-v4-workspace-card__actions">
        <a href="{{ route('workspace.index') }}">
            <i class="bi bi-grid-3x3-gap-fill"></i>
            <span>Ứng dụng</span>
        </a>

        <a href="{{ route('workspace.index', ['focus' => 'departments']) }}">
            <i class="bi bi-arrow-left-right"></i>
            <span>Đổi phòng</span>
        </a>
    </div>
</li>

    @php
        $renderExcelMenu = function (array $items, string $path = 'root', int $depth = 0) use (&$renderExcelMenu, $egoExcelResolveUrl, $egoExcelIsActive) {
            $html = '';
            foreach ($items as $index => $item) {
                if (($item['type'] ?? 'item') === 'section') {
                    $sectionLabel = e((string) ($item['label'] ?? ''));
                    $html .= '<li class="ego-excel-section ego-excel-item" data-ego-excel-menu="true"><span>'.$sectionLabel.'</span></li>';
                    continue;
                }

                $children = (array) ($item['children'] ?? []);
                $hasChildren = $children !== [];
                $active = $egoExcelIsActive($item);
                $id = 'egoExcel'.preg_replace('/[^A-Za-z0-9]/', '', ucfirst($path)).$index;
                $label = e((string) ($item['label'] ?? ''));
                $icon = e((string) ($item['icon'] ?? 'bi-circle'));
                $description = trim((string) ($item['description'] ?? ''));

                if ($depth === 0) {
                    $html .= '<li class="ego-item ego-excel-item '.($hasChildren ? 'ego-item--has-sub' : '').'" data-ego-excel-menu="true" data-title="'.$label.'">';
                    if ($hasChildren) {
                        $html .= '<a href="#'.$id.'" class="ego-link '.($active ? 'active' : '').'" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="'.($active ? 'true' : 'false').'">';
                        $html .= '<span class="ego-ic"><i class="bi '.$icon.'"></i></span><span class="ego-txt">'.$label.'</span><span class="ego-caret"><i class="bi bi-chevron-down"></i></span></a>';
                        $html .= '<ul id="'.$id.'" class="ego-sub collapse '.($active ? 'show' : '').'" data-ego-submenu>';
                        $html .= $renderExcelMenu($children, $path.$index, 1);
                        $html .= '</ul>';
                    } else {
                        $url = e($egoExcelResolveUrl($item));
                        $html .= '<a href="'.$url.'" class="ego-link '.($active ? 'active' : '').'" data-ego-type="nav">';
                        $html .= '<span class="ego-ic"><i class="bi '.$icon.'"></i></span><span class="ego-txt">'.$label.'</span></a>';
                    }
                    $html .= '</li>';
                    continue;
                }

                $html .= '<li class="ego-excel-subitem depth-'.$depth.'">';
                if ($hasChildren) {
                    $html .= '<a href="#'.$id.'" class="ego-sublink ego-excel-subtoggle '.($active ? 'active' : '').'" data-bs-toggle="collapse" aria-expanded="'.($active ? 'true' : 'false').'">';
                    $html .= '<i class="bi '.$icon.'"></i><span>'.$label.'</span><i class="bi bi-chevron-down ego-excel-mini-caret"></i></a>';
                    if ($description !== '') {
                        $html .= '<small class="ego-excel-description">'.e($description).'</small>';
                    }
                    $html .= '<ul id="'.$id.'" class="ego-excel-nested collapse '.($active ? 'show' : '').'">';
                    $html .= $renderExcelMenu($children, $path.$index, $depth + 1);
                    $html .= '</ul>';
                } else {
                    $url = e($egoExcelResolveUrl($item));
                    $html .= '<a href="'.$url.'" class="ego-sublink '.($active ? 'active' : '').'" data-ego-type="nav"><i class="bi '.$icon.'"></i><span>'.$label.'</span></a>';
                    if ($description !== '') {
                        $html .= '<small class="ego-excel-description">'.e($description).'</small>';
                    }
                }
                $html .= '</li>';
            }
            return $html;
        };
    @endphp

    {!! $renderExcelMenu($egoExcelItems) !!}
@endif
