<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\SalesWorkReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller báo cáo công việc Sales: CRUD, thống kê, xuất CSV.
 */
class SalesWorkReportController extends Controller
{
    /**
     * Hiển thị danh sách báo cáo công việc Sales kèm bộ lọc và thống kê.
     */
    public function index(Request $request)
    {
        $reports = $this->baseQuery($request)
            ->orderByDesc('r.updated_at')
            ->paginate((int) request('per_page', 500))
            ->withQueryString();

        return view('sales.work_reports.index', [
            'reports' => $reports,
            'stats' => $this->stats(),
            'salesUsers' => $this->salesUsers(),
            'sources' => $this->sources(),
            'statuses' => SalesWorkReport::STATUSES,
            'priorities' => SalesWorkReport::PRIORITIES,
            'channels' => SalesWorkReport::CHANNELS,
            'outcomes' => SalesWorkReport::OUTCOMES,
            'customerTypes' => $this->customerTypes(),
            'customerStages' => $this->customerStages(),
            'callResults' => $this->callResults(),
            'quoteStatuses' => $this->quoteStatuses(),
            'canManage' => $this->canManage(),
        ]);
    }

    /**
     * Hiển thị form tạo báo cáo mới.
     */
    public function create()
    {
        return view('sales.work_reports.create', $this->formData(new SalesWorkReport));
    }

    /**
     * Lưu báo cáo công việc Sales mới.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $userId = (int) Auth::id();

        $data['assigned_to'] = $this->canManage()
            ? (int) ($data['assigned_to'] ?? $userId)
            : $userId;

        // Đồng bộ hồ sơ khách hàng gốc. Chỉ cập nhật khách/lead mới khi cần,
        // tuyệt đối không sửa crm_orders hoặc lead cũ đã có.
        $data['customer_id'] = $this->syncCustomerForReport($data);

        $data['created_by'] = $userId;
        $data['last_contact_at'] = $data['last_contact_at'] ?? $data['call_2_at'] ?? $data['call_1_at'] ?? $data['first_call_at'] ?? now();
        $data['proof_links'] = $this->linksJson($data['proof_links'] ?? null);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('sales_work_reports')->insertGetId($data);

        return redirect()
            ->route('customers.pipeline')
            ->with('success', 'Đã lưu dữ liệu chăm sóc khách hàng.');
    }

    /**
     * Hiển thị chi tiết báo cáo kèm lịch sử cùng khách hàng.
     */
    public function show(int $id)
    {
        $report = $this->baseQuery(new Request)->where('r.id', $id)->firstOrFail();
        $this->authorizeReport($report);

        $history = $this->baseQuery(new Request)
            ->where(function ($q) use ($report) {
                if (! empty($report->customer_phone)) {
                    $q->where('r.customer_phone', $report->customer_phone);
                } else {
                    $q->where('r.customer_name', $report->customer_name);
                }
            })
            ->orderByDesc('r.created_at')
            ->limit(20)
            ->get();

        return view('sales.work_reports.show', [
            'report' => $report,
            'history' => $history,
            'statuses' => SalesWorkReport::STATUSES,
            'priorities' => SalesWorkReport::PRIORITIES,
            'channels' => SalesWorkReport::CHANNELS,
            'outcomes' => SalesWorkReport::OUTCOMES,
            'customerTypes' => $this->customerTypes(),
            'customerStages' => $this->customerStages(),
            'callResults' => $this->callResults(),
            'quoteStatuses' => $this->quoteStatuses(),
            'canManage' => $this->canManage(),
        ]);
    }

    /**
     * Hiển thị form sửa báo cáo.
     */
    public function edit(int $id)
    {
        $report = SalesWorkReport::findOrFail($id);
        $this->authorizeReport($report);

        return view('sales.work_reports.edit', $this->formData($report));
    }

