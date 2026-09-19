<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Contracts\Services\CustomerServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Core\Region;
use App\Models\CRM\Customers\Customer;
use App\Models\CRM\Customers\CustomerType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerServiceInterface $customerService
    ) {
        $this->middleware('auth');
        $this->authorizeResource(Customer::class, 'customer');
    }

    /**
     * Danh sách khách hàng.
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'search',
            'region_id',
            'customer_type_id',
            'customer_status',
            'owner_id',
            'is_potential',
            'has_order',
        ]);

        $customers = $this->customerService->search($filters);

        $this->decorateCustomerPage($customers);

        return view('customers.index', [
            'customers' => $customers,
            'regions' => Region::query()
                ->orderBy('name')
                ->get(),
            'customerTypes' => CustomerType::query()
                ->orderBy('name')
                ->get(),
            'users' => User::query()
                ->orderBy('name')
                ->get(),
            'stats' => $this->customerStats($request),
        ]);
    }

    /**
     * Tổng quan hợp nhất Khách hàng + Chăm sóc/Pipeline.
     * Không đọc/ghi thay đổi vào crm_orders hoặc crm_leads.
     */
    public function overview(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $pipelineStats = [
            'total' => 0,
            'caring' => 0,
            'quote_sent' => 0,
            'won' => 0,
            'need_follow' => 0,
            'unlinked' => 0,
            'potential_value' => 0.0,
        ];

        $pipelineByStatus = collect();

        if (Schema::hasTable('sales_work_reports')) {
            $pipeline = DB::table('sales_work_reports');

            if (! $this->canViewAllPipeline($request->user())) {
                $pipeline->where('assigned_to', (int) $request->user()->id);
            }

            $pipelineStats = [
                'total' => (clone $pipeline)->count(),
                'caring' => (clone $pipeline)
                    ->whereIn('status', ['consulting', 'follow_up', 'quoted'])
                    ->count(),
                'quote_sent' => (clone $pipeline)
                    ->whereIn('quote_status', ['sent', 'viewed', 'waiting'])
                    ->count(),
                'won' => (clone $pipeline)
                    ->where('status', 'won')
                    ->count(),
                'need_follow' => (clone $pipeline)
                    ->whereIn('status', ['consulting', 'follow_up', 'quoted'])
                    ->whereNotNull('next_followup_at')
                    ->where('next_followup_at', '<=', now()->addDay())
                    ->count(),
                'unlinked' => Schema::hasColumn('sales_work_reports', 'customer_id')
                    ? (clone $pipeline)->whereNull('customer_id')->count()
                    : 0,
                'potential_value' => (float) (clone $pipeline)
                    ->sum('revenue_expectation'),
            ];

            $pipelineByStatus = (clone $pipeline)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->orderByDesc('total')
                ->get();
        }

        return view('customers.overview', [
            'stats' => $this->customerStats($request),
            'pipelineStats' => $pipelineStats,
            'pipelineByStatus' => $pipelineByStatus,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('customers.index');
    }

    public function store(StoreCustomerRequest $request)
    {
        $customerData = $request->validated();

        $chatbotData = $request->validate([
            'ai_chatbot_link' => [
                'nullable',
                'url',
                'max:2048',
            ],
        ], [
            'ai_chatbot_link.url' =>
                'Link Chat Bot AI không đúng định dạng URL.',
        ]);

        $customerData['ai_chatbot_link'] =
            $chatbotData['ai_chatbot_link'] ?? null;

        $this->customerService->create($customerData);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Thêm khách hàng thành công.',
            ]);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', 'Thêm khách hàng thành công.');
    }

    /**
     * Hồ sơ khách hàng.
     */
    public function show(Customer $customer): View
    {
        $customerDetail = $this->customerService
            ->findWithDetails($customer->id);

        $interactions = collect();

        if (Schema::hasTable('crm_customer_interactions')) {
            $interactions = DB::table(
                'crm_customer_interactions as interactions'
            )
                ->leftJoin(
                    'users as creators',
                    'creators.id',
                    '=',
                    'interactions.created_by'
                )
                ->where(
                    'interactions.customer_id',
                    $customer->id
                )
                ->orderByDesc('interactions.interaction_date')
                ->orderByDesc('interactions.id')
                ->select([
                    'interactions.*',
                    'creators.name as creator_name',
                ])
                ->limit(100)
                ->get();
        }

        $nextFollowup = $interactions
            ->filter(
                fn (object $item): bool =>
                    ! empty($item->next_followup_date)
            )
            ->sortBy('next_followup_date')
            ->first();

        $orders = collect($customerDetail->orders ?? [])
            ->sortByDesc(
                fn ($order) =>
                    $order->order_date
                    ?? $order->created_at
                    ?? null
            )
            ->values();

        $orderRevenue = (float) $orders->sum(
            fn ($order): float =>
                (float) ($order->total_amount ?? 0)
        );

        $debtTotal = 0.0;
        $debtRows = collect();

        if (Schema::hasTable('crm_customer_debts')) {
            $debtRows = DB::table('crm_customer_debts')
                ->where('customer_id', $customer->id)
                ->orderByDesc('id')
                ->get();

            $debtTotal = (float) $debtRows
                ->where('status', '!=', 'paid')
                ->sum('debt_amount');
        }

        $quotations = collect();

        if (Schema::hasTable('sales_quotations')) {
            $quotations = DB::table('sales_quotations')
                ->where('customer_id', $customer->id)
                ->whereNull('deleted_at')
                ->orderByDesc('quote_date')
                ->orderByDesc('id')
                ->limit(30)
                ->get();
        }

        $warrantyEventCount = 0;

        if (Schema::hasTable('crm_serial_warranty_events')) {
            $warrantyEventCount = DB::table(
                'crm_serial_warranty_events'
            )
                ->where('customer_id', $customer->id)
                ->count();
        }


        /*
         * EGO_CUSTOMER_SITES_V31_START
         *
         * Bảng sites hiện chưa có customer_id.
         * Liên kết an toàn theo thứ tự:
         * - 9 số cuối điện thoại.
         * - Email xuất hóa đơn.
         * - Mã số thuế.
         * - Tên liên hệ khi không có ba dữ liệu trên.
         *
         * Đồng thời giới hạn theo company_id của khách hàng.
         */
        $sites = collect();
        $siteContractValue = 0.0;

        if (Schema::hasTable('sites')) {
            $siteColumns = Schema::getColumnListing('sites');

            $phoneDigits = preg_replace(
                '/\D+/',
                '',
                (string) ($customerDetail->phone ?? '')
            );

            $phoneTail = strlen($phoneDigits) >= 8
                ? substr($phoneDigits, -9)
                : '';

            $customerEmail = mb_strtolower(
                trim((string) (
                    $customerDetail->billing_email
                    ?: $customerDetail->email
                    ?: ''
                ))
            );

            $customerTaxCode = preg_replace(
                '/\s+/',
                '',
                (string) (
                    $customerDetail->billing_tax_code
                    ?? ''
                )
            );

            $customerName = mb_strtolower(
                trim((string) (
                    $customerDetail->name
                    ?? ''
                ))
            );

            $hasStrongIdentity =
                $phoneTail !== ''
                || $customerEmail !== ''
                || $customerTaxCode !== '';

            $hasIdentity =
                $hasStrongIdentity
                || $customerName !== '';

            if ($hasIdentity) {
                $siteQuery = DB::table('sites');

                if (
                    in_array('company_id', $siteColumns, true)
                    && ! empty($customerDetail->company_id)
                ) {
                    $siteQuery->where(
                        'company_id',
                        (int) $customerDetail->company_id
                    );
                }

                $siteQuery->where(
                    function ($match) use (
                        $phoneTail,
                        $customerEmail,
                        $customerTaxCode,
                        $customerName,
                        $hasStrongIdentity,
                        $siteColumns
                    ): void {
                        $conditionAdded = false;

                        if (
                            $phoneTail !== ''
                            && in_array(
                                'contact_phone',
                                $siteColumns,
                                true
                            )
                        ) {
                            $match->whereRaw(
                                "RIGHT(
                                    REPLACE(
                                        REPLACE(
                                            REPLACE(
                                                REPLACE(
                                                    REPLACE(
                                                        REPLACE(
                                                            COALESCE(contact_phone, ''),
                                                            ' ',
                                                            ''
                                                        ),
                                                        '.',
                                                        ''
                                                    ),
                                                    '-',
                                                    ''
                                                ),
                                                '(',
                                                ''
                                            ),
                                            ')',
                                            ''
                                        ),
                                        '+',
                                        ''
                                    ),
                                    9
                                ) = ?",
                                [$phoneTail]
                            );

                            $conditionAdded = true;
                        }

                        if (
                            $customerEmail !== ''
                            && in_array(
                                'quote_customer_email',
                                $siteColumns,
                                true
                            )
                        ) {
                            $method = $conditionAdded
                                ? 'orWhereRaw'
                                : 'whereRaw';

                            $match->{$method}(
                                'LOWER(TRIM(COALESCE(quote_customer_email, ""))) = ?',
                                [$customerEmail]
                            );

                            $conditionAdded = true;
                        }

                        if (
                            $customerTaxCode !== ''
                            && in_array(
                                'quote_customer_tax_code',
                                $siteColumns,
                                true
                            )
                        ) {
                            $method = $conditionAdded
                                ? 'orWhereRaw'
                                : 'whereRaw';

                            $match->{$method}(
                                "REPLACE(
                                    COALESCE(quote_customer_tax_code, ''),
                                    ' ',
                                    ''
                                ) = ?",
                                [$customerTaxCode]
                            );

                            $conditionAdded = true;
                        }

                        /*
                         * Chỉ dùng tên khi khách hàng không có
                         * điện thoại, email hoặc mã số thuế.
                         */
                        if (
                            ! $hasStrongIdentity
                            && $customerName !== ''
                            && in_array(
                                'contact_name',
                                $siteColumns,
                                true
                            )
                        ) {
                            $method = $conditionAdded
                                ? 'orWhereRaw'
                                : 'whereRaw';

                            $match->{$method}(
                                'LOWER(TRIM(COALESCE(contact_name, ""))) = ?',
                                [$customerName]
                            );

                            $conditionAdded = true;
                        }

                        if (! $conditionAdded) {
                            $match->whereRaw('1 = 0');
                        }
                    }
                );

                $siteSelect = array_values(
                    array_intersect(
                        [
                            'id',
                            'name',
                            'status',
                            'stage',
                            'address',
                            'contact_name',
                            'contact_phone',
                            'system_kwp',
                            'system_kw_ac',
                            'battery_kwh',
                            'system_type',
                            'phase',
                            'installed_at',
                            'completed_at',
                            'warranty_to',
                            'contract_amount',
                            'technician_name',
                            'created_at',
                            'updated_at',
                        ],
                        $siteColumns
                    )
                );

                if (! in_array('id', $siteSelect, true)) {
                    $siteSelect[] = 'id';
                }

                $sites = $siteQuery
                    ->select($siteSelect)
                    ->orderByDesc(
                        in_array(
                            'created_at',
                            $siteColumns,
                            true
                        )
                            ? 'created_at'
                            : 'id'
                    )
                    ->limit(50)
                    ->get();

                $siteContractValue = (float) $sites->sum(
                    fn (object $site): float =>
                        (float) ($site->contract_amount ?? 0)
                );
            }
        }
        /* EGO_CUSTOMER_SITES_V31_END */

        $lastInteraction = $interactions->first();

        /*
         * EGO_CUSTOMER_HANDOVER_SHOW_V32
         */
        $canHandover = $this->canHandoverCustomer(
            $customer,
            request()->user()
        );

        $handoverUsers = $canHandover
            ? $this->eligibleHandoverUsers($customer)
            : collect();

        $handoverHistory = collect();

        if (
            Schema::hasTable(
                'crm_customer_handover_logs'
            )
        ) {
            $handoverHistory = DB::table(
                'crm_customer_handover_logs as logs'
            )
                ->leftJoin(
                    'users as from_users',
                    'from_users.id',
                    '=',
                    'logs.from_user_id'
                )
                ->leftJoin(
                    'users as to_users',
                    'to_users.id',
                    '=',
                    'logs.to_user_id'
                )
                ->leftJoin(
                    'users as action_users',
                    'action_users.id',
                    '=',
                    'logs.handed_over_by'
                )
                ->where(
                    'logs.customer_id',
                    $customer->id
                )
                ->orderByDesc('logs.id')
                ->limit(20)
                ->get([
                    'logs.id',
                    'logs.note',
                    'logs.created_at',
                    'from_users.name as from_user_name',
                    'to_users.name as to_user_name',
                    'action_users.name as action_user_name',
                ]);
        }


        return view('customers.show', [
            'customer' => $customerDetail,
            'interactions' => $interactions,
            'nextFollowup' => $nextFollowup,
            'lastInteraction' => $lastInteraction,
            'canHandover' => $canHandover,
            'handoverUsers' => $handoverUsers,
            'handoverHistory' => $handoverHistory,
            'orders' => $orders,
            'debtRows' => $debtRows,
            'quotations' => $quotations,
            'sites' => $sites,
            'summary' => [
                'orders' => $orders->count(),
                'revenue' => $orderRevenue,
                'debt' => $debtTotal,
                'quotations' => $quotations->count(),
                'warranty_events' => $warrantyEventCount,
                'sites' => $sites->count(),
                'site_contract_value' => $siteContractValue,
            ],
        ]);
    }

    public function edit(Customer $customer): RedirectResponse
    {
        return redirect()->route('customers.index');
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer
    ) {
        $customerData = $request->validated();

        $chatbotData = $request->validate([
            'ai_chatbot_link' => [
                'nullable',
                'url',
                'max:2048',
            ],
        ], [
            'ai_chatbot_link.url' =>
                'Link Chat Bot AI không đúng định dạng URL.',
        ]);

        if ($request->exists('ai_chatbot_link')) {
            $customerData['ai_chatbot_link'] =
                $chatbotData['ai_chatbot_link'] ?? null;
        }

        $this->customerService->update(
            $customer->id,
            $customerData
        );

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Cập nhật khách hàng thành công.',
            ]);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', 'Cập nhật khách hàng thành công.');
    }

    public function destroy(
        Customer $customer
    ): RedirectResponse {
        $this->customerService->delete($customer->id);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Đã xóa khách hàng.');
    }

    /**
     * Form popup tạo/sửa.
     */
    public function ajaxForm(
        ?string $id = null
    ): View {
        $customer = $id
            ? $this->customerService->find($id)
            : null;

        if ($customer) {
            $this->authorize('update', $customer);
        } else {
            $this->authorize('create', Customer::class);
        }

        return view('customers._form', [
            'customer' => $customer,
            'regions' => Region::query()
                ->orderBy('name')
                ->get(),
            'customerTypes' => CustomerType::query()
                ->orderBy('name')
                ->get(),
            'users' => User::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Lưu lịch sử chăm sóc khách hàng.
     */
    public function storeInteraction(
        Request $request,
        Customer $customer
    ): RedirectResponse {
        $this->authorize('update', $customer);

        abort_unless(
            Schema::hasTable('crm_customer_interactions'),
            503,
            'Bảng lịch sử chăm sóc chưa sẵn sàng.'
        );

        $validated = $request->validate([
            'interaction_type' => [
                'required',
                'in:call,message,meeting,email,note',
            ],
            'subject' => [
                'nullable',
                'string',
                'max:255',
            ],
            'content' => [
                'required',
                'string',
                'max:5000',
            ],
            'interaction_date' => [
                'nullable',
                'date',
            ],
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:0',
                'max:1440',
            ],
            'outcome' => [
                'nullable',
                'in:positive,neutral,negative,no_answer',
            ],
            'next_followup_date' => [
                'nullable',
                'date',
            ],
        ], [
            'content.required' =>
                'Vui lòng nhập nội dung chăm sóc.',
            'interaction_type.required' =>
                'Vui lòng chọn hình thức tương tác.',
        ]);

        $now = now();

        DB::table('crm_customer_interactions')->insert([
            'customer_id' => $customer->id,
            'lead_id' => null,
            'interaction_type' =>
                $validated['interaction_type'],
            'subject' =>
                $validated['subject'] ?? null,
            'content' =>
                $validated['content'],
            'interaction_date' =>
                $validated['interaction_date'] ?? $now,
            'duration_minutes' =>
                $validated['duration_minutes'] ?? null,
            'outcome' =>
                $validated['outcome'] ?? null,
            'next_followup_date' =>
                $validated['next_followup_date'] ?? null,
            'created_by' =>
                $request->user()?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()
            ->route('customers.show', $customer)
            ->with(
                'success',
                'Đã lưu lịch sử chăm sóc khách hàng.'
            );
    }

    /**
     * EGO_CUSTOMER_HANDOVER_METHOD_V32
     *
     * Chuyển người phụ trách khách hàng.
     */
    public function handover(
        Request $request,
        Customer $customer
    ): RedirectResponse {
        $this->authorize('update', $customer);

        abort_unless(
            $this->canHandoverCustomer(
                $customer,
                $request->user()
            ),
            403,
            'Bạn không có quyền bàn giao khách hàng này.'
        );

        $validated = $request->validate([
            'owner_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'handover_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ], [
            'owner_id.required' =>
                'Vui lòng chọn nhân viên nhận bàn giao.',
            'owner_id.exists' =>
                'Nhân viên nhận bàn giao không tồn tại.',
            'handover_note.max' =>
                'Ghi chú bàn giao tối đa 2.000 ký tự.',
        ]);

        $newOwnerId = (int) $validated['owner_id'];

        DB::transaction(
            function () use (
                $customer,
                $newOwnerId,
                $validated,
                $request
            ): void {
                $lockedCustomer = Customer::query()
                    ->whereKey($customer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldOwnerId = $lockedCustomer->owner_id
                    ? (int) $lockedCustomer->owner_id
                    : null;

                abort_if(
                    $oldOwnerId === $newOwnerId,
                    422,
                    'Khách hàng đang do nhân viên này phụ trách.'
                );

                $newOwner = User::query()
                    ->whereKey($newOwnerId)
                    ->firstOrFail();

                if (
                    Schema::hasColumn(
                        'users',
                        'is_active'
                    )
                ) {
                    abort_if(
                        ! (bool) $newOwner->is_active,
                        422,
                        'Không thể bàn giao cho tài khoản đã ngừng hoạt động.'
                    );
                }

                $lockedCustomer->update([
                    'owner_id' => $newOwnerId,
                ]);

                if (
                    Schema::hasTable(
                        'crm_customer_handover_logs'
                    )
                ) {
                    DB::table(
                        'crm_customer_handover_logs'
                    )->insert([
                        'customer_id' =>
                            $lockedCustomer->id,

                        'from_user_id' =>
                            $oldOwnerId,

                        'to_user_id' =>
                            $newOwnerId,

                        'handed_over_by' =>
                            $request->user()?->id,

                        'note' =>
                            $validated['handover_note']
                            ?? null,

                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        );

        $newOwnerName = User::query()
            ->whereKey($newOwnerId)
            ->value('name');

        return redirect()
            ->route('customers.show', $customer)
            ->with(
                'success',
                'Đã bàn giao khách hàng cho '
                .($newOwnerName ?: 'nhân viên mới')
                .'.'
            );
    }

    /**
     * Kiểm tra khách hàng bị trùng.
     */
    public function duplicateCheck(
        Request $request
    ): JsonResponse {
        $this->authorize('viewAny', Customer::class);

        $validated = $request->validate([
            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'email' => [
                'nullable',
                'string',
                'max:255',
            ],
            'tax_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'exclude_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $phone = preg_replace(
            '/\D+/',
            '',
            (string) ($validated['phone'] ?? '')
        );

        $email = mb_strtolower(
            trim((string) ($validated['email'] ?? ''))
        );

        $taxCode = preg_replace(
            '/\s+/',
            '',
            (string) ($validated['tax_code'] ?? '')
        );

        if (
            $phone === ''
            && $email === ''
            && $taxCode === ''
        ) {
            return response()->json([
                'matches' => [],
            ]);
        }

        $query = Customer::query()
            ->visibleToUser($request->user());

        if (! empty($validated['exclude_id'])) {
            $query->where(
                'id',
                '!=',
                (int) $validated['exclude_id']
            );
        }

        $query->where(function ($subQuery) use (
            $phone,
            $email,
            $taxCode
        ): void {
            $hasCondition = false;

            if ($phone !== '') {
                $subQuery->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '.', ''), '-', ''), '+84', '0') = ?",
                    [$phone]
                );

                $hasCondition = true;
            }

            if ($email !== '') {
                $method = $hasCondition
                    ? 'orWhereRaw'
                    : 'whereRaw';

                $subQuery->{$method}(
                    'LOWER(TRIM(email)) = ?',
                    [$email]
                );

                $hasCondition = true;
            }

            if ($taxCode !== '') {
                $method = $hasCondition
                    ? 'orWhereRaw'
                    : 'whereRaw';

                $subQuery->{$method}(
                    "REPLACE(billing_tax_code, ' ', '') = ?",
                    [$taxCode]
                );
            }
        });

        $matches = $query
            ->with([
                'assignedUser:id,name',
            ])
            ->limit(8)
            ->get([
                'id',
                'name',
                'phone',
                'email',
                'billing_tax_code',
                'owner_id',
            ])
            ->map(
                fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'tax_code' =>
                        $customer->billing_tax_code,
                    'owner' =>
                        $customer->assignedUser?->name,
                    'url' => route(
                        'customers.show',
                        $customer
                    ),
                ]
            )
            ->values();

        return response()->json([
            'matches' => $matches,
        ]);
    }

    /**
     * Search customers.
     */
    public function search()
    {
        $filters = request()->only([
            'search',
            'region_id',
            'customer_type_id',
            'customer_status',
            'owner_id',
            'is_potential',
        ]);

        $query = Customer::query();
        $user = auth()->user();

        if ($user->hasRole('sales')) {
            $query->where('owner_id', $user->id);
        } elseif (! empty($filters['owner_id'])) {
            $query->where(
                'owner_id',
                $filters['owner_id']
            );
        }

        if (! empty($filters['search'])) {
            $query->where(function ($subQuery) use (
                $filters
            ): void {
                $keyword = '%'.$filters['search'].'%';

                $subQuery
                    ->where('name', 'like', $keyword)
                    ->orWhere('phone', 'like', $keyword)
                    ->orWhere('email', 'like', $keyword);
            });
        }

        return $query->paginate(20);
    }

    public function invoiceInfo(
        Customer $customer
    ): JsonResponse {
        return response()->json([
            'id' => $customer->id,
            'billing_company_name' =>
                $customer->billing_company_name,
            'billing_tax_code' =>
                $customer->billing_tax_code,
            'billing_address' =>
                $customer->billing_address,
            'billing_email' =>
                $customer->billing_email,
        ]);
    }

    public function updateBillingInfo(
        Request $request,
        Customer $customer
    ): JsonResponse {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'billing_company_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'billing_tax_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'billing_address' => [
                'nullable',
                'string',
                'max:500',
            ],
            'billing_email' => [
                'nullable',
                'email',
                'max:255',
            ],
        ]);

        $customer->update($validated);

        return response()->json([
            'message' =>
                'Lưu thông tin hóa đơn thành công.',
            'data' => [
                'id' => $customer->id,
                'billing_company_name' =>
                    $customer->billing_company_name,
                'billing_tax_code' =>
                    $customer->billing_tax_code,
                'billing_address' =>
                    $customer->billing_address,
                'billing_email' =>
                    $customer->billing_email,
            ],
        ]);
    }

    /**
     * EGO_CUSTOMER_HANDOVER_HELPERS_V32
     */
    private function canHandoverCustomer(
        Customer $customer,
        ?User $user
    ): bool {
        if ($user === null) {
            return false;
        }

        if (
            (int) $customer->owner_id > 0
            && (int) $customer->owner_id
                === (int) $user->id
        ) {
            return true;
        }

        try {
            if (
                method_exists($user, 'hasAnyRole')
                && $user->hasAnyRole([
                    'admin',
                    'management',
                    'manager',
                    'sales_manager',
                    'department_manager',
                ])
            ) {
                return true;
            }
        } catch (\Throwable) {
            // Tiếp tục kiểm tra permission.
        }

        try {
            if (
                method_exists($user, 'can')
                && (
                    $user->can('customer.handover')
                    || $user->can('customer.update_all')
                    || $user->can('customer.view_all')
                )
            ) {
                return true;
            }
        } catch (\Throwable) {
            // Permission chưa tồn tại.
        }

        return false;
    }

    /**
     * @return Collection<int, User>
     */
    private function eligibleHandoverUsers(
        Customer $customer
    ): Collection {
        $query = User::query()
            ->orderBy('name');

        if ((int) $customer->owner_id > 0) {
            $query->where(
                'id',
                '!=',
                (int) $customer->owner_id
            );
        }

        if (
            Schema::hasColumn(
                'users',
                'is_active'
            )
        ) {
            $query->where('is_active', true);
        }

        if (
            Schema::hasColumn(
                'users',
                'status'
            )
        ) {
            $query->where(function ($status): void {
                $status
                    ->whereNull('status')
                    ->orWhereIn('status', [
                        'active',
                        'working',
                        'enabled',
                    ]);
            });
        }

        return $query
            ->get([
                'id',
                'name',
                'email',
            ]);
    }

    /**
     * Quyền xem toàn bộ dữ liệu chăm sóc trong màn Khách hàng.
     */
    private function canViewAllPipeline(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->can('customer.view_all') || $user->can('customer.view_sales_all')) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole([
                'admin',
                'management',
                'sales_manager',
                'accounting',
            ]);
        }

        if (method_exists($user, 'hasRole')) {
            foreach (['admin', 'management', 'sales_manager', 'accounting'] as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Thống kê tổng theo phạm vi khách mà user được xem.
     *
     * @return array<string, int>
     */
    private function customerStats(
        Request $request
    ): array {
        $base = Customer::query()
            ->visibleToUser($request->user());

        return [
            'total' => (clone $base)->count(),
            'lead' => (clone $base)
                ->where('customer_status', 'lead')
                ->count(),
            'member' => (clone $base)
                ->where('customer_status', 'member')
                ->count(),
            'potential' => (clone $base)
                ->where('is_potential', true)
                ->count(),
        ];
    }

    /**
     * Bổ sung dữ liệu chăm sóc cho 20 khách trên trang hiện tại
     * bằng truy vấn nhóm, không tạo N+1 query.
     */
    private function decorateCustomerPage(
        mixed $customers
    ): void {
        $items = method_exists(
            $customers,
            'getCollection'
        )
            ? $customers->getCollection()
            : collect($customers);

        if ($items->isEmpty()) {
            return;
        }

        $customerIds = $items
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values();

        $latestInteractions = collect();

        if (Schema::hasTable('crm_customer_interactions')) {
            $latestInteractions = DB::table(
                'crm_customer_interactions as interactions'
            )
                ->leftJoin(
                    'users as creators',
                    'creators.id',
                    '=',
                    'interactions.created_by'
                )
                ->whereIn(
                    'interactions.customer_id',
                    $customerIds
                )
                ->orderByDesc(
                    'interactions.interaction_date'
                )
                ->orderByDesc('interactions.id')
                ->select([
                    'interactions.customer_id',
                    'interactions.interaction_type',
                    'interactions.subject',
                    'interactions.interaction_date',
                    'interactions.next_followup_date',
                    'creators.name as creator_name',
                ])
                ->get()
                ->groupBy('customer_id')
                ->map(
                    fn (Collection $group) =>
                        $group->first()
                );
        }

        $orderCounts = collect();

        if (
            Schema::hasTable('crm_orders')
            && Schema::hasTable('crm_leads')
        ) {
            $orderCounts = DB::table('crm_orders as orders')
                ->join(
                    'crm_leads as leads',
                    'leads.id',
                    '=',
                    'orders.lead_id'
                )
                ->whereIn(
                    'leads.customer_id',
                    $customerIds
                )
                ->whereNull('orders.deleted_at')
                ->groupBy('leads.customer_id')
                ->selectRaw(
                    'leads.customer_id, COUNT(orders.id) AS total'
                )
                ->pluck('total', 'customer_id');
        }

        $phones = $items
            ->pluck('phone')
            ->filter()
            ->unique()
            ->values();

        $phoneCounts = collect();

        if ($phones->isNotEmpty()) {
            $phoneCounts = DB::table('crm_customers')
                ->whereIn('phone', $phones)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->groupBy('phone')
                ->selectRaw(
                    'phone, COUNT(*) AS total'
                )
                ->pluck('total', 'phone');
        }

        $now = now();

        $items->each(function (Customer $customer) use (
            $latestInteractions,
            $orderCounts,
            $phoneCounts,
            $now
        ): void {
            $interaction = $latestInteractions->get(
                $customer->id
            );

            $customer->setAttribute(
                '_care_last_at',
                $interaction?->interaction_date
            );

            $customer->setAttribute(
                '_care_type',
                $interaction?->interaction_type
            );

            $customer->setAttribute(
                '_care_subject',
                $interaction?->subject
            );

            $customer->setAttribute(
                '_care_creator',
                $interaction?->creator_name
            );

            $customer->setAttribute(
                '_next_followup_at',
                $interaction?->next_followup_date
            );

            $careState = 'none';

            if (! empty($interaction?->next_followup_date)) {
                $followup = Carbon::parse(
                    $interaction->next_followup_date
                );

                if ($followup->isPast()) {
                    $careState = 'overdue';
                } elseif ($followup->isToday()) {
                    $careState = 'today';
                } else {
                    $careState = 'planned';
                }
            }

            $customer->setAttribute(
                '_care_state',
                $careState
            );

            $customer->setAttribute(
                '_order_count',
                (int) ($orderCounts->get(
                    $customer->id
                ) ?? 0)
            );

            $customer->setAttribute(
                '_duplicate_phone_count',
                (int) ($phoneCounts->get(
                    $customer->phone
                ) ?? 0)
            );
        });

        if (method_exists($customers, 'setCollection')) {
            $customers->setCollection($items);
        }
    }
}
