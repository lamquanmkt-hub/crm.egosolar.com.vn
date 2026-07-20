<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payments\PaymentRequest;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Controller quản lý phiếu đề nghị thanh toán: CRUD, duyệt và xuất Excel/PDF.
 */
class PaymentRequestController extends Controller
{
    /* =========================
     * Helpers: role check
     * ========================= */
    /**
     * Kiểm tra người dùng có phải Admin (role column / is_admin / spatie).
     */
    private function isAdmin($user): bool
    {
        return (method_exists($user, 'hasRole') && $user->hasRole('admin'))
            || (($user->role ?? null) === 'admin')
            || ((int) ($user->is_admin ?? 0) === 1);
    }

    /**
     * Kiểm tra người dùng có phải Kế toán.
     */
    private function isAccounting($user): bool
    {
        if (! $user) {
            return false;
        }

        $roles = ['accounting', 'ketoan', 'ke_toan'];

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        $rawRole = strtolower((string) ($user->role ?? ''));
        $email = strtolower((string) ($user->email ?? ''));

        return in_array($rawRole, $roles, true) || str_contains($email, 'ketoan');
    }

    /**
     * Danh sách công ty dùng cho form.
     */
    private function companyOptions(): array
    {
        return [
            'Công ty TNHH Ego Việt Nam',
            'Công ty TNHH TMKT Quốc Tế EGO',
        ];
    }

    /**
     * Bản đồ trạng thái phiếu sang nhãn tiếng Việt.
     */
    private function statusLabels(): array
    {
        return [
            'draft' => 'Nháp',
            'submitted' => 'Đã gửi duyệt',
            'admin_approved' => 'Quản lý tài chính đã duyệt',
            'admin_rejected' => 'Quản lý tài chính từ chối',
            'accounting_approved' => 'Kế toán đã chi',
            'accounting_rejected' => 'Kế toán từ chối',
        ];
    }

    /**
     * Lấy nhãn tiếng Việt của một trạng thái phiếu.
     */
    private function statusLabel(?string $status): string
    {
        $map = $this->statusLabels();

        return $map[$status ?? ''] ?? ($status ?? '-');
    }

    /* =========================
     * Date filter
     * ========================= */
    /**
     * Xác định khoảng ngày lọc theo preset hoặc ngày tùy chỉnh.
     *
     * @return array [preset, dateFrom, dateTo]
     */
    private function resolveDateFilter(Request $request): array
    {
        $preset = trim((string) $request->input('date_preset', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));
        $manualDate = $request->boolean('date_filter_manual');

        $dateFrom = $dateFrom !== '' ? $dateFrom : null;
        $dateTo = $dateTo !== '' ? $dateTo : null;

        /*
         * Khi người dùng tự chọn Từ ngày / Đến ngày,
         * phải ưu tiên ngày nhập và không để "Tháng này" ghi đè.
         */
        if (
            $manualDate
            || $preset === 'custom'
            || ($preset === '' && ($dateFrom || $dateTo))
        ) {
            return ['custom', $dateFrom, $dateTo];
        }

        if ($preset === '') {
            $preset = 'this_month';
        }

        /*
         * Tương thích giao diện cũ:
         * Nếu dropdown vẫn là "Tháng này" nhưng ngày gửi lên
         * không phải phạm vi tháng hiện tại thì hiểu là lọc tùy chỉnh.
         */
        if ($preset === 'this_month' && ($dateFrom || $dateTo)) {
            $thisMonthFrom = now()->copy()->startOfMonth()->toDateString();
            $thisMonthTo = now()->copy()->endOfMonth()->toDateString();

            if (
                $dateFrom !== $thisMonthFrom
                || $dateTo !== $thisMonthTo
            ) {
                return ['custom', $dateFrom, $dateTo];
            }
        }

        switch ($preset) {
            case 'today':
                $dateFrom = now()->toDateString();
                $dateTo = now()->toDateString();
                break;

            case 'this_week':
                $dateFrom = now()
                    ->copy()
                    ->startOfWeek(Carbon::MONDAY)
                    ->toDateString();

                $dateTo = now()
                    ->copy()
                    ->endOfWeek(Carbon::SUNDAY)
                    ->toDateString();
                break;

            case 'last_week':
                $dateFrom = now()
                    ->copy()
                    ->subWeek()
                    ->startOfWeek(Carbon::MONDAY)
                    ->toDateString();

                $dateTo = now()
                    ->copy()
                    ->subWeek()
                    ->endOfWeek(Carbon::SUNDAY)
                    ->toDateString();
                break;

            case 'last_month':
                $dateFrom = now()
                    ->copy()
                    ->subMonthNoOverflow()
                    ->startOfMonth()
                    ->toDateString();

                $dateTo = now()
                    ->copy()
                    ->subMonthNoOverflow()
                    ->endOfMonth()
                    ->toDateString();
                break;

            case 'this_year':
                $dateFrom = now()
                    ->copy()
                    ->startOfYear()
                    ->toDateString();

                $dateTo = now()
                    ->copy()
                    ->endOfYear()
                    ->toDateString();
                break;

            case 'all_time':
                $dateFrom = null;
                $dateTo = null;
                break;

            case 'custom':
                break;

            case 'this_month':
            default:
                $preset = 'this_month';

                $dateFrom = now()
                    ->copy()
                    ->startOfMonth()
                    ->toDateString();

                $dateTo = now()
                    ->copy()
                    ->endOfMonth()
                    ->toDateString();
                break;
        }

        return [$preset, $dateFrom, $dateTo];
    }

