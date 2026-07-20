<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Enums\MaterialRequestStatus;
use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Models\Projects\Site;
use App\Services\SiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller quản lý công trình điện mặt trời (CRUD, thanh toán, phân quyền sales).
 */
class SiteController extends Controller
{
    /**
     * Khởi tạo controller với SiteService.
     */
    public function __construct(
        private readonly SiteService $siteService
    ) {}

    /**
     * Danh sách công trình có tìm kiếm, lọc trạng thái/công ty/ngày, giới hạn theo sales.
     */
    public function index(Request $request): View
    {
        $query = Site::query();

        if (
            $this->egoCurrentUserIsSalesOnly()
            && Schema::hasColumn('sites', 'created_by')
        ) {
            $query->where('created_by', auth()->id());
        }

        if ($request->filled('q')) {
            $keyword = trim(
                (string) $request->input('q')
            );

            $searchable = array_values(
                array_filter([
                    'name',
                    Schema::hasColumn(
                        'sites',
                        'customer_name'
                    ) ? 'customer_name' : null,
                    'contact_name',
                    'contact_phone',
                    Schema::hasColumn(
                        'sites',
                        'phone'
                    ) ? 'phone' : null,
                    'address',
                    Schema::hasColumn(
                        'sites',
                        'quote_customer_company'
                    ) ? 'quote_customer_company' : null,
                ])
            );

            $query->where(
                function ($subQuery) use (
                    $keyword,
                    $searchable
                ) {
                    foreach ($searchable as $column) {
                        $subQuery->orWhere(
                            $column,
                            'like',
                            '%'.$keyword.'%'
                        );
                    }
                }
            );
        }

        if (
            $request->filled('status')
            && Schema::hasColumn('sites', 'status')
        ) {
            $query->where(
                'status',
                (string) $request->input('status')
            );
        }

        if (
            $request->filled('company_id')
            && Schema::hasColumn('sites', 'company_id')
        ) {
            $query->where(
                'company_id',
                (int) $request->input('company_id')
            );
        }

        if (
            $request->filled('installed_from')
            && Schema::hasColumn('sites', 'installed_at')
        ) {
            $query->whereDate(
                'installed_at',
                '>=',
                $request->input('installed_from')
            );
        }

        if (
            $request->filled('installed_to')
            && Schema::hasColumn('sites', 'installed_at')
        ) {
            $query->whereDate(
                'installed_at',
                '<=',
                $request->input('installed_to')
            );
        }

        if (
            $request->filled('completed_from')
            && Schema::hasColumn('sites', 'completed_at')
        ) {
            $query->whereDate(
                'completed_at',
                '>=',
                $request->input('completed_from')
            );
        }

        if (
            $request->filled('completed_to')
            && Schema::hasColumn('sites', 'completed_at')
        ) {
            $query->whereDate(
                'completed_at',
                '<=',
                $request->input('completed_to')
            );
        }

        $orderColumn = Schema::hasColumn(
            'sites',
            'created_at'
        ) ? 'created_at' : 'id';

        $sites = $query
            ->orderByDesc($orderColumn)
            ->paginate(20)
            ->withQueryString();

        return view(
            'sites.index',
            compact('sites')
        );
    }

    /**
     * Hiển thị form tạo công trình mới.
     */
    public function create(): View
    {
        return view('sites.create');
    }

    /**
     * Tạo công trình mới kèm thiết bị, vật tư dự kiến và đợt thanh toán.
     */
    public function store(StoreSiteRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (Schema::hasColumn('sites', 'company_id')) {
            $companyId = (int) $request->input('company_id');
            $data['company_id'] = $companyId > 0
                ? $companyId
                : null;
        }

        if (Schema::hasColumn('sites', 'created_by')) {
            $data['created_by'] = auth()->id();
        }

        // Devices
        $devices = $data['devices'] ?? [];
        unset($data['devices']);

        // Planned vật tư dự kiến
        $planned = $data['planned'] ?? [];
        unset($data['planned']);

        // Đợt thanh toán
        $paymentTerms = $data['payment_terms'] ?? [];
        unset($data['payment_terms']);

        $site = $this->siteService->create($data);

        // Insert devices -> site_devices
        $this->siteService->syncDevices($site, $devices);

        // Insert planned -> site_planned_materials
        $this->siteService->syncPlannedMaterials($site, $planned);

        // Insert payment terms -> site_payment_terms
        $this->siteService->syncPaymentTerms($site, $paymentTerms);

        return redirect()
            ->route('sites.index')
            ->with('success', 'Đã tạo công trình!');
    }

