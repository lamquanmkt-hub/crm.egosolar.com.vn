<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Contracts\Services\PageAccessServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Workspace\WorkspaceBadgeService;
use App\Services\Workspace\WorkspaceContextService;
use App\Services\Workspace\WorkspaceProfileService;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

final class WorkspaceController extends Controller
{
    public function __construct(
        private readonly PageAccessServiceInterface $pageAccess,
        private readonly WorkspaceBadgeService $badges,
        private readonly WorkspaceProfileService $profiles,
        private readonly WorkspaceContextService $context,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'permissions', 'department', 'position', 'avatar']);

        $canManageWorkspace = $this->profiles->canManage($user);
        $availableProfiles = $this->profiles->profiles();
        $primaryProfileKey = $this->context->current($user);
        $profileKeys = [$primaryProfileKey];
        $activeProfile = $availableProfiles[$primaryProfileKey] ?? $availableProfiles['general'];
        $workspaceOptions = $this->context->options($user);
        $canSwitchWorkspace = count($workspaceOptions) > 1;
        $isCustomWorkspace = $primaryProfileKey !== $this->context->default($user);
        $profileAllowedIds = $this->profiles->allowedAppIds($profileKeys);
        $workspaceAllowedIds = $this->context->allowedAppIds($user);
        $allowedIds = array_values(array_intersect($profileAllowedIds, $workspaceAllowedIds));

        /* EGO_SHARED_COMPANY_PROPOSAL_APPS_V1
         * Hồ sơ công ty và Đề xuất là ứng dụng dùng chung cho mọi phòng ban.
         * Chỉ mở hiển thị launcher/menu; quyền dữ liệu và thao tác trong module giữ nguyên.
         */
        $sharedAppRoutes = ['de-xuat.index', 'company-documents.index'];
        $sharedAppIds = collect(config('ego_workspace.apps', []))
            ->filter(static fn (array $app): bool => in_array(
                (string) ($app['route'] ?? ''),
                $sharedAppRoutes,
                true
            ))
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->filter()
            ->values()
            ->all();
        $allowedIds = array_values(array_unique(array_merge($allowedIds, $sharedAppIds)));
        $featuredIds = array_values(array_intersect(
            $this->profiles->featuredAppIds($profileKeys),
            $allowedIds
        ));
        $badgeCounts = $this->badges->counts($user);

        $apps = collect(config('ego_workspace.apps', []))
            ->filter(fn (array $app): bool => in_array((string) ($app['id'] ?? ''), $allowedIds, true))
            ->filter(fn (array $app): bool => $this->isSharedCompanyApp($app) || $this->canOpen($user, $app))
            ->map(function (array $app) use ($badgeCounts, $primaryProfileKey): array {
                /* HR cần đi thẳng vào bảng công toàn công ty, không phải trang cá nhân. */
                if ($primaryProfileKey === 'hr' && (string) ($app['id'] ?? '') === 'attendance') {
                    $app['name'] = 'Chấm công & nghỉ phép';
                    $app['description'] = 'Bảng công nhân sự, nghỉ phép, tăng ca và các đơn chờ duyệt';
                    $app['route'] = 'hr.attendance.index';
                    $app['fallback'] = '/nhan-su/cham-cong';
                    $app['page_permission'] = 'page.hr';
                    $app['keywords'] = ['chấm công', 'nghỉ phép', 'tăng ca', 'duyệt đơn nhân sự'];
                }

                $routeName = (string) ($app['route'] ?? '');
                $fallback = (string) ($app['fallback'] ?? '/');
                $app['url'] = $routeName !== '' && Route::has($routeName) ? route($routeName) : url($fallback);
                $app['badge'] = max(0, (int) ($badgeCounts[$app['badge_key'] ?? ''] ?? 0));
                $app['keywords_text'] = mb_strtolower(implode(' ', array_merge(
                    [(string) ($app['name'] ?? ''), (string) ($app['description'] ?? '')],
                    $app['keywords'] ?? []
                )));

                return $app;
            })
            ->values();


        /* EGO_PAYMENT_REQUEST_PUBLIC_WORKSPACE_V1
         * Đề nghị thanh toán là ứng dụng dùng chung: luôn hiện trên Workspace.
         * Không thay đổi quyền dữ liệu hoặc nghiệp vụ của module ĐNTT.
         */
        if (Route::has('payment_requests.index')) {
            $paymentRequestApp = collect(config('ego_workspace.apps', []))
                ->first(function (array $candidate): bool {
                    $route = (string) ($candidate['route'] ?? '');
                    $id = (string) ($candidate['id'] ?? '');
                    $fallback = (string) ($candidate['fallback'] ?? '');

                    return $route === 'payment_requests.index'
                        || in_array($id, ['payment_requests', 'payment-request', 'payment_request'], true)
                        || str_contains($fallback, 'payment-requests');
                });

            if (! is_array($paymentRequestApp)) {
                $paymentRequestApp = [
                    'id' => 'payment_requests',
                    'name' => 'Đề nghị thanh toán',
                    'description' => 'Tạo và theo dõi đề nghị thanh toán',
                    'route' => 'payment_requests.index',
                    'fallback' => '/payment-requests',
                    'icon' => 'bi-receipt',
                    'tone' => 'cyan',
                    'category' => 'finance',
                    'badge_key' => 'pending_payment_requests',
                    'keywords' => ['đề nghị thanh toán', 'thanh toán', 'dntt'],
                ];
            }

            $paymentRequestId = (string) ($paymentRequestApp['id'] ?? 'payment_requests');

            if (! $apps->contains(fn (array $app): bool =>
                (string) ($app['id'] ?? '') === $paymentRequestId
                || (string) ($app['route'] ?? '') === 'payment_requests.index'
            )) {
                $paymentRequestApp['url'] = route('payment_requests.index');
                $paymentRequestApp['badge'] = max(0, (int) (
                    $badgeCounts[$paymentRequestApp['badge_key'] ?? 'pending_payment_requests'] ?? 0
                ));
                $paymentRequestApp['keywords_text'] = mb_strtolower(implode(' ', array_merge(
                    [(string) ($paymentRequestApp['name'] ?? ''), (string) ($paymentRequestApp['description'] ?? '')],
                    $paymentRequestApp['keywords'] ?? []
                )));

                $apps->push($paymentRequestApp);
            }
        }
        /* EGO_PAYMENT_REQUEST_PUBLIC_WORKSPACE_V1_END */


        /* EGO_PAYMENT_ADVANCE_PUBLIC_WORKSPACE_V1
         * Đề nghị tạm ứng & hoàn ứng là ứng dụng dùng chung cho mọi phòng ban.
         * Chỉ mở launcher Workspace; quyền dữ liệu và phê duyệt vẫn do module kiểm soát.
         */
        if (Route::has('payment_advances.index')) {
            $paymentAdvanceApp = collect(config('ego_workspace.apps', []))
                ->first(function (array $candidate): bool {
                    $route = (string) ($candidate['route'] ?? '');
                    $id = (string) ($candidate['id'] ?? '');
                    $fallback = (string) ($candidate['fallback'] ?? '');

                    return $route === 'payment_advances.index'
                        || in_array($id, ['payment_advances', 'payment-advances', 'payment_advance'], true)
                        || str_contains($fallback, 'tam-ung-hoan-ung');
                });

            if (! is_array($paymentAdvanceApp)) {
                $paymentAdvanceApp = [
                    'id' => 'payment_advances',
                    'name' => 'Đề nghị tạm ứng & hoàn ứng',
                    'description' => 'Tạo tạm ứng, hoàn ứng và theo dõi quyết toán',
                    'route' => 'payment_advances.index',
                    'fallback' => '/payment-requests/tam-ung-hoan-ung',
                    'icon' => 'bi-wallet2',
                    'tone' => 'cyan',
                    'category' => 'finance',
                    'badge_key' => 'pending_payment_advances',
                    'keywords' => ['đề nghị tạm ứng', 'hoàn ứng', 'quyết toán', 'tạm ứng', 'dnhu'],
                ];
            }

            $paymentAdvanceId = (string) ($paymentAdvanceApp['id'] ?? 'payment_advances');

            if (! $apps->contains(fn (array $app): bool =>
                (string) ($app['id'] ?? '') === $paymentAdvanceId
                || (string) ($app['route'] ?? '') === 'payment_advances.index'
            )) {
                $paymentAdvanceApp['url'] = route('payment_advances.index');
                $paymentAdvanceApp['badge'] = max(0, (int) (
                    $badgeCounts[$paymentAdvanceApp['badge_key'] ?? 'pending_payment_advances'] ?? 0
                ));
                $paymentAdvanceApp['keywords_text'] = mb_strtolower(implode(' ', array_merge(
                    [(string) ($paymentAdvanceApp['name'] ?? ''), (string) ($paymentAdvanceApp['description'] ?? '')],
                    $paymentAdvanceApp['keywords'] ?? []
                )));

                $apps->push($paymentAdvanceApp);
            }
        }
        /* EGO_PAYMENT_ADVANCE_PUBLIC_WORKSPACE_V1_END */


        /* EGO_WAREHOUSE_CUSTOMER_RECEIVABLE_WORKSPACE_V1
         * Kho được xem Công nợ phải thu ngay từ Workspace.
         * Route chỉ là GET nên Kho có quyền xem, không mở quyền ghi/xóa công nợ.
         */
        if ($primaryProfileKey === 'warehouse' && Route::has('finance.customer-debts.index')) {
            $receivableApp = [
                'id' => 'warehouse-customer-receivables',
                'name' => 'Công nợ phải thu',
                'description' => 'Theo dõi công nợ khách hàng và lịch sử thanh toán',
                'route' => 'finance.customer-debts.index',
                'fallback' => '/finance/customer-debts',
                'icon' => 'bi-cash-coin',
                'tone' => 'cyan',
                'category' => 'finance',
                'badge_key' => '',
                'keywords' => ['công nợ phải thu', 'công nợ khách hàng', 'phải thu', 'khách hàng'],
            ];

            if (! $apps->contains(fn (array $app): bool =>
                (string) ($app['id'] ?? '') === 'warehouse-customer-receivables'
                || (string) ($app['route'] ?? '') === 'finance.customer-debts.index'
            )) {
                $receivableApp['url'] = route('finance.customer-debts.index');
                $receivableApp['badge'] = 0;
                $receivableApp['keywords_text'] = mb_strtolower(implode(' ', array_merge(
                    [(string) $receivableApp['name'], (string) $receivableApp['description']],
                    $receivableApp['keywords']
                )));
                $apps->push($receivableApp);
            }
        }
        /* EGO_WAREHOUSE_CUSTOMER_RECEIVABLE_WORKSPACE_V1_END */

        /* EGO_GIFT_MANAGEMENT_V2_WORKSPACE_START */
        if (in_array('gifts', $allowedIds, true) && Route::has('hr.gifts.index')) {
            $giftPendingBadge = 0;
            try {
                if (Schema::hasTable('hr_gift_requests')) {
                    $giftPendingBadge = (int) DB::table('hr_gift_requests')
                        ->where('company_id', EgoCompanyLock::id())
                        ->where('status', 'pending')
                        ->count();
                }
            } catch (Throwable) {
                $giftPendingBadge = 0;
            }

            $apps = $apps->map(function (array $app) use ($giftPendingBadge): array {
                if ((string) ($app['id'] ?? '') !== 'gifts') {
                    return $app;
                }

                $app['url'] = route('hr.gifts.index');
                $app['badge'] = $giftPendingBadge;

                return $app;
            })->values();
        }
        /* EGO_GIFT_MANAGEMENT_V2_WORKSPACE_END */

        /* EGO_BUSINESS_TRIP_WARRANTY_WORKSPACE_V1_START
         * Lịch công tác là app dùng chung cho mọi Workspace.
         * Đổi hàng bảo hành chỉ được ghim vào Workspace Kỹ thuật.
         * Việc thêm app tại đây không nới quyền backend của route/controller.
         */
        $workspaceInsertAppAfter = static function (Collection $collection, array $app, string $afterId): Collection {
            $appId = (string) ($app['id'] ?? '');
            if ($appId !== '' && $collection->contains(fn (array $existing): bool => (string) ($existing['id'] ?? '') === $appId)) {
                return $collection->values();
            }

            $result = collect();
            $inserted = false;
            foreach ($collection as $existing) {
                $result->push($existing);
                if ((string) ($existing['id'] ?? '') === $afterId) {
                    $result->push($app);
                    $inserted = true;
                }
            }
            if (! $inserted) {
                $result->push($app);
            }

            return $result->values();
        };

        if (Route::has('business-trips.index')) {
            $businessTripBadge = 0;
            try {
                if (Schema::hasTable('hr_business_trips')) {
                    $tripBadgeQuery = DB::table('hr_business_trips')->where('status', 'pending');
                    $tripManagers = ['admin', 'management', 'manager', 'director', 'ceo', 'hr', 'hr_manager', 'human_resources'];
                    if (! (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($tripManagers))) {
                        $tripBadgeQuery->where('approver_id', (int) $user->id);
                    }
                    $businessTripBadge = (int) $tripBadgeQuery->count();
                }
            } catch (Throwable) {
                $businessTripBadge = 0;
            }

            $businessTripApp = [
                'id' => 'business-trips',
                'name' => 'LỊCH CÔNG TÁC',
                'description' => 'Tạo lịch, phụ cấp, phê duyệt và hoàn tất công tác',
                'category' => 'hr',
                'icon' => 'bi-geo-alt-fill',
                'tone' => 'orange',
                'route' => 'business-trips.index',
                'fallback' => '/lich-cong-tac',
                'page_permission' => null,
                'badge_key' => null,
                'badge' => $businessTripBadge,
                'url' => route('business-trips.index'),
                'keywords' => ['lịch công tác', 'công tác', 'phụ cấp', 'địa điểm', 'business trip'],
                'keywords_text' => 'lịch công tác công tác phụ cấp địa điểm business trip',
            ];
            $apps = $workspaceInsertAppAfter($apps, $businessTripApp, 'attendance');
        }

        if ($primaryProfileKey === 'technical' && Route::has('ky-thuat.warranty-exchange.index')) {
            $warrantyExchangeBadge = 0;
            try {
                if (Schema::hasTable('crm_serial_warranty_claims')) {
                    $warrantyQuery = DB::table('crm_serial_warranty_claims')
                        ->where('claim_type', 'replacement')
                        ->where('status', 'pending_approval');
                    $companyId = (int) EgoCompanyLock::id();
                    if ($companyId > 0 && Schema::hasColumn('crm_serial_warranty_claims', 'company_id')) {
                        $warrantyQuery->where('company_id', $companyId);
                    }
                    $warrantyExchangeBadge = (int) $warrantyQuery->count();
                }
            } catch (Throwable) {
                $warrantyExchangeBadge = 0;
            }

            $warrantyExchangeApp = [
                'id' => 'warranty-exchange',
                'name' => 'ĐỔI HÀNG BẢO HÀNH',
                'description' => 'Kỹ thuật đề xuất đổi thiết bị lỗi theo serial bảo hành',
                'category' => 'technical',
                'icon' => 'bi-arrow-repeat',
                'tone' => 'amber',
                'route' => 'ky-thuat.warranty-exchange.index',
                'fallback' => '/ky-thuat/de-xuat-doi-hang-bao-hanh',
                'page_permission' => 'page.technical',
                'badge_key' => null,
                'badge' => $warrantyExchangeBadge,
                'url' => route('ky-thuat.warranty-exchange.index'),
                'keywords' => ['đổi hàng bảo hành', 'bảo hành', 'serial', 'kỹ thuật', 'warranty'],
                'keywords_text' => 'đổi hàng bảo hành bảo hành serial kỹ thuật warranty',
            ];
            $apps = $workspaceInsertAppAfter($apps, $warrantyExchangeApp, 'technical-workspace');
        }
        /* EGO_BUSINESS_TRIP_WARRANTY_WORKSPACE_V1_END */
        /* EGO_WORKSPACE_DASHBOARD_FIRST_V32_START */
        $workspaceDashboardMap = [
            'admin' => 'dashboard',
            'management' => 'dashboard',
            'technical' => 'technical-workspace',
            'sales' => 'sales-workspace',
            'accounting' => 'finance',
            'warehouse' => 'warehouse-workspace',
            'hr' => 'hr',
            'marketing' => 'marketing',
        ];
        $workspaceDashboardId = (string) ($workspaceDashboardMap[$primaryProfileKey] ?? '');

        if ($workspaceDashboardId !== '') {
            $apps = $apps
                ->sortBy(static function (array $app) use ($workspaceDashboardId, $featuredIds): int {
                    $id = (string) ($app['id'] ?? '');
                    if ($id === $workspaceDashboardId) {
                        return 0;
                    }
                    return in_array($id, $featuredIds, true) ? 10 : 20;
                })
                ->values();
        }
        /* EGO_WORKSPACE_DASHBOARD_FIRST_V32_END */
        $categories = $this->visibleCategories($apps);
        $quickApps = $this->quickApps($apps, $featuredIds);
        $branding = $this->branding();

        return view('workspace.index', [
            'apps' => $apps,
            'categories' => $categories,
            'quickApps' => $quickApps,
            'user' => $user,
            'avatarUrl' => $this->avatarUrl($user),
            'roleLabel' => $this->roleLabel($user),
            'companyName' => $this->companyName(),
            'brandName' => $branding['brand_name'],
            'brandShortName' => $branding['brand_short_name'],
            'brandLogoUrl' => asset($branding['logo_light']),
            'faviconUrl' => $branding['favicon'] !== '' ? asset($branding['favicon']) : null,
            'canManageWorkspace' => $canManageWorkspace,
            'workspaceOptions' => $workspaceOptions,
            'canSwitchWorkspace' => $canSwitchWorkspace,
            'isCustomWorkspace' => $isCustomWorkspace,
            'activeProfileKey' => $primaryProfileKey,
            'activeProfileLabel' => (string) ($activeProfile['label'] ?? 'Nhân viên'),
            'defaultCategory' => $this->profiles->defaultCategory($profileKeys),
            'workspaceJsApps' => $apps->map(fn (array $app): array => [
                'id' => $app['id'],
                'name' => $app['name'],
                'url' => $app['url'],
                'icon' => $app['icon'],
                'tone' => $app['tone'],
            ])->values()->all(),
        ]);
    }

    private function isSharedCompanyApp(array $app): bool
    {
        return in_array(
            (string) ($app['route'] ?? ''),
            ['de-xuat.index', 'company-documents.index'],
            true
        );
    }
    private function canOpen(User $user, array $app): bool
    {
        $permission = $app['page_permission'] ?? null;
        if (is_string($permission) && $permission !== '' && ! $this->pageAccess->canAccess($user, $permission)) {
            return false;
        }

        $ability = $app['ability'] ?? null;
        if (is_string($ability) && $ability !== '' && ! $user->can($ability)) {
            return false;
        }

        return true;
    }

    private function quickApps(Collection $apps, array $featuredIds): Collection
    {
        $indexed = $apps->keyBy('id');
        $quick = collect($featuredIds)
            ->map(fn (string $id) => $indexed->get($id))
            ->filter()
            ->values();

        if ($quick->count() < 3) {
            $quick = $quick
                ->concat($apps->reject(fn (array $app): bool => $quick->contains('id', $app['id'] ?? null)))
                ->take(3)
                ->values();
        }

        return $quick;
    }

    private function visibleCategories(Collection $apps): array
    {
        $definitions = config('ego_workspace.categories', []);
        $used = $apps->pluck('category')->filter()->unique()->values()->all();
        $visible = ['all' => $definitions['all'] ?? 'Tất cả'];

        foreach ($definitions as $key => $label) {
            if ($key !== 'all' && in_array($key, $used, true)) {
                $visible[$key] = $label;
            }
        }

        return $visible;
    }

    private function roleLabel(User $user): string
    {
        $position = trim((string) ($user->position->name ?? ''));
        if ($position !== '') {
            return $position;
        }

        $roles = $user->roles
            ->map(fn ($role): string => $this->pageAccess->displayRoleName($role))
            ->filter()
            ->unique()
            ->values();

        return $roles->isNotEmpty() ? $roles->join(' · ') : 'Tài khoản EGO Solar';
    }

    private function avatarUrl(User $user): ?string
    {
        $avatar = $user->avatar;
        $path = is_object($avatar) ? ($avatar->file_path ?? null) : null;

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/storage/')) {
            return $path;
        }

        try {
            return Storage::url($path);
        } catch (Throwable) {
            return null;
        }
    }


    private function branding(): array
    {
        $defaults = [
            'brand_name' => 'EGO Solar CRM',
            'brand_short_name' => 'EGO Solar',
            'logo_light' => 'images/ego-logo.png',
            'favicon' => '',
        ];

        try {
            if (! Schema::hasTable('ego_system_settings')) {
                return $defaults;
            }

            $stored = DB::table('ego_system_settings')
                ->whereIn('key', array_keys($defaults))
                ->pluck('value', 'key')
                ->map(static fn ($value): string => (string) $value)
                ->all();

            $settings = array_merge($defaults, $stored);
        } catch (Throwable) {
            $settings = $defaults;
        }

        foreach (['brand_name', 'brand_short_name', 'logo_light', 'favicon'] as $key) {
            $settings[$key] = trim((string) ($settings[$key] ?? $defaults[$key]));
        }

        if ($settings['brand_name'] === '') {
            $settings['brand_name'] = $defaults['brand_name'];
        }
        if ($settings['brand_short_name'] === '') {
            $settings['brand_short_name'] = $defaults['brand_short_name'];
        }
        if ($settings['logo_light'] === '') {
            $settings['logo_light'] = $defaults['logo_light'];
        }

        return $settings;
    }

    private function companyName(): string
    {
        try {
            return EgoCompanyLock::name();
        } catch (Throwable) {
            return 'CÔNG TY TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO';
        }
    }
}