    /**
     * Áp dụng các bộ lọc chung (quyền xem, công ty, người tạo, ngày, từ khóa) vào query.
     */
    private function applyCommonFilters($query, Request $request, bool $canViewAll, $user, ?string $dateFrom, ?string $dateTo)
    {
        if (! $canViewAll) {
            $query->where('created_by', $user->id);
        }

        if ($request->filled('company')) {
            $query->where('company', $request->company);
        }

        if ($canViewAll && $request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($request->filled('q')) {
            $kw = trim($request->q);

            $query->where(function ($sub) use ($kw) {
                $sub->where('code', 'like', "%{$kw}%")
                    ->orWhere('receiver_name', 'like', "%{$kw}%")
                    ->orWhere('payment_content', 'like', "%{$kw}%")
                    ->orWhere('reason', 'like', "%{$kw}%")
                    ->orWhere('company', 'like', "%{$kw}%");
            });
        }

        return $query;
    }

    /**
     * Dựng HTML danh sách đề nghị thanh toán dùng cho xuất Excel/PDF.
     */
    private function buildExportHtml($items, array $filters, array $statusLabels, int $totalAmount, int $totalPaid, string $title = 'DANH SÁCH ĐỀ NGHỊ THANH TOÁN'): string
    {
        $rows = '';

        foreach ($items as $index => $it) {
            $paymentContent = trim(strip_tags((string) ($it->payment_content ?? '-')));
            $reasonText = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($it->reason ?? '-'))));

            $dueDateText = '-';
            if (! empty($it->payment_due_date)) {
                try {
                    $dueDateText = Carbon::parse($it->payment_due_date)->format('d/m/Y');
                } catch (\Throwable $e) {
                    $dueDateText = (string) $it->payment_due_date;
                }
            }

            if ($paymentContent === '') {
                $paymentContent = '-';
            }

            if ($reasonText === '') {
                $reasonText = '-';
            }