    /**
     * Chi tiết công trình: thiết bị, vật tư, tài chính, thanh toán và vật tư thực tế đã xuất kho.
     */
    public function show(int $id): View
    {
        $site = $this->siteService->find($id);

        $this->egoAbortIfSalesCannotAccessSite($site);
        $devices = $this->siteService->getDevices($site);
        $planned = $this->siteService->getPlannedMaterials($site);
        $paymentTerms = $this->siteService->getPaymentTerms($site);
        $financeSummary = $this->siteService->getFinanceSummary($site);
        $paymentReceipts = $this->siteService->getPaymentReceipts($site);
        $systemSummary = $this->siteService->getSystemSummary($site, $devices);

        /*
         * Vật tư thực tế:
         * Lấy từ các đơn vật tư đã xuất kho của công trình.
         * Dùng để hiển thị bên tab công trình, follow theo đơn vật tư:
         * - Thiết bị chính
         * - Vật tư phụ
         */
        $actualMaterials = collect();

        if (
            Schema::hasTable('material_request_items')
            && Schema::hasTable('material_requests')
        ) {
            $select = [
                'mri.id',
                'mri.material_request_id',
                'mri.product_id',
                'mri.qty',
                'mri.note',
                'mri.created_at',
                'mri.updated_at',
                'mr.created_at as request_created_at',
                'mr.status as request_status',
            ];

            if (Schema::hasColumn('material_request_items', 'unit')) {
                $select[] = 'mri.unit';
            } else {
                $select[] = DB::raw("'' as unit");
            }

            if (Schema::hasColumn('material_request_items', 'unit_cost')) {
                $select[] = 'mri.unit_cost';
            } else {
                $select[] = DB::raw('0 as unit_cost');
            }

            if (Schema::hasColumn('material_request_items', 'vat_percent')) {
                $select[] = 'mri.vat_percent';
            } else {
                $select[] = DB::raw('0 as vat_percent');
            }

            if (Schema::hasColumn('material_request_items', 'line_total')) {
                $select[] = 'mri.line_total';
            } else {
                $select[] = DB::raw('0 as line_total');
            }

            if (Schema::hasTable('crm_product_catalog')) {
                $select[] = 'p.name as product_name';
                $select[] = 'p.sku as product_sku';
                $select[] = 'p.unit as product_unit';

                $actualMaterialsQuery = DB::table('material_request_items as mri')
                    ->join('material_requests as mr', 'mr.id', '=', 'mri.material_request_id')
                    ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'mri.product_id');
            } else {
                $select[] = DB::raw("'' as product_name");
                $select[] = DB::raw("'' as product_sku");
                $select[] = DB::raw("'' as product_unit");

                $actualMaterialsQuery = DB::table('material_request_items as mri')
                    ->join('material_requests as mr', 'mr.id', '=', 'mri.material_request_id');
            }

            $actualMaterials = $actualMaterialsQuery
                ->where('mr.site_id', $site->id)
                ->where('mr.status', MaterialRequestStatus::EXPORTED->value)
                ->select($select)
                ->orderByDesc('mr.id')
                ->orderBy('mri.id')
                ->get();
        }

        $actualMaterialSummary = [
            'total_rows' => $actualMaterials->count(),
            'total_qty' => $actualMaterials->sum(function ($item) {
                return (float) ($item->qty ?? 0);
            }),
            'total_cost' => $actualMaterials->sum(function ($item) {
                return (float) ($item->line_total ?? 0);
            }),
            'stock_rows' => $actualMaterials->filter(function ($item) {
                return ! empty($item->product_id);
            })->count(),
            'external_rows' => $actualMaterials->filter(function ($item) {
                return empty($item->product_id);
            })->count(),
        ];