    /**
     * Cập nhật báo cáo công việc Sales.
     */
    public function update(Request $request, int $id)
    {
        $report = SalesWorkReport::findOrFail($id);
        $this->authorizeReport($report);

        $data = $this->validated($request);

        if (! $this->canManage()) {
            unset($data['assigned_to']);
        }

        $syncData = $data;
        $syncData['assigned_to'] = $data['assigned_to'] ?? $report->assigned_to ?? Auth::id();
        $syncData['customer_id'] = $report->customer_id ?? null;
        $data['customer_id'] = $this->syncCustomerForReport($syncData);

        $data['last_contact_at'] = $data['last_contact_at'] ?? $data['call_2_at'] ?? $data['call_1_at'] ?? $data['first_call_at'] ?? $report->last_contact_at ?? now();
        $data['proof_links'] = $this->linksJson($data['proof_links'] ?? null);
        $data['updated_at'] = now();

        DB::table('sales_work_reports')->where('id', $report->id)->update($data);

        return redirect()
            ->route('customers.pipeline')
            ->with('success', 'Đã cập nhật chăm sóc khách hàng.');
    }

    /**
     * Quản lý lưu ghi chú và xác nhận báo cáo.
     */
    public function approve(Request $request, int $id)
    {
        abort_unless($this->canManage(), 403);

        SalesWorkReport::where('id', $id)->update([
            'manager_note' => $request->input('manager_note'),
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã lưu ghi chú quản lý.');
    }

    /**
     * Xóa báo cáo (quản lý hoặc người tạo).
     */
    public function destroy(int $id)
    {
        $report = SalesWorkReport::findOrFail($id);
        abort_unless($this->canManage() || (int) $report->created_by === (int) Auth::id(), 403);

        $report->delete();

        return redirect()
            ->route('customers.pipeline')
            ->with('success', 'Đã xoá dữ liệu chăm sóc.');
    }

    /**
     * Xuất danh sách báo cáo theo bộ lọc ra file CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $rows = $this->baseQuery($request)->orderByDesc('r.updated_at')->get();
        $fileName = 'bao-cao-cong-viec-sales-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'Ngày nhận data',
                'Sales',
                'Khách hàng',
                'Loại khách',
                'Trạng thái khách',
                'SĐT',
                'Email',
                'Nguồn',
                'Gọi lần 1',
                'KQ gọi lần 1',
                'Gọi lần 2',
                'KQ gọi lần 2',
                'Báo giá',
                'Ngày gửi báo giá',
                'Trạng thái',
                'Ưu tiên',
                'Nhu cầu',
                'Follow-up',
                'Doanh thu kỳ vọng',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->data_received_at,
                    $row->sales_name,
                    $row->customer_name,
                    $this->customerTypes()[$row->customer_type] ?? $row->customer_type,
                    $this->customerStages()[$row->customer_stage] ?? $row->customer_stage,
                    $row->customer_phone,
                    $row->customer_email,
                    $row->source_name,
                    $row->call_1_at,
                    $this->callResults()[$row->call_1_result] ?? $row->call_1_result,
                    $row->call_2_at,
                    $this->callResults()[$row->call_2_result] ?? $row->call_2_result,
                    $this->quoteStatuses()[$row->quote_status] ?? $row->quote_status,
                    $row->quote_sent_at,
                    SalesWorkReport::STATUSES[$row->status] ?? $row->status,
                    SalesWorkReport::PRIORITIES[$row->priority] ?? $row->priority,
                    $row->customer_need,
                    $row->next_followup_at,
                    $row->revenue_expectation,
                ]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Dựng query gốc join nguồn data, người phụ trách và áp dụng bộ lọc.
     */
    private function baseQuery(Request $request)
    {
        $select = ['r.*'];
        $q = DB::table('sales_work_reports as r');

        if (Schema::hasTable('crm_sources')) {
            $q->leftJoin('crm_sources as s', 's.id', '=', 'r.data_source_id');
            $select[] = 's.name as source_name';
        } else {
            $select[] = DB::raw('NULL as source_name');
        }

        if (Schema::hasTable('users')) {
            $q->leftJoin('users as u', 'u.id', '=', 'r.assigned_to');
            $q->leftJoin('users as creator', 'creator.id', '=', 'r.created_by');
            $select[] = 'u.name as sales_name';
            $select[] = 'creator.name as creator_name';
        } else {
            $select[] = DB::raw('NULL as sales_name');
            $select[] = DB::raw('NULL as creator_name');
        }

        $q->select($select);

        if (! $this->canManage()) {
            $q->where('r.assigned_to', Auth::id());
        }

        if ($request->filled('customer_type')) {
            $q->where('r.customer_type', $request->input('customer_type'));
        }

        if ($request->filled('status')) {
            $q->where('r.status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $q->where('r.priority', $request->input('priority'));
        }

        if ($request->filled('assigned_to') && $this->canManage()) {
            $q->where('r.assigned_to', (int) $request->input('assigned_to'));
        }

        if ($request->filled('from')) {
            $q->whereDate('r.data_received_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $q->whereDate('r.data_received_at', '<=', $request->input('to'));
        }

        if (! $request->filled('from') && ! $request->filled('to') && $request->filled('period')) {
            if ($request->input('period') === 'today') {
                $q->whereBetween('r.created_at', [now()->startOfDay(), now()->endOfDay()]);
            } elseif ($request->input('period') === 'week') {
                $q->whereBetween('r.created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($request->input('period') === 'month') {
                $q->whereBetween('r.created_at', [now()->startOfMonth(), now()->endOfMonth()]);
            }
        }

        if ($request->filled('quick')) {
            if ($request->input('quick') === 'hot') {
                $q->where('r.priority', 'hot')->whereNotIn('r.status', ['won', 'lost', 'invalid']);
            } elseif ($request->input('quick') === 'need_follow') {
                $q->whereIn('r.status', ['follow_up', 'consulting', 'quoted'])
                    ->whereNotNull('r.next_followup_at')
                    ->where('r.next_followup_at', '<=', now()->addDay());
            } elseif ($request->input('quick') === 'quote_sent') {
                $q->where('r.quote_status', 'sent');
            } elseif ($request->input('quick') === 'quote_pending') {
                $q->where(function ($sub) {
                    $sub->whereNull('r.quote_status')->orWhere('r.quote_status', 'not_sent');
                });
            } elseif ($request->input('quick') === 'no_answer') {
                $q->where(function ($sub) {
                    $sub->where('r.call_1_result', 'no_answer')->orWhere('r.call_2_result', 'no_answer');
                });
            }
        }

        if ($request->filled('q')) {
            $keyword = '%'.trim($request->input('q')).'%';
            $q->where(function ($sub) use ($keyword) {
                $sub->where('r.customer_name', 'like', $keyword)
                    ->orWhere('r.customer_phone', 'like', $keyword)
                    ->orWhere('r.customer_email', 'like', $keyword)
                    ->orWhere('r.customer_company', 'like', $keyword)
                    ->orWhere('r.customer_need', 'like', $keyword)
                    ->orWhere('r.consultation_summary', 'like', $keyword)
                    ->orWhere('r.customer_feedback', 'like', $keyword)
                    ->orWhere('r.quoted_products', 'like', $keyword)
                    ->orWhere('r.region_text', 'like', $keyword);
            });
        }

        return $q;
    }

    /**
     * Tính số liệu thống kê báo cáo theo ngày/tuần/tháng và trạng thái.
     */
    private function stats(): array
    {
        $base = DB::table('sales_work_reports');

        if (! $this->canManage()) {
            $base->where('assigned_to', Auth::id());
        }

        $todayBase = (clone $base)->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]);
        $weekBase = (clone $base)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        $monthBase = (clone $base)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        $won = (clone $base)->where('status', 'won')->count();
        $lost = (clone $base)->where('status', 'lost')->count();

        return [
            'today' => (clone $todayBase)->count(),
            'week' => (clone $weekBase)->count(),
            'month' => (clone $monthBase)->count(),
            'need_follow' => (clone $base)
                ->whereIn('status', ['follow_up', 'consulting', 'quoted'])
                ->whereNotNull('next_followup_at')
                ->where('next_followup_at', '<=', now()->addDay())
                ->count(),
            'hot' => (clone $base)
                ->where('priority', 'hot')
                ->whereNotIn('status', ['won', 'lost', 'invalid'])
                ->count(),
            'won' => $won,
            'quote_sent' => (clone $base)->where('quote_status', 'sent')->count(),
            'quote_pending' => (clone $base)->where(function ($q) {
                $q->whereNull('quote_status')->orWhere('quote_status', 'not_sent');
            })->count(),
            'no_answer' => (clone $base)->where(function ($q) {
                $q->where('call_1_result', 'no_answer')->orWhere('call_2_result', 'no_answer');
            })->count(),
            'win_rate' => ($won + $lost) > 0 ? round(($won * 100) / ($won + $lost), 1) : 0,
        ];
    }

    /**
     * Validate dữ liệu báo cáo từ request.
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'assigned_to' => ['nullable', 'integer'],
            'data_source_id' => ['nullable', 'integer'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_company' => ['nullable', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string'],
            'facebook_name' => ['nullable', 'string', 'max:255'],
            'facebook_link' => ['nullable', 'string', 'max:511'],
            'zalo_id' => ['nullable', 'string', 'max:255'],
            'customer_type' => ['required', 'string', 'max:30'],
            'customer_stage' => ['nullable', 'string', 'max:40'],
            'region_text' => ['nullable', 'string', 'max:255'],
            'data_received_at' => ['nullable', 'date'],
            'first_call_at' => ['nullable', 'date'],
            'call_1_at' => ['nullable', 'date'],
            'call_1_result' => ['nullable', 'string', 'max:30'],
            'call_2_at' => ['nullable', 'date'],
            'call_2_result' => ['nullable', 'string', 'max:30'],
            'last_contact_at' => ['nullable', 'date'],
            'contact_channel' => ['required', 'string', 'max:30'],
            'customer_need' => ['nullable', 'string'],
            'system_size_kw' => ['nullable', 'numeric', 'min:0'],
            'budget_range' => ['nullable', 'string', 'max:255'],
            'project_timeline' => ['nullable', 'string', 'max:255'],
            'consultation_summary' => ['nullable', 'string'],
            'quoted_products' => ['nullable', 'string'],
            'quote_status' => ['nullable', 'string', 'max:30'],
            'quote_sent_at' => ['nullable', 'date'],
            'customer_feedback' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:30'],
            'priority' => ['required', 'string', 'max:20'],
            'outcome' => ['nullable', 'string', 'max:30'],
            'next_followup_at' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'revenue_expectation' => ['nullable', 'numeric', 'min:0'],
            'lost_reason' => ['nullable', 'string'],
            'proof_links' => ['nullable', 'string'],
        ]);
    }

    /**
     * Chuẩn bị dữ liệu chung cho form tạo/sửa báo cáo.
     */
    private function formData(SalesWorkReport $report): array
    {
        return [
            'report' => $report,
            'sources' => $this->sources(),
            'salesUsers' => $this->salesUsers(),
            'statuses' => SalesWorkReport::STATUSES,
            'priorities' => SalesWorkReport::PRIORITIES,
            'channels' => SalesWorkReport::CHANNELS,
            'outcomes' => SalesWorkReport::OUTCOMES,
            'customerTypes' => $this->customerTypes(),
            'customerStages' => $this->customerStages(),
            'callResults' => $this->callResults(),
            'quoteStatuses' => $this->quoteStatuses(),
            'canManage' => $this->canManage(),
        ];
    }

    /**
     * Đồng bộ một dòng chăm sóc vào hồ sơ khách hàng gốc.
     *
     * Nguyên tắc an toàn đơn hàng:
     * - Không UPDATE crm_orders.
     * - Không đổi assigned_to của lead cũ.
     * - Khách mới tạo từ data chăm sóc mới có một lead mới để sau này tạo đơn được.
     */
    private function syncCustomerForReport(array $data): ?int
    {
        if (! Schema::hasTable('crm_customers')) {
            return isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        }

        $assignedTo = (int) ($data['assigned_to'] ?? Auth::id() ?? 0);
        $customerId = (int) ($data['customer_id'] ?? 0);

        $phoneKey = $this->normalizePhone($data['customer_phone'] ?? null);
        $emailKey = mb_strtolower(trim((string) ($data['customer_email'] ?? '')));

        $customer = null;

        if ($customerId > 0) {
            $customer = DB::table('crm_customers')->where('id', $customerId)->first();
        }

        if (! $customer && ($phoneKey !== '' || $emailKey !== '')) {
            $candidates = DB::table('crm_customers')
                ->select(['id', 'name', 'phone', 'email', 'address', 'owner_id'])
                ->when($emailKey !== '', function ($q) use ($emailKey) {
                    $q->whereRaw("LOWER(TRIM(COALESCE(email, ''))) = ?", [$emailKey]);
                })
                ->limit(50)
                ->get();

            if ($phoneKey !== '') {
                $phoneMatches = DB::table('crm_customers')
                    ->select(['id', 'name', 'phone', 'email', 'address', 'owner_id'])
                    ->whereNotNull('phone')
                    ->get()
                    ->filter(fn ($row) => $this->normalizePhone($row->phone ?? null) === $phoneKey);

                if ($phoneMatches->count() === 1) {
                    $customer = $phoneMatches->first();
                }
            }

            if (! $customer && $emailKey !== '' && $candidates->count() === 1) {
                $customer = $candidates->first();
            }
        }

        if ($customer) {
            $updates = [];

            if ($assignedTo > 0 && Schema::hasColumn('crm_customers', 'owner_id')) {
                $updates['owner_id'] = $assignedTo;
            }

            if (empty($customer->phone) && ! empty($data['customer_phone']) && Schema::hasColumn('crm_customers', 'phone')) {
                $updates['phone'] = $data['customer_phone'];
            }

            if (empty($customer->email) && ! empty($data['customer_email']) && Schema::hasColumn('crm_customers', 'email')) {
                $updates['email'] = $data['customer_email'];
            }

            if (empty($customer->address) && ! empty($data['customer_address']) && Schema::hasColumn('crm_customers', 'address')) {
                $updates['address'] = $data['customer_address'];
            }

            if ($updates) {
                if (Schema::hasColumn('crm_customers', 'updated_at')) {
                    $updates['updated_at'] = now();
                }
                DB::table('crm_customers')->where('id', $customer->id)->update($updates);
            }

            return (int) $customer->id;
        }

        $columns = Schema::getColumnListing('crm_customers');
        $insert = [];

        $put = static function (array &$target, array $columns, string $key, mixed $value): void {
            if (in_array($key, $columns, true)) {
                $target[$key] = $value;
            }
        };

        $put($insert, $columns, 'name', trim((string) ($data['customer_name'] ?? '')) ?: 'Khách hàng mới');
        $put($insert, $columns, 'phone', $data['customer_phone'] ?? null);
        $put($insert, $columns, 'email', $data['customer_email'] ?? null);
        $put($insert, $columns, 'address', $data['customer_address'] ?? ($data['region_text'] ?? null));
        $put($insert, $columns, 'customer_status', 'lead');
        $put($insert, $columns, 'is_potential', ($data['priority'] ?? null) === 'hot' ? 1 : 0);
        $put($insert, $columns, 'owner_id', $assignedTo > 0 ? $assignedTo : null);
        $put($insert, $columns, 'company_id', $this->currentCompanyId($data['company_id'] ?? null));
        $put($insert, $columns, 'created_at', now());
        $put($insert, $columns, 'updated_at', now());

        $newCustomerId = (int) DB::table('crm_customers')->insertGetId($insert);

        // Chỉ tạo lead cho khách vừa sinh mới; không sửa lead lịch sử của khách đã tồn tại.
        if (Schema::hasTable('crm_leads')) {
            $leadColumns = Schema::getColumnListing('crm_leads');
            $lead = [];
            $put($lead, $leadColumns, 'customer_id', $newCustomerId);
            $put($lead, $leadColumns, 'assigned_to', $assignedTo > 0 ? $assignedTo : null);
            $put($lead, $leadColumns, 'contact_date', now()->toDateString());
            $put($lead, $leadColumns, 'status_id', 1);
            $put($lead, $leadColumns, 'note', 'Tạo tự động từ Chăm sóc & Pipeline');
            $put($lead, $leadColumns, 'created_by', Auth::id());
            $put($lead, $leadColumns, 'created_at', now());
            $put($lead, $leadColumns, 'updated_at', now());
            DB::table('crm_leads')->insert($lead);
        }

        return $newCustomerId;
    }

    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if (str_starts_with($digits, '84') && strlen($digits) >= 10) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    private function currentCompanyId(mixed $fallback = null): mixed
    {
        if (! empty($fallback)) {
            return $fallback;
        }

        try {
            if (class_exists(\App\Support\EgoCompanyLock::class)) {
                return \App\Support\EgoCompanyLock::id();
            }
        } catch (\Throwable $e) {
            // CLI hoặc context chưa chọn công ty: dùng fallback bên dưới.
        }

        if (Schema::hasColumn('crm_customers', 'company_id')) {
            return DB::table('crm_customers')
                ->whereNotNull('company_id')
                ->select('company_id', DB::raw('COUNT(*) as total'))
                ->groupBy('company_id')
                ->orderByDesc('total')
                ->value('company_id');
        }

        return null;
    }

    /**
     * Danh sách loại khách hàng.
     */
    private function customerTypes(): array
    {
        return [
            'dealer' => 'Đại lý',
            'retail' => 'Mua lẻ',
            'turnkey' => 'Lắp đặt trọn gói',
        ];
    }

    /**
     * Danh sách trạng thái chăm sóc khách hàng.
     */
    private function customerStages(): array
    {
        return [
            'new_need_confirm' => 'Mới - cần xác nhận',
            'interested' => 'Quan tâm',
            'hot_need_quote' => 'Nóng - cần báo giá',
            'quoted_waiting' => 'Đã báo giá - chờ phản hồi',
            'comparing_price' => 'Đang so sánh giá',
            'need_follow' => 'Cần chăm sóc lại',
            'unreachable' => 'Chưa liên hệ được',
            'not_interested' => 'Không quan tâm',
            'closed_won' => 'Đã chốt',
            'closed_lost' => 'Đã mất',
        ];
    }

    /**
     * Danh sách kết quả cuộc gọi.
     */
    private function callResults(): array
    {
        return [
            'not_called' => 'Chưa gọi',
            'answered' => 'Nghe máy',
            'no_answer' => 'Không nghe',
            'busy' => 'Máy bận',
            'call_back' => 'Hẹn gọi lại',
        ];
    }

    /**
     * Danh sách trạng thái báo giá.
     */
    private function quoteStatuses(): array
    {
        return [
            'not_sent' => 'Chưa gửi',
            'sent' => 'Đã gửi',
            'viewed' => 'Khách đã xem',
            'waiting' => 'Đang chờ phản hồi',
        ];
    }

    /**
     * Lấy danh sách nguồn data từ bảng crm_sources.
     */
    private function sources()
    {
        if (! Schema::hasTable('crm_sources')) {
            return collect();
        }

        return DB::table('crm_sources')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Lấy danh sách nhân viên có role sales theo nhiều kiểu phân quyền.
     */
    private function salesUsers()
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        $q = DB::table('users')->select('users.id', 'users.name', 'users.email');

        if (Schema::hasColumn('users', 'is_active')) {
            $q->where('users.is_active', 1);
        }

        /*
         * Chỉ lấy nhân viên role sales/sale.
         * Hỗ trợ nhiều kiểu phân quyền:
         * 1. Spatie permission: roles + model_has_roles
         * 2. role_user pivot
         * 3. users.role hoặc users.role_name
         */

        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $q->join('model_has_roles as mhr', function ($join) {
                $join->on('mhr.model_id', '=', 'users.id')
                    ->where(function ($sub) {
                        $sub->where('mhr.model_type', 'App\Models\User')
                            ->orWhere('mhr.model_type', 'App\User')
                            ->orWhere('mhr.model_type', 'like', '%User');
                    });
            })
                ->join('roles', 'roles.id', '=', 'mhr.role_id')
                ->whereIn('roles.name', ['sales', 'sale'])
                ->distinct();

            return $q->orderBy('users.name')->get();
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
            $q->join('role_user', 'role_user.user_id', '=', 'users.id')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->whereIn('roles.name', ['sales', 'sale'])
                ->distinct();

            return $q->orderBy('users.name')->get();
        }

        if (Schema::hasColumn('users', 'role')) {
            $q->whereIn('users.role', ['sales', 'sale']);

            return $q->orderBy('users.name')->get();
        }

        if (Schema::hasColumn('users', 'role_name')) {
            $q->whereIn('users.role_name', ['sales', 'sale']);

            return $q->orderBy('users.name')->get();
        }

        // Nếu hệ thống không có bảng/cột role rõ ràng thì trả rỗng để tránh hiện sai phòng ban.
        return collect();
    }

    /**
     * Chuyển danh sách link (mỗi dòng một link) thành chuỗi JSON.
     */
    private function linksJson(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $links = collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        return $links
            ? json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
    }

    /**
     * Kiểm tra người dùng có quyền quản lý báo cáo.
     */
    private function canManage(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['admin', 'sales_manager', 'accounting']);
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin') || $user->hasRole('sales_manager') || $user->hasRole('accounting');
        }

        if (isset($user->role)) {
            return in_array($user->role, ['admin', 'sales_manager', 'accounting'], true);
        }

        return false;
    }

    /**
     * Chặn 403 nếu không phải quản lý hoặc người được giao báo cáo.
     */
    private function authorizeReport(object $report): void
    {
        abort_if(! $this->canManage() && (int) $report->assigned_to !== (int) Auth::id(), 403);
    }
}