            $rows .= '
                <tr>
                    <td style="text-align:center;">'.($index + 1).'</td>
                    <td>'.e($it->code).'</td>
                    <td style="text-align:center;">'.e(optional($it->created_at)->format('d/m/Y H:i')).'</td>
                    <td style="text-align:center;">'.e($dueDateText).'</td>
                    <td>'.e($it->receiver_name).'</td>
                    <td style="text-align:right;">'.number_format((int) $it->amount).' đ</td>
                    <td style="text-align:center;">'.e($statusLabels[$it->status] ?? $it->status).'</td>
                    <td>'.e($it->company ?? '-').'</td>
                    <td>'.e(optional($it->creator)->name ?? ($it->created_by ?? '-')).'</td>
                    <td>'.e($paymentContent).'</td>
                    <td>'.e($reasonText).'</td>
                </tr>
            ';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="11" style="text-align:center;">Không có dữ liệu</td></tr>';
        }

        return '
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <title>'.e($title).'</title>
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111827; }
                h2 { margin: 0 0 10px 0; font-size: 18px; }
                .meta { margin-bottom: 12px; }
                .meta table { width: 100%; border-collapse: collapse; }
                .meta td { padding: 4px 6px; border: 1px solid #d1d5db; }
                .summary { margin: 10px 0 14px 0; }
                .summary span { display: inline-block; margin-right: 18px; font-weight: bold; }
                table.list { width: 100%; border-collapse: collapse; table-layout: fixed; }
                table.list th, table.list td {
                    border: 1px solid #d1d5db;
                    padding: 6px;
                    vertical-align: top;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                }
                table.list th {
                    background: #f3f4f6;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <h2>'.e($title).'</h2>

            <div class="meta">
                <table>
                    <tr>
                        <td><strong>Ngày xuất</strong></td>
                        <td>'.e(now()->format('d/m/Y H:i')).'</td>
                        <td><strong>Trạng thái</strong></td>
                        <td>'.e($filters['status'] ?? 'Tất cả').'</td>
                    </tr>
                    <tr>
                        <td><strong>Công ty</strong></td>
                        <td>'.e($filters['company'] ?? 'Tất cả').'</td>
                        <td><strong>Người tạo</strong></td>
                        <td>'.e($filters['created_by'] ?? 'Tất cả').'</td>
                    </tr>
                    <tr>
                        <td><strong>Từ ngày</strong></td>
                        <td>'.e($filters['date_from'] ?? '-').'</td>
                        <td><strong>Đến ngày</strong></td>
                        <td>'.e($filters['date_to'] ?? '-').'</td>
                    </tr>
                    <tr>
                        <td><strong>Preset</strong></td>
                        <td>'.e($filters['date_preset'] ?? '-').'</td>
                        <td><strong>Từ khóa</strong></td>
                        <td>'.e($filters['q'] ?? '-').'</td>
                    </tr>
                </table>
            </div>

            <div class="summary">
                <span>Tổng phiếu: '.count($items).'</span>
                <span>Tổng tiền: '.number_format((int) $totalAmount).' đ</span>
                <span>Tổng đã chi: '.number_format((int) $totalPaid).' đ</span>
            </div>

            <table class="list">
                <thead>
                    <tr>
                        <th style="width:40px;">STT</th>
                        <th style="width:110px;">Mã</th>
                        <th style="width:110px;">Ngày tạo</th>
                        <th style="width:110px;">Hạn thanh toán</th>
                        <th style="width:170px;">Người nhận</th>
                        <th style="width:110px;">Số tiền</th>
                        <th style="width:140px;">Trạng thái</th>
                        <th style="width:190px;">Công ty</th>
                        <th style="width:120px;">Người tạo</th>
                        <th style="width:220px;">Nội dung thanh toán</th>
                        <th style="width:280px;">Lý do</th>
                    </tr>
                </thead>
                <tbody>
                    '.$rows.'
                </tbody>
            </table>
        </body>
        </html>';
    }

    /**
     * Nhãn tiếng Việt của preset khoảng ngày.
     */
    private function datePresetLabel(?string $preset): string
    {
        return match ($preset) {
            'today' => 'Hôm nay',
            'this_week' => 'Tuần này',
            'last_week' => 'Tuần trước',
            'this_month' => 'Tháng này',
            'last_month' => 'Tháng trước',
            'this_year' => 'Năm nay',
            'custom' => 'Tùy chỉnh',
            'all_time' => 'Tất cả thời gian',
            default => 'Tháng này',
        };
    }

    /**
     * Hiển thị danh sách đề nghị thanh toán kèm bộ lọc và tổng đã chi.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $canAdminApprove = $this->isAdmin($user);
        $canAccountingApprove = $this->isAccounting($user);
        $canBulkApprove = ($canAdminApprove || $canAccountingApprove);
        $canViewAll = $canBulkApprove;

        [$selectedDatePreset, $effectiveDateFrom, $effectiveDateTo] = $this->resolveDateFilter($request);

        $base = PaymentRequest::query();
        $this->applyCommonFilters($base, $request, $canViewAll, $user, $effectiveDateFrom, $effectiveDateTo);

        $totalPaid = (clone $base)
            ->where('status', 'accounting_approved')
            ->sum('amount');

        $q = (clone $base)
            ->with(['creator'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        $items = $q->paginate(20)->withQueryString();

        $companyOptions = $this->companyOptions();
        $statusLabels = $this->statusLabels();

        if ($canViewAll) {
            $creatorIds = (clone $base)
                ->whereNotNull('created_by')
                ->distinct()
                ->pluck('created_by')
                ->filter()
                ->values();

            $creatorOptions = User::query()
                ->whereIn('id', $creatorIds)
                ->orderBy('name')
                ->get(['id', 'name']);
        } else {
            $creatorOptions = collect([$user]);
        }

        return view('payment_requests.index', compact(
            'items',
            'companyOptions',
            'statusLabels',
            'creatorOptions',
            'totalPaid',
            'canViewAll',
            'canAdminApprove',
            'canAccountingApprove',
            'canBulkApprove',
            'selectedDatePreset',
            'effectiveDateFrom',
            'effectiveDateTo'
        ));
    }

    /**
     * Chuyển hướng về trang danh sách (tạo phiếu ngay trên trang danh sách).
     */
    public function create()
    {
        return redirect()->route('payment_requests.index');
    }

    /**
     * Chuyển hướng về trang danh sách.
     */
    public function demoCreate()
    {
        return redirect()->route('payment_requests.index');
    }

    /**
     * Xuất danh sách đề nghị thanh toán theo bộ lọc ra file Excel.
     */
    public function exportExcel(Request $request)
    {
        $user = auth()->user();
        $canViewAll = ($this->isAdmin($user) || $this->isAccounting($user));

        [$selectedDatePreset, $effectiveDateFrom, $effectiveDateTo] = $this->resolveDateFilter($request);

        $query = PaymentRequest::query();
        $this->applyCommonFilters($query, $request, $canViewAll, $user, $effectiveDateFrom, $effectiveDateTo);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $items = (clone $query)
            ->with(['creator'])
            ->orderByDesc('id')
            ->get();

        $totalAmount = (clone $query)->sum('amount');
        $totalPaid = (clone $query)->where('status', 'accounting_approved')->sum('amount');

        $statusLabels = $this->statusLabels();

        $creatorName = null;
        if ($request->filled('created_by')) {
            $creatorName = optional(User::find($request->created_by))->name;
        }

        $filters = [
            'status' => $request->filled('status') ? ($statusLabels[$request->status] ?? $request->status) : 'Tất cả',
            'company' => $request->filled('company') ? $request->company : 'Tất cả',
            'created_by' => $creatorName ?: 'Tất cả',
            'date_from' => $effectiveDateFrom ?: '-',
            'date_to' => $effectiveDateTo ?: '-',
            'date_preset' => $this->datePresetLabel($selectedDatePreset),
            'q' => $request->filled('q') ? $request->q : '-',
        ];

        $html = $this->buildExportHtml(
            $items,
            $filters,
            $statusLabels,
            (int) $totalAmount,
            (int) $totalPaid,
            'DANH SÁCH ĐỀ NGHỊ THANH TOÁN'
        );

        $fileName = 'danh_sach_de_nghi_thanh_toan_'.now()->format('Ymd_His').'.xls';

        return response("\xEF\xBB\xBF".$html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Xuất danh sách đề nghị thanh toán theo bộ lọc ra file PDF.
     */
    public function exportPdf(Request $request)
    {
        $user = auth()->user();
        $canViewAll = ($this->isAdmin($user) || $this->isAccounting($user));

        [$selectedDatePreset, $effectiveDateFrom, $effectiveDateTo] = $this->resolveDateFilter($request);

        $query = PaymentRequest::query();
        $this->applyCommonFilters($query, $request, $canViewAll, $user, $effectiveDateFrom, $effectiveDateTo);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $items = (clone $query)
            ->with(['creator'])
            ->orderByDesc('id')
            ->get();

        $totalAmount = (clone $query)->sum('amount');
        $totalPaid = (clone $query)->where('status', 'accounting_approved')->sum('amount');

        $statusLabels = $this->statusLabels();

        $creatorName = null;
        if ($request->filled('created_by')) {
            $creatorName = optional(User::find($request->created_by))->name;
        }

        $filters = [
            'status' => $request->filled('status') ? ($statusLabels[$request->status] ?? $request->status) : 'Tất cả',
            'company' => $request->filled('company') ? $request->company : 'Tất cả',
            'created_by' => $creatorName ?: 'Tất cả',
            'date_from' => $effectiveDateFrom ?: '-',
            'date_to' => $effectiveDateTo ?: '-',
            'date_preset' => $this->datePresetLabel($selectedDatePreset),
            'q' => $request->filled('q') ? $request->q : '-',
        ];

        $html = $this->buildExportHtml(
            $items,
            $filters,
            $statusLabels,
            (int) $totalAmount,
            (int) $totalPaid,
            'DANH SÁCH ĐỀ NGHỊ THANH TOÁN'
        );

        $pdf = Pdf::loadHTML($html)
            ->setPaper('A3', 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->download('danh_sach_de_nghi_thanh_toan_'.now()->format('Ymd_His').'.pdf');
    }

    /**
     * Tạo phiếu đề nghị thanh toán mới, sinh mã và lưu chứng từ đính kèm.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        try {
            $allowedDocTypes = ['payment_request', 'payment_voucher', 'advance', 'refund_request'];

            $rawDocType = trim((string) $request->input('doc_type', 'payment_request'));
            $lowerDocType = function_exists('mb_strtolower')
                ? mb_strtolower($rawDocType, 'UTF-8')
                : strtolower($rawDocType);

            if (! in_array($rawDocType, $allowedDocTypes, true)) {
                if (str_contains($lowerDocType, 'hoàn tiền') || str_contains($lowerDocType, 'hoan tien') || str_contains($lowerDocType, 'refund')) {
                    $rawDocType = 'refund_request';
                } elseif (str_contains($lowerDocType, 'phiếu chi') || str_contains($lowerDocType, 'phieu chi') || str_contains($lowerDocType, 'voucher')) {
                    $rawDocType = 'payment_voucher';
                } elseif (str_contains($lowerDocType, 'tạm ứng') || str_contains($lowerDocType, 'tam ung') || str_contains($lowerDocType, 'advance')) {
                    $rawDocType = 'advance';
                } else {
                    $rawDocType = 'payment_request';
                }
            }

            $rawAmount = $request->input('amount');
            if (is_string($rawAmount)) {
                $cleanAmount = preg_replace('/[^0-9]/', '', $rawAmount);
                $request->merge(['amount' => $cleanAmount === '' ? null : (int) $cleanAmount]);
            }

            $request->merge(['doc_type' => $rawDocType]);

            if (! $request->filled('company')) {
                $fallbackCompany = (string) session('active_company_name', '');
                if ($fallbackCompany === '') {
                    $fallbackCompany = $this->companyOptions()[0] ?? 'Công ty TNHH Ego Việt Nam';
                }
                $request->merge(['company' => $fallbackCompany]);
            }

            if (! $request->filled('reason')) {
                $fallbackReason = trim((string) $request->input('payment_content', ''));
                if ($fallbackReason === '') {
                    $fallbackReason = 'Thanh toán theo đề nghị';
                }
                $request->merge(['reason' => $fallbackReason]);
            }

            if (! $request->filled('payment_content')) {
                $request->merge(['payment_content' => trim((string) $request->input('reason', 'Thanh toán theo đề nghị'))]);
            }

            $data = $request->validate([
                'doc_type' => 'required|in:payment_request,payment_voucher,advance,refund_request',
                'company' => 'required|string|max:5000',
                'receiver_name' => 'required|string|max:5000',
                'department' => 'nullable|string|max:5000',
                'payment_content' => 'nullable|string|max:10000',
                'reason' => 'required|string|max:50000',
                'amount' => 'required|integer|min:1',
                'payment_due_date' => 'nullable|date',
                'bank_info' => 'nullable|string|max:10000',
                'attachments' => ['nullable', 'array'],
                'attachments.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
            ], [
                'company.required' => 'Vui lòng chọn công ty.',
                'receiver_name.required' => 'Vui lòng nhập người nhận.',
                'reason.required' => 'Vui lòng nhập lý do.',
                'amount.required' => 'Vui lòng nhập số tiền.',
                'amount.min' => 'Số tiền phải lớn hơn 0.',
                'attachments.*.max' => 'File chứng từ không được vượt quá 20MB.',
                'attachments.*.mimes' => 'Chứng từ chỉ nhận JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX.',
            ]);

            unset($data['attachments']);

            $now = now();
            $columns = Schema::getColumnListing('payment_requests');

            $data['created_by'] = (int) $user->id;
            $data['status'] = 'draft';
            $data['code'] = 'TMP-'.(string) Str::uuid();

            if (in_array('created_at', $columns, true)) {
                $data['created_at'] = $now;
            }

            if (in_array('updated_at', $columns, true)) {
                $data['updated_at'] = $now;
            }

            if (in_array('company_id', $columns, true)) {
                $companyId = 0;

                if (Schema::hasTable('companies')) {
                    $companyId = (int) DB::table('companies')
                        ->where('name', $data['company'])
                        ->value('id');

                    if (! $companyId) {
                        if (stripos($data['company'], 'Quốc') !== false || stripos($data['company'], 'Quoc') !== false || stripos($data['company'], 'TMKT') !== false || stripos($data['company'], 'QT') !== false) {
                            $companyId = (int) DB::table('companies')
                                ->where('name', 'like', '%Quốc%')
                                ->orWhere('name', 'like', '%Quoc%')
                                ->orWhere('name', 'like', '%TMKT%')
                                ->value('id');
                        } else {
                            $companyId = (int) DB::table('companies')
                                ->where('name', 'like', '%Ego Việt Nam%')
                                ->orWhere('name', 'like', '%Ego Viet Nam%')
                                ->orWhere('name', 'like', '%EGO VIETNAM%')
                                ->value('id');
                        }
                    }
                }

                if (! $companyId) {
                    $companyId = (int) $request->input('company_id', session('active_company_id', 0));
                }

                if ($companyId > 0) {
                    $data['company_id'] = $companyId;
                }
            }

            $prId = DB::transaction(function () use ($data, $request, $now, $columns) {
                $insert = array_intersect_key($data, array_flip($columns));

                $id = DB::table('payment_requests')->insertGetId($insert);

                $code = 'PR-'.now()->format('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
                $update = ['code' => $code];

                if (in_array('updated_at', $columns, true)) {
                    $update['updated_at'] = $now;
                }

                DB::table('payment_requests')->where('id', $id)->update($update);

                if ($request->hasFile('attachments') && Schema::hasTable('payment_attachments')) {
                    $attachmentColumns = Schema::getColumnListing('payment_attachments');

                    foreach ($request->file('attachments') as $file) {
                        if (! $file || ! $file->isValid()) {
                            continue;
                        }

                        $path = $file->store("payment_requests/{$id}", 'public');

                        $attachment = [
                            'payment_request_id' => $id,
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime_type' => $file->getClientMimeType(),
                            'size' => $file->getSize(),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        DB::table('payment_attachments')->insert(array_intersect_key($attachment, array_flip($attachmentColumns)));
                    }
                }

                return $id;
            });

            /* EGO_PR_AJAX_CREATE_RESPONSE_START */
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Đã tạo đề nghị thanh toán thành công.',
                    'id' => (int) $prId,
                    'redirect_url' => route('payment_requests.index'),
                ], 201);
            }
            /* EGO_PR_AJAX_CREATE_RESPONSE_END */

            return redirect()
                ->route('payment_requests.show', $prId)
                ->with('success', 'Đã tạo đề nghị thanh toán thành công. Phiếu mới đã được lưu và hiển thị.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Payment request store failed', [
                'user_id' => optional(auth()->user())->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Không lưu được đề nghị thanh toán: '.$e->getMessage());
        }
    }

    /**
     * Hiển thị chi tiết phiếu đề nghị thanh toán.
     */
    public function show($id)
    {
        $item = PaymentRequest::with(['creator', 'attachments'])->findOrFail($id);
        $user = auth()->user();

        if (! ($this->isAdmin($user) || $this->isAccounting($user)) && (int) $item->created_by !== (int) $user->id) {
            abort(403);
        }

        $item->status_label = $this->statusLabel($item->status);

        return view('payment_requests.show', compact('item'));
    }

    /**
     * Hiển thị form sửa phiếu khi còn được phép chỉnh sửa.
     */
    public function edit($id)
    {
        $item = PaymentRequest::with('attachments')->findOrFail($id);
        $user = auth()->user();
        $canEditCompleted = $this->canEditCompletedFinanceRecord();

        if (! $canEditCompleted && (int) $item->created_by !== (int) $user->id) {
            abort(403);
        }

        if (! $canEditCompleted && ! in_array($item->status, ['draft', 'admin_rejected', 'accounting_rejected'], true)) {
            return redirect()->route('payment_requests.show', $item->id)
                ->with('error', 'Phiếu đã gửi duyệt/hoàn thành nên không sửa được.');
        }

        $companyOptions = $this->companyOptions();

        return view('payment_requests.edit', compact('item', 'companyOptions'));
    }

    /**
     * Cập nhật phiếu đề nghị thanh toán.
     */
    public function update(Request $request, $id)
    {
        $item = PaymentRequest::findOrFail($id);
        $user = auth()->user();

        $canEditCompleted = $this->canEditCompletedFinanceRecord();

        if (! $canEditCompleted && (int) $item->created_by !== (int) $user->id) {
            abort(403);
        }

        if (! $canEditCompleted && ! in_array($item->status, ['draft', 'admin_rejected', 'accounting_rejected'], true)) {
            return redirect()->route('payment_requests.show', $item->id)
                ->with('error', 'Phiếu đã gửi duyệt/hoàn thành nên không sửa được.');
        }

        $data = $request->validate([
            'company' => 'required|string|max:255',
            'receiver_name' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'payment_content' => 'nullable|string|max:255',
            'reason' => 'required|string',
            'amount' => 'required|integer|min:1',
            'payment_due_date' => 'nullable|date',
            'bank_info' => 'nullable|string|max:255',
        ]);

        $item->update($data);

        return redirect()->route('payment_requests.show', $item->id)
            ->with('success', 'Đã cập nhật phiếu.');
    }

    /**
     * Xóa phiếu và chứng từ; mở lại đợt công nợ liên kết nếu có.
     */
    public function destroy($id)
    {
        $item = PaymentRequest::with('attachments')->findOrFail($id);
        $user = auth()->user();
        $canEditCompleted = $this->canEditCompletedFinanceRecord();

        if (! $canEditCompleted && (int) $item->created_by !== (int) $user->id) {
            abort(403);
        }

        if (! $canEditCompleted && ! in_array($item->status, ['draft', 'admin_rejected', 'accounting_rejected'], true)) {
            return redirect()->route('payment_requests.index')
                ->with('error', 'Phiếu đã gửi duyệt/hoàn thành nên không xoá được.');
        }

        if (
            Schema::hasTable('finance_supplier_debt_payments') &&
            Schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id')
        ) {
            DB::table('finance_supplier_debt_payments')
                ->where('payment_request_id', (int) $item->id)
                ->update([
                    'payment_request_id' => null,
                    'status' => 'planned',
                    'updated_at' => now(),
                ]);
        }

        foreach ($item->attachments as $att) {
            Storage::disk('public')->delete($att->path);
        }

        $item->delete();

        return redirect()->route('payment_requests.index')
            ->with('success', 'Đã xoá phiếu. Nếu phiếu có liên kết công nợ, đợt liên quan đã mở lại để tạo ĐNTT mới.');
    }

    /**
     * Chủ phiếu gửi phiếu đi duyệt.
     */
    public function submit($id)
    {
        $item = PaymentRequest::findOrFail($id);
        $user = auth()->user();

        if ((int) $item->created_by !== (int) $user->id) {
            abort(403);
        }

        if (! in_array($item->status, ['draft', 'admin_rejected', 'accounting_rejected'], true)) {
            return redirect()->route('payment_requests.show', $item->id)
                ->with('error', 'Phiếu không ở trạng thái có thể gửi duyệt.');
        }

        $item->status = 'submitted';
        $item->save();

        return redirect()->route('payment_requests.show', $item->id)
            ->with('success', 'Đã gửi Quản lý tài chính duyệt.');
    }

    /**
     * Quản lý tài chính duyệt phiếu.
     */
    public function adminApprove(Request $request, $id)
    {
        $item = PaymentRequest::findOrFail($id);
        $user = auth()->user();

        if (! $this->isAdmin($user)) {
            abort(403);
        }

        if (($item->status ?? '') !== 'submitted') {
            return redirect()->route('payment_requests.show', $item->id)
                ->with('error', 'Chỉ duyệt khi phiếu đang "Đã gửi duyệt".');
        }

        $item->status = 'admin_approved';
        $item->admin_approved_by = $user->id;
        $item->admin_approved_at = now();
        $item->admin_note = $request->input('note');
        $item->save();

        return redirect()->route('payment_requests.show', $item->id)
            ->with('success', 'Quản lý tài chính đã duyệt.');
    }

    /**
     * Quản lý tài chính từ chối phiếu.
     */
    public function adminReject(Request $request, $id)
    {
        $item = PaymentRequest::findOrFail($id);
        $user = auth()->user();

        if (! $this->isAdmin($user)) {
            abort(403);
        }

        if (($item->status ?? '') !== 'submitted') {
            return redirect()->route('payment_requests.show', $item->id)
                ->with('error', 'Chỉ từ chối khi phiếu đang "Đã gửi duyệt".');
        }

        $item->status = 'admin_rejected';
        $item->admin_approved_by = $user->id;
        $item->admin_approved_at = now();
        $item->admin_note = $request->input('note');
        $item->save();

        return redirect()->route('payment_requests.show', $item->id)
            ->with('success', 'Quản lý tài chính đã từ chối.');
    }

    /**
     * Kế toán xác nhận đã chi phiếu.
     */
    public function accApprove(Request $request, $id)
    {
        $item = PaymentRequest::findOrFail($id);
        $user = auth()->user();

        if (! $this->isAccounting($user)) {
            abort(403);
        }

        if (($item->status ?? '') !== 'admin_approved') {
            return redirect()->route('payment_requests.show', $item->id)
                ->with('error', 'Chỉ chi khi phiếu đã được Quản lý tài chính duyệt.');
        }

        $item->status = 'accounting_approved';
        $item->accounting_approved_by = $user->id;
        $item->accounting_approved_at = now();
        $item->accounting_note = $request->input('note');
        $item->save();

        return redirect()->route('payment_requests.show', $item->id)
            ->with('success', 'Kế toán đã chi.');
    }

    /**
     * Kế toán từ chối phiếu.
     */
    public function accReject(Request $request, $id)
    {
        $item = PaymentRequest::findOrFail($id);
        $user = auth()->user();

        if (! $this->isAccounting($user)) {
            abort(403);
        }

        if (($item->status ?? '') !== 'admin_approved') {
            return redirect()->route('payment_requests.show', $item->id)
                ->with('error', 'Chỉ từ chối khi phiếu đã được Quản lý tài chính duyệt.');
        }

        $item->status = 'accounting_rejected';
        $item->accounting_approved_by = $user->id;
        $item->accounting_approved_at = now();
        $item->accounting_note = $request->input('note');
        $item->save();

        return redirect()->route('payment_requests.show', $item->id)
            ->with('success', 'Kế toán đã từ chối.');
    }

    /**
     * Tải PDF phiếu đề nghị thanh toán đã được kế toán chi.
     */
    public function invoice($id)
    {
        $item = PaymentRequest::with(['creator'])->findOrFail($id);
        $user = auth()->user();

        if (! ($this->isAdmin($user) || $this->isAccounting($user)) && (int) $item->created_by !== (int) $user->id) {
            abort(403);
        }

        if (($item->status ?? '') !== 'accounting_approved') {
            abort(403, 'Chỉ tải PDF khi kế toán đã chi.');
        }

        $company = (string) ($item->company ?? '');
        $isEGP = (stripos($company, 'TMKT') !== false)
            || (stripos($company, 'Quốc') !== false)
            || (stripos($company, 'Quoc') !== false)
            || (stripos($company, 'EGP') !== false);

        $companyKey = $isEGP ? 'egp' : 'ego';

        $logoAbs = public_path("assets/logos/{$companyKey}.png");
        $financeSigAbs = public_path("assets/signatures/{$companyKey}_finance.png");
        $accSigAbs = public_path("assets/signatures/{$companyKey}_accounting.png");

        $logoDataUri = $this->fileToDataUri($logoAbs);
        $financeSigDataUri = $this->fileToDataUri($financeSigAbs);
        $accSigDataUri = $this->fileToDataUri($accSigAbs);

        $amountText = $this->vnNumberToWords((int) $item->amount);
        $statusLabel = $this->statusLabel($item->status);

        $pdf = Pdf::loadView('payment_requests.invoice_pdf', compact(
            'item',
            'logoDataUri',
            'financeSigDataUri',
            'accSigDataUri',
            'amountText',
            'statusLabel'
        ))
            ->setPaper('A5', 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->download('DNTT_'.$item->code.'.pdf');
    }

    /**
     * Chuyển file ảnh sang chuỗi data URI base64.
     */
    private function fileToDataUri(?string $absPath): ?string
    {
        if (! $absPath || ! is_file($absPath)) {
            return null;
        }

        $mime = mime_content_type($absPath) ?: 'image/png';
        $data = base64_encode(file_get_contents($absPath));

        return "data:$mime;base64,$data";
    }

    /**
     * Đọc số tiền thành chữ tiếng Việt.
     */
    private function vnNumberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Không đồng';
        }
        if ($number < 0) {
            return 'Âm '.$this->vnNumberToWords(abs($number));
        }

        $units = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
        $scales = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];

        $readTriple = function ($n, $full) use ($units) {
            $hundreds = intdiv($n, 100);
            $tens = intdiv($n % 100, 10);
            $ones = $n % 10;

            $out = [];

            if ($full || $hundreds > 0) {
                $out[] = $units[$hundreds].' trăm';
                if ($tens === 0 && $ones > 0) {
                    $out[] = 'lẻ';
                }
            }

            if ($tens > 1) {
                $out[] = $units[$tens].' mươi';
                if ($ones === 1) {
                    $out[] = 'mốt';
                } elseif ($ones === 5) {
                    $out[] = 'lăm';
                } elseif ($ones > 0) {
                    $out[] = $units[$ones];
                }
            } elseif ($tens === 1) {
                $out[] = 'mười';
                if ($ones === 5) {
                    $out[] = 'lăm';
                } elseif ($ones > 0) {
                    $out[] = $units[$ones];
                }
            } else {
                if ($ones > 0) {
                    $out[] = $units[$ones];
                }
            }

            return trim(implode(' ', $out));
        };

        $parts = [];
        $scaleIndex = 0;
        $full = false;

        while ($number > 0) {
            $triple = $number % 1000;
            if ($triple > 0) {
                $text = $readTriple($triple, $full);
                $suffix = $scales[$scaleIndex] ?? '';
                $parts[] = trim($text.' '.$suffix);
                $full = true;
            }
            $number = intdiv($number, 1000);
            $scaleIndex++;
        }

        $result = trim(implode(' ', array_reverse($parts)));
        $result = mb_strtoupper(mb_substr($result, 0, 1)).mb_substr($result, 1);

        return $result.' đồng';
    }

    /**
     * Kiểm tra tài khoản đặc biệt được sửa/xóa phiếu đã hoàn thành.
     */
    private function canEditCompletedFinanceRecord(): bool
    {
        return strtolower((string) optional(auth()->user())->email) === 'buibichthao@egosolar.vn';
    }

    /* EGO_THAO_PAYMENT_REQUEST_HELPER_START */
    /**
     * Kiểm tra tài khoản chỉ định có toàn quyền thao tác phiếu.
     */
    private function egoThaoCanFullPaymentRequest(): bool
    {
        return strtolower((string) optional(auth()->user())->email) === 'buibichthao@egosolar.vn';
    }
    /* EGO_THAO_PAYMENT_REQUEST_HELPER_END */
}