        return view('sites.show', compact(
            'site',
            'devices',
            'planned',
            'paymentTerms',
            'financeSummary',
            'paymentReceipts',
            'systemSummary',
            'actualMaterials',
            'actualMaterialSummary'
        ));
    }

    /**
     * Hiển thị form sửa công trình.
     */
    public function edit(int $id): View
    {
        $site = $this->siteService->find($id);

        $this->egoAbortIfSalesCannotAccessSite($site);
        $devices = $this->siteService->getEditDevices($site);
        $planned = $this->siteService->getEditPlannedMaterials($site);
        $paymentTerms = $this->siteService->getPaymentTerms($site);

        return view('sites.edit', compact(
            'site',
            'devices',
            'planned',
            'paymentTerms'
        ));
    }

    /**
     * Cập nhật công trình, ép lưu chi phí phát sinh và đồng bộ thiết bị/vật tư/đợt thanh toán.
     */
    public function update(UpdateSiteRequest $request, int $id): RedirectResponse
    {
        $site = $this->siteService->find($id);

        $this->egoAbortIfSalesCannotAccessSite($site);
        $data = $request->validated();

        if (
            Schema::hasColumn('sites', 'company_id')
            && $request->exists('company_id')
        ) {
            $companyId = (int) $request->input(
                'company_id'
            );

            $data['company_id'] = $companyId > 0
                ? $companyId
                : null;
        }

        /*
        |--------------------------------------------------------------------------
        | FIX COST: bat buoc luu chi phi phat sinh, ke ca khi nhap/xoa ve 0
        |--------------------------------------------------------------------------
        */
        $toMoney = function ($value): float {
            if (is_array($value)) {
                return 0.0;
            }

            $value = trim((string) $value);

            if ($value === '') {
                return 0.0;
            }

            $value = str_replace(["\xc2\xa0", ' ', 'đ', 'Đ'], '', $value);

            if (str_contains($value, ',') && str_contains($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } elseif (str_contains($value, ',')) {
                $value = str_replace(',', '.', $value);
            } elseif (substr_count($value, '.') > 1) {
                $value = str_replace('.', '', $value);
            }

            $value = preg_replace('/[^0-9.\-]/', '', $value);

            return is_numeric($value) ? (float) $value : 0.0;
        };

        $costData = [
            'labor_cost' => $toMoney($request->input('labor_cost', 0)),
            'transport_cost' => $toMoney($request->input('transport_cost', 0)),
            'other_cost' => $toMoney($request->input('other_cost', 0)),
            'other_cost_note' => $request->input('other_cost_note'),
        ];

        $data = array_merge($data, $costData);

        $devices = $data['devices'] ?? [];
        unset($data['devices']);

        $planned = $data['planned'] ?? [];
        unset($data['planned']);

        $paymentTerms = $data['payment_terms'] ?? [];
        unset($data['payment_terms']);

        $this->siteService->update($site, $data);

        /*
        |--------------------------------------------------------------------------
        | Force update truc tiep DB de chac chan khong bi service/model bo sot.
        |--------------------------------------------------------------------------
        */
        $siteTable = $site->getTable();

        $forceUpdate = [];

        foreach ($costData as $key => $value) {
            if (Schema::hasColumn($siteTable, $key)) {
                $forceUpdate[$key] = $value;
            }
        }

        if (
            Schema::hasColumn($siteTable, 'company_id')
            && array_key_exists('company_id', $data)
        ) {
            $forceUpdate['company_id'] = $data['company_id'];
        }

        if (Schema::hasColumn($siteTable, 'updated_at')) {
            $forceUpdate['updated_at'] = now();
        }

        if (! empty($forceUpdate)) {
            DB::table($siteTable)
                ->where('id', $site->id)
                ->update($forceUpdate);
        }

        $site = $this->siteService->find($id);

        $this->egoAbortIfSalesCannotAccessSite($site);
        $this->siteService->syncDevices($site, $devices);
        $this->siteService->syncPlannedMaterials($site, $planned);
        $this->siteService->syncPaymentTerms($site, $paymentTerms);

        /*
        |--------------------------------------------------------------------------
        | Force update lan 2 sau sync de tranh bi ghi de.
        |--------------------------------------------------------------------------
        */
        if (! empty($forceUpdate)) {
            DB::table($siteTable)
                ->where('id', $site->id)
                ->update($forceUpdate);
        }

        return redirect()
            ->route('sites.show', $site->id)
            ->with('success', 'Đã cập nhật công trình!');
    }

    /**
     * Ghi nhận thanh toán cho một đợt thanh toán của công trình (tạo phiếu thu).
     */
    public function recordPayment(\Illuminate\Http\Request $request, $id)
    {
        $siteId = (int) $id;
        $redirectUrl = route('sites.show', $siteId).'#ghi-nhan-thanh-toan';

        $site = Site::query()->findOrFail($siteId);
        $this->egoAbortIfSalesCannotAccessSite($site);

        if (! \Illuminate\Support\Facades\Schema::hasTable('site_payment_terms')) {
            return redirect($redirectUrl)
                ->withInput()
                ->with('error', 'Chưa có bảng đợt thanh toán công trình.');
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('receipts')) {
            return redirect($redirectUrl)
                ->withInput()
                ->with('error', 'Chưa có bảng phiếu thu receipts.');
        }

        if (
            ! \Illuminate\Support\Facades\Schema::hasColumn(
                'receipts',
                'site_id'
            )
            || ! \Illuminate\Support\Facades\Schema::hasColumn(
                'receipts',
                'site_payment_term_id'
            )
        ) {
            return redirect($redirectUrl)
                ->withInput()
                ->with(
                    'error',
                    'Thiếu cột liên kết phiếu thu. '
                    .'Vui lòng chạy php artisan migrate trước.'
                );
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'site_payment_term_id' => ['required', 'integer'],
            'amount' => ['required'],
            'payment_method' => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'site_payment_term_id.required' => 'Vui lòng chọn đợt thanh toán.',
            'amount.required' => 'Vui lòng nhập số tiền thanh toán.',
            'payment_method.required' => 'Vui lòng chọn hình thức thanh toán.',
            'payment_date.required' => 'Vui lòng chọn ngày thanh toán.',
        ]);

        if ($validator->fails()) {
            return redirect($redirectUrl)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $term = \Illuminate\Support\Facades\DB::table('site_payment_terms')
            ->where('id', (int) $validated['site_payment_term_id'])
            ->where('site_id', $siteId)
            ->first();

        if (! $term) {
            return redirect($redirectUrl)
                ->withInput()
                ->with('error', 'Đợt thanh toán không hợp lệ hoặc không thuộc công trình này.');
        }

        $paidBefore = (float) \Illuminate\Support\Facades\DB::table('receipts')
            ->where('site_id', $siteId)
            ->where('site_payment_term_id', (int) $term->id)
            ->sum('amount');

        $remain = max(0, (float) $term->amount - $paidBefore);
        $amount = $this->egoSiteMoneyToFloat($validated['amount']);

        if ($amount <= 0) {
            return redirect($redirectUrl)
                ->withInput()
                ->with('error', 'Số tiền thanh toán không hợp lệ.');
        }

        if ($amount > $remain + 0.5) {
            return redirect($redirectUrl)
                ->withInput()
                ->with('error', 'Số tiền thu vượt quá số còn lại của đợt: '.number_format($remain, 0, ',', '.').' đ.');
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($site, $siteId, $term, $validated, $amount) {
                $schema = \Illuminate\Support\Facades\Schema::class;

                $insert = [];

                if ($schema::hasColumn('receipts', 'account_id')) {
                    $insert['account_id'] = null;
                }

                if ($schema::hasColumn('receipts', 'code')) {
                    $insert['code'] = $this->egoSiteNextReceiptCode();
                }

                if ($schema::hasColumn('receipts', 'receipt_date')) {
                    $insert['receipt_date'] = $validated['payment_date'];
                }

                if ($schema::hasColumn('receipts', 'paid_at')) {
                    $insert['paid_at'] = $validated['payment_date'];
                }

                if ($schema::hasColumn('receipts', 'payment_date')) {
                    $insert['payment_date'] = $validated['payment_date'];
                }

                if ($schema::hasColumn('receipts', 'payer_name')) {
                    $insert['payer_name'] = $site->contact_name ?: ($site->quote_customer_company ?: $site->name);
                }

                if ($schema::hasColumn('receipts', 'payer_phone')) {
                    $insert['payer_phone'] = $site->contact_phone ?? null;
                }

                if ($schema::hasColumn('receipts', 'category')) {
                    $insert['category'] = 'thu_khach_hang';
                }

                if ($schema::hasColumn('receipts', 'payment_method')) {
                    $insert['payment_method'] = $validated['payment_method'];
                }

                $insert['amount'] = $amount;

                if ($schema::hasColumn('receipts', 'note')) {
                    $insert['note'] = $validated['note'] ?? null;
                }

                if ($schema::hasColumn('receipts', 'created_by')) {
                    $insert['created_by'] = auth()->id();
                }

                $insert['site_id'] = $siteId;
                $insert['site_payment_term_id'] = (int) $term->id;

                if ($schema::hasColumn('receipts', 'created_at')) {
                    $insert['created_at'] = now();
                }

                if ($schema::hasColumn('receipts', 'updated_at')) {
                    $insert['updated_at'] = now();
                }

                \Illuminate\Support\Facades\DB::table('receipts')->insert($insert);

                $this->egoSiteSyncPaymentTermStatus((int) $term->id);
            });
        } catch (\Throwable $e) {
            return redirect($redirectUrl)
                ->withInput()
                ->with('error', 'Không lưu được thanh toán: '.$e->getMessage());
        }

        return redirect($redirectUrl)
            ->with('success', 'Đã ghi nhận thanh toán cho công trình #'.$siteId.'.');
    }

    /**
     * Xóa công trình nếu chưa phát sinh dữ liệu liên quan (phiếu thu, vật tư...).
     */
    public function destroy(int $id): RedirectResponse
    {
        $site = Site::query()->findOrFail($id);
        $this->egoAbortIfSalesCannotAccessSite($site);

        $blockingTables = [
            'receipts',
            'payments',
            'payment_requests',
            'material_requests',
            'solar_maintenance_schedules',
            'site_assemblies',
        ];

        foreach ($blockingTables as $table) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, 'site_id')
                && DB::table($table)
                    ->where('site_id', $site->id)
                    ->exists()
            ) {
                return back()->with(
                    'error',
                    'Không thể xóa công trình vì đã phát sinh '
                    .'dữ liệu liên quan trong bảng '
                    .$table
                    .'.'
                );
            }
        }

        $this->siteService->delete($site);

        return redirect()
            ->route('sites.index')
            ->with('success', 'Đã xoá công trình!');
    }

    /* EGO_SITE_CONTROLLER_SALES_SCOPE_START */
    /**
     * Kiểm tra user hiện tại có phải sales thuần (không phải admin/kế toán/kỹ thuật/kho).
     */
    private function egoCurrentUserIsSalesOnly(): bool
    {
        try {
            if (! auth()->check()) {
                return false;
            }

            $user = auth()->user();

            $roleTexts = [];

            foreach (['role', 'type', 'position', 'department'] as $field) {
                if (! empty($user->{$field})) {
                    $roleTexts[] = mb_strtolower((string) $user->{$field});
                }
            }

            if (method_exists($user, 'getRoleNames')) {
                foreach ($user->getRoleNames() as $roleName) {
                    $roleTexts[] = mb_strtolower((string) $roleName);
                }
            }

            $roleText = implode('|', array_unique(array_filter($roleTexts)));

            $hasRole = function (array $roles) use ($user, $roleText): bool {
                foreach ($roles as $role) {
                    $roleLower = mb_strtolower($role);

                    if (str_contains($roleText, $roleLower)) {
                        return true;
                    }

                    if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                        return true;
                    }

                    if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole([$role])) {
                        return true;
                    }
                }

                return false;
            };

            $isAdmin = ((int) ($user->is_admin ?? 0) === 1)
                || $hasRole(['admin', 'administrator', 'super_admin']);

            $isAccounting = $hasRole([
                'accounting',
                'ketoan',
                'ke_toan',
                'kế toán',
                'ketoan_truong',
            ]);

            $isTechnicalOrWarehouse = $hasRole([
                'ky_thuat',
                'kỹ thuật',
                'technical',
                'warehouse',
                'kho',
            ]);

            $isSales = $hasRole([
                'sales',
                'sale',
                'sales_manager',
                'kinh_doanh',
                'kinh doanh',
                'nhan_vien_kinh_doanh',
            ]);

            return $isSales && ! $isAdmin && ! $isAccounting && ! $isTechnicalOrWarehouse;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Chặn 403 nếu sales thuần truy cập công trình không do mình tạo.
     */
    private function egoAbortIfSalesCannotAccessSite($site): void
    {
        try {
            if (! $this->egoCurrentUserIsSalesOnly()) {
                return;
            }

            if (! Schema::hasColumn('sites', 'created_by')) {
                abort(403);
            }

            if ((int) ($site->created_by ?? 0) !== (int) auth()->id()) {
                abort(403);
            }
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            abort(403);
        }
    }
    /* EGO_SITE_CONTROLLER_SALES_SCOPE_END */

    /* EGO_SITE_PAYMENT_TERM_ONLY_START */
    /**
     * Cập nhật một phiếu thu thanh toán của công trình và đồng bộ trạng thái đợt.
     */
    public function updatePayment(\Illuminate\Http\Request $request, $id, $receipt)
    {
        $site = \Illuminate\Support\Facades\DB::table('sites')->where('id', (int) $id)->first();

        abort_unless($site, 404);
        $this->egoAbortIfSalesCannotAccessSite($site);

        $receiptRow = \Illuminate\Support\Facades\DB::table('receipts')
            ->where('id', (int) $receipt)
            ->where('site_id', (int) $site->id)
            ->first();

        abort_unless($receiptRow, 404);

        $validated = $request->validate([
            'site_payment_term_id' => ['required', 'integer'],
            'amount' => ['required'],
            'payment_method' => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'site_payment_term_id.required' => 'Vui lòng chọn đợt thanh toán.',
            'amount.required' => 'Vui lòng nhập số tiền.',
        ]);

        $term = \Illuminate\Support\Facades\DB::table('site_payment_terms')
            ->where('id', (int) $validated['site_payment_term_id'])
            ->where('site_id', (int) $site->id)
            ->first();

        if (! $term) {
            return back()->withInput()->with('error', 'Đợt thanh toán không hợp lệ hoặc không thuộc công trình này.');
        }

        $amount = $this->egoSiteMoneyToFloat($validated['amount']);

        if ($amount <= 0) {
            return back()->withInput()->with('error', 'Số tiền thanh toán không hợp lệ.');
        }

        $paidOther = (float) \Illuminate\Support\Facades\DB::table('receipts')
            ->where('site_id', (int) $site->id)
            ->where('site_payment_term_id', (int) $term->id)
            ->where('id', '!=', (int) $receiptRow->id)
            ->sum('amount');

        $remainAllow = max(0, (float) $term->amount - $paidOther);

        if ($amount > $remainAllow) {
            return back()->withInput()->with('error', 'Số tiền thu vượt quá số còn lại của đợt: '.number_format($remainAllow, 0, ',', '.').' đ.');
        }

        $oldTermId = $receiptRow->site_payment_term_id ? (int) $receiptRow->site_payment_term_id : null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($receiptRow, $validated, $amount, $term, $oldTermId) {
            \Illuminate\Support\Facades\DB::table('receipts')
                ->where('id', (int) $receiptRow->id)
                ->update([
                    'site_payment_term_id' => (int) $term->id,
                    'receipt_date' => $validated['payment_date'],
                    'payment_method' => $validated['payment_method'],
                    'amount' => $amount,
                    'note' => $validated['note'] ?? null,
                    'updated_at' => now(),
                ]);

            if ($oldTermId && $oldTermId !== (int) $term->id) {
                $this->egoSiteSyncPaymentTermStatus($oldTermId);
            }

            $this->egoSiteSyncPaymentTermStatus((int) $term->id);
        });

        return back()->with('success', 'Đã cập nhật thanh toán.');
    }

    /**
     * Xóa một phiếu thu thanh toán và đồng bộ lại trạng thái đợt.
     */
    public function destroyPayment($id, $receipt)
    {
        $site = \Illuminate\Support\Facades\DB::table('sites')->where('id', (int) $id)->first();

        abort_unless($site, 404);
        $this->egoAbortIfSalesCannotAccessSite($site);

        $receiptRow = \Illuminate\Support\Facades\DB::table('receipts')
            ->where('id', (int) $receipt)
            ->where('site_id', (int) $site->id)
            ->first();

        abort_unless($receiptRow, 404);

        $oldTermId = $receiptRow->site_payment_term_id ? (int) $receiptRow->site_payment_term_id : null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($receiptRow, $oldTermId) {
            \Illuminate\Support\Facades\DB::table('receipts')
                ->where('id', (int) $receiptRow->id)
                ->delete();

            if ($oldTermId) {
                $this->egoSiteSyncPaymentTermStatus($oldTermId);
            }
        });

        return back()->with('success', 'Đã xóa thanh toán.');
    }

    /**
     * Chuyển chuỗi tiền tệ nhiều định dạng (dấu chấm/phẩy) về float.
     */
    private function egoSiteMoneyToFloat($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        $value = str_replace(
            ["\xc2\xa0", ' ', 'đ', 'Đ'],
            '',
            $value
        );

        $value = preg_replace(
            '/[^0-9,\.\-]/',
            '',
            $value
        );

        if ($value === '' || $value === '-') {
            return 0.0;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            if (
                strrpos($value, ',')
                > strrpos($value, '.')
            ) {
                $value = str_replace(
                    '.',
                    '',
                    $value
                );

                $value = str_replace(
                    ',',
                    '.',
                    $value
                );
            } else {
                $value = str_replace(
                    ',',
                    '',
                    $value
                );
            }
        } elseif ($hasComma) {
            if (
                substr_count($value, ',') > 1
                || preg_match(
                    '/,\d{3}(,|$)/',
                    $value
                )
            ) {
                $value = str_replace(
                    ',',
                    '',
                    $value
                );
            } else {
                $value = str_replace(
                    ',',
                    '.',
                    $value
                );
            }
        } elseif ($hasDot) {
            if (
                substr_count($value, '.') > 1
                || preg_match(
                    '/\.\d{3}(\.|$)/',
                    $value
                )
            ) {
                $value = str_replace(
                    '.',
                    '',
                    $value
                );
            }
        }

        return is_numeric($value)
            ? (float) $value
            : 0.0;
    }

    /**
     * Sinh mã phiếu thu kế tiếp dạng PT-Ymd-XXXX.
     */
    private function egoSiteNextReceiptCode(): string
    {
        $prefix = 'PT-'.date('Ymd').'-';

        $lastCode = \Illuminate\Support\Facades\DB::table('receipts')
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('code');

        $next = 1;

        if ($lastCode && preg_match('/(\d+)$/', (string) $lastCode, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Đồng bộ trạng thái đợt thanh toán (pending/partial/paid) theo tổng tiền đã thu.
     */
    private function egoSiteSyncPaymentTermStatus(int $termId): void
    {
        $term = \Illuminate\Support\Facades\DB::table('site_payment_terms')->where('id', $termId)->first();

        if (! $term) {
            return;
        }

        $paid = (float) \Illuminate\Support\Facades\DB::table('receipts')
            ->where('site_payment_term_id', $termId)
            ->sum('amount');

        $amount = (float) $term->amount;

        if ($paid <= 0) {
            $status = 'pending';
        } elseif ($amount > 0 && $paid + 0.5 >= $amount) {
            $status = 'paid';
        } else {
            $status = 'partial';
        }

        \Illuminate\Support\Facades\DB::table('site_payment_terms')
            ->where('id', $termId)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
    }
    /* EGO_SITE_PAYMENT_TERM_ONLY_END */
}
