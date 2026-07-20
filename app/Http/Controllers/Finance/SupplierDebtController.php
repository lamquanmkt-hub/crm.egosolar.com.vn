<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SupplierDebtPaymentRoundRequest;
use App\Http\Requests\Finance\SupplierDebtRequest;
use App\Services\Finance\SupplierDebtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quản lý công nợ nhà cung cấp: đợt thanh toán, ĐNTT liên kết và tệp đính kèm.
 */
class SupplierDebtController extends Controller
{
    /**
     * Khởi tạo controller, inject service nghiệp vụ công nợ nhà cung cấp.
     */
    public function __construct(
        private readonly SupplierDebtService $supplierDebtService,
    ) {}

    /**
     * Chuẩn hóa các trường tiền tệ trong request trước khi validate.
     */
    private function normalizeMoneyFields(Request $request, array $fields): void
    {
        $payload = [];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $payload[$field] = $this->supplierDebtService->normalizeMoneyInput($request->input($field));
            }
        }

        if (count($payload)) {
            $request->merge($payload);
        }
    }

    /**
     * Tạo các bảng công nợ NCC nếu chưa tồn tại và bổ sung cột còn thiếu.
     */
    private function ensureSupplierDebtTables(): void
    {
        DB::statement("\n            CREATE TABLE IF NOT EXISTS finance_supplier_debts (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n                supplier_name VARCHAR(255) NOT NULL,\n                company_name VARCHAR(255) NULL,\n                document_no VARCHAR(100) NULL,\n                document_date DATE NULL,\n                debt_month DATE NULL,\n                total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,\n                paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,\n                note TEXT NULL,\n                bank_info TEXT NULL,\n                status VARCHAR(50) NOT NULL DEFAULT 'unpaid',\n                created_by BIGINT UNSIGNED NULL,\n                created_at TIMESTAMP NULL DEFAULT NULL,\n                updated_at TIMESTAMP NULL DEFAULT NULL,\n                INDEX finance_supplier_debts_supplier_name_index (supplier_name),\n                INDEX finance_supplier_debts_debt_month_index (debt_month),\n                INDEX finance_supplier_debts_status_index (status)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        DB::statement("\n            CREATE TABLE IF NOT EXISTS finance_supplier_debt_files (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n                supplier_debt_id BIGINT UNSIGNED NOT NULL,\n                original_name VARCHAR(255) NOT NULL,\n                path VARCHAR(500) NOT NULL,\n                mime_type VARCHAR(150) NULL,\n                size BIGINT UNSIGNED NULL,\n                created_at TIMESTAMP NULL DEFAULT NULL,\n                updated_at TIMESTAMP NULL DEFAULT NULL,\n                INDEX fsdf_supplier_debt_id_index (supplier_debt_id)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        DB::statement("\n            CREATE TABLE IF NOT EXISTS finance_supplier_debt_payments (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n                supplier_debt_id BIGINT UNSIGNED NOT NULL,\n                payment_request_id BIGINT UNSIGNED NULL,\n                payment_round INT UNSIGNED NOT NULL DEFAULT 1,\n                amount DECIMAL(15,2) NOT NULL DEFAULT 0,\n                payment_date DATE NULL,\n                status VARCHAR(50) NOT NULL DEFAULT 'planned',\n                note TEXT NULL,\n                created_by BIGINT UNSIGNED NULL,\n                created_at TIMESTAMP NULL DEFAULT NULL,\n                updated_at TIMESTAMP NULL DEFAULT NULL,\n                INDEX fsdp_supplier_debt_id_index (supplier_debt_id),\n                INDEX fsdp_payment_request_id_index (payment_request_id),\n                INDEX fsdp_status_index (status)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        DB::statement("\n            CREATE TABLE IF NOT EXISTS finance_supplier_debt_payment_files (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n                supplier_debt_payment_id BIGINT UNSIGNED NOT NULL,\n                original_name VARCHAR(255) NOT NULL,\n                path VARCHAR(500) NOT NULL,\n                mime_type VARCHAR(150) NULL,\n                size BIGINT UNSIGNED NULL,\n                created_at TIMESTAMP NULL DEFAULT NULL,\n                updated_at TIMESTAMP NULL DEFAULT NULL,\n                INDEX fsdpf_payment_id_index (supplier_debt_payment_id)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        if (! Schema::hasColumn('finance_supplier_debts', 'bank_info')) {
            DB::statement('ALTER TABLE finance_supplier_debts ADD COLUMN bank_info TEXT NULL AFTER note');
        }

        if (! Schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id')) {
            DB::statement('ALTER TABLE finance_supplier_debt_payments ADD COLUMN payment_request_id BIGINT UNSIGNED NULL AFTER supplier_debt_id');
        }
    }

    /**
     * Danh sách công nợ NCC kèm đợt thanh toán, tệp đính kèm và tổng hợp theo bộ lọc.
     */
    public function index(Request $request)
    {
        $this->ensureSupplierDebtTables();
        $keyword = trim((string) $request->input('keyword'));
        $status = $request->input('status');
        $period = $request->input('period', 'all');

        if (! in_array($period, ['all', 'month'], true)) {
            $period = 'all';
        }

        $month = $request->input('month', now()->format('Y-m'));

        $monthStart = $month.'-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $debts = collect();
        $paymentRoundsByDebt = collect();
        $paymentFilesByRound = collect();

        if (Schema::hasTable('finance_supplier_debts')) {
            $query = DB::table('finance_supplier_debts');

            if ($period === 'month') {
                $query->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereNull('debt_month')
                        ->orWhereBetween('debt_month', [$monthStart, $monthEnd]);
                });
            }

            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->where('supplier_name', 'like', '%'.$keyword.'%')
                        ->orWhere('company_name', 'like', '%'.$keyword.'%')
                        ->orWhere('document_no', 'like', '%'.$keyword.'%')
                        ->orWhere('note', 'like', '%'.$keyword.'%');

                    if (Schema::hasColumn('finance_supplier_debts', 'bank_info')) {
                        $q->orWhere('bank_info', 'like', '%'.$keyword.'%');
                    }
                });
            }

            $debts = $query
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->get();

            if (Schema::hasTable('finance_supplier_debt_files') && $debts->count()) {
                $debtFiles = DB::table('finance_supplier_debt_files')
                    ->whereIn('supplier_debt_id', $debts->pluck('id')->values()->all())
                    ->orderBy('id')
                    ->get()
                    ->groupBy('supplier_debt_id');

                $debts = $debts->map(function ($debt) use ($debtFiles) {
                    $debt->files = $debtFiles->get($debt->id, collect());
                    $debt->file_count = $debt->files->count();

                    return $debt;
                });
            }
        }

        if (Schema::hasTable('finance_supplier_debt_payments') && $debts->count()) {
            $debtIds = $debts->pluck('id')->values()->all();

            $rounds = DB::table('finance_supplier_debt_payments')
                ->whereIn('supplier_debt_id', $debtIds)
                ->orderBy('payment_round')
                ->orderBy('id')
                ->get();

            $cleanedMissingPaymentRequests = $this->supplierDebtService->clearMissingSupplierDebtPaymentRequests($rounds->pluck('id')->values()->all());

            if ($cleanedMissingPaymentRequests > 0) {
                $rounds = DB::table('finance_supplier_debt_payments')
                    ->whereIn('supplier_debt_id', $debtIds)
                    ->orderBy('payment_round')
                    ->orderBy('id')
                    ->get();
            }

            if (
                Schema::hasTable('payment_requests') &&
                Schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id') &&
                Schema::hasColumn('payment_requests', 'status')
            ) {
                $requestIds = $rounds
                    ->pluck('payment_request_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $requestMap = collect();

                if (count($requestIds)) {
                    $requestMap = DB::table('payment_requests')
                        ->whereIn('id', $requestIds)
                        ->get()
                        ->keyBy('id');
                }

                $rounds = $rounds->map(function ($round) use ($requestMap) {
                    $round->payment_request_status = null;
                    $round->payment_request_is_locked = false;
                    $round->payment_request_amount = null;
                    $round->paid_amount_by_request = 0;
                    $round->remaining_amount_by_request = 0;
                    $round->is_partial_paid = false;
                    $round->remaining_round_exists = false;

                    if (! empty($round->payment_request_id) && $requestMap->has($round->payment_request_id)) {
                        $paymentRequest = $requestMap[$round->payment_request_id];
                        $meta = $this->supplierDebtService->roundPaymentMeta($round);

                        $round->payment_request_status = $meta['payment_request_status'];
                        $round->payment_request_amount = $meta['payment_request_amount'];
                        $round->payment_request_is_locked = $meta['payment_request_is_locked'];
                        $round->paid_amount_by_request = $meta['paid_amount'];
                        $round->remaining_amount_by_request = $meta['remaining_amount'];
                        $round->is_partial_paid = $meta['is_partial_paid'];
                        $round->remaining_round_exists = $this->supplierDebtService->remainingRoundAlreadyExists($round, (float) $meta['remaining_amount']);

                        if ($this->supplierDebtService->paymentRequestIsCompleted($paymentRequest)) {
                            $round->status = 'accounting_approved';
                        } elseif (! empty($round->payment_request_status)) {
                            $round->status = $round->payment_request_status;
                        }
                    }

                    return $round;
                });
            }

            if (Schema::hasTable('finance_supplier_debt_payment_files')) {
                $roundIds = $rounds->pluck('id')->values()->all();

                if (count($roundIds)) {
                    $files = DB::table('finance_supplier_debt_payment_files')
                        ->whereIn('supplier_debt_payment_id', $roundIds)
                        ->orderBy('id')
                        ->get();

                    $paymentFilesByRound = $files->groupBy('supplier_debt_payment_id');
                }

                $rounds = $rounds->map(function ($round) use ($paymentFilesByRound) {
                    $round->files = $paymentFilesByRound->get($round->id, collect());
                    $round->file_count = $round->files->count();

                    return $round;
                });
            }

            $paymentRoundsByDebt = $rounds->groupBy('supplier_debt_id');
        }

        $paidStatuses = $this->supplierDebtService->paidRoundStatuses();
        $pendingStatuses = $this->supplierDebtService->pendingRoundStatuses();

        $debts = $debts->map(function ($item) use ($paymentRoundsByDebt) {
            $item->total_amount = (float) ($item->total_amount ?? 0);

            $rounds = $paymentRoundsByDebt->get($item->id, collect());

            $paidAmount = 0.0;
            $pendingAmount = 0.0;

            foreach ($rounds as $round) {
                /*
                 * FIX:
                 * Trước đây mỗi round đều bị gán paid_amount_by_request = 0,
                 * kể cả round không có ĐNTT. Vì dùng isset(), các round paid/accounting_approved
                 * không có ĐNTT bị bỏ qua roundPaymentMeta() nên dòng cha hiện Đã thanh toán = 0.
                 *
                 * Luôn dùng roundPaymentMeta() để tính chuẩn:
                 * - Có ĐNTT kế toán đã chi: tính theo ĐNTT.
                 * - Không có ĐNTT nhưng round.status = paid/accounting_approved: tính đã thanh toán.
                 * - Đợt planned/requested/submitted/admin_approved: tính đang chờ.
                 */
                $meta = $this->supplierDebtService->roundPaymentMeta($round);
                $paidAmount += (float) $meta['paid_amount'];
                $pendingAmount += (float) $meta['pending_amount'];
            }

            $item->paid_amount = $paidAmount;
            $item->pending_payment_amount = $pendingAmount;
            $item->remain_amount = max($item->total_amount - $paidAmount, 0);
            $item->payment_rounds = $rounds;
            $item->payment_round_count = $rounds->count();

            if ($item->remain_amount <= 0) {
                $item->status = 'paid';
                $item->status_text = 'Đã thanh toán đủ';
                $item->status_class = 'success';
            } elseif ($paidAmount > 0 || $pendingAmount > 0) {
                $item->status = 'partial';
                $item->status_text = 'Đang thanh toán theo đợt';
                $item->status_class = 'warning';
            } else {
                $item->status = 'unpaid';
                $item->status_text = 'Chưa thanh toán';
                $item->status_class = 'danger';
            }

            return $item;
        });

        if ($status && in_array($status, ['unpaid', 'partial', 'paid'], true)) {
            $debts = $debts
                ->filter(function ($item) use ($status) {
                    return $item->status === $status;
                })
                ->values();
        }

        $summary = [
            'total_amount' => (float) $debts->sum('total_amount'),
            'paid_amount' => (float) $debts->sum('paid_amount'),
            'remain_amount' => (float) $debts->sum('remain_amount'),
            'pending_payment_amount' => (float) $debts->sum('pending_payment_amount'),
            'supplier_count' => $debts->pluck('supplier_name')->filter()->unique()->count(),
            'debt_count' => $debts->count(),
            'payment_round_count' => (int) $debts->sum('payment_round_count'),
        ];

        $companyOptions = $this->supplierDebtService->companyOptions();

        return view('finance.supplier-debts.index', compact(
            'debts',
            'summary',
            'keyword',
            'status',
            'period',
            'month',
            'monthStart',
            'monthEnd',
            'paymentRoundsByDebt',
            'paymentFilesByRound',
            'companyOptions'
        ));
    }

    /**
     * Thêm công nợ nhà cung cấp mới kèm tệp đính kèm.
     */
    public function store(SupplierDebtRequest $request)
    {
        $data = $request->validated();

        $payload = [
            'supplier_name' => trim($data['supplier_name']),
            'company_name' => $data['company_name'],
            'document_no' => ! empty($data['document_no']) ? trim($data['document_no']) : null,
            'document_date' => $data['document_date'] ?? null,
            'debt_month' => $data['debt_month'].'-01',
            'total_amount' => (float) $data['total_amount'],
            'paid_amount' => 0,
            'note' => $data['note'] ?? null,
            'status' => 'unpaid',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('finance_supplier_debts', 'bank_info')) {
            $payload['bank_info'] = $data['bank_info'] ?? null;
        }

        $debtId = DB::table('finance_supplier_debts')->insertGetId($payload);

        $this->storeDebtFiles($request, (int) $debtId);
        $this->supplierDebtService->syncSupplierDebtTotals((int) $debtId);

        return redirect()
            ->route('finance.supplier-debts.index', ['month' => $data['debt_month']])
            ->with('success', 'Đã thêm công nợ nhà cung cấp.');
    }

    /**
     * Cập nhật công nợ NCC (chặn khi tổng nhỏ hơn các đợt đã nhập).
     */
    public function update(SupplierDebtRequest $request, $id)
    {
        $data = $request->validated();

        $otherRoundsTotal = Schema::hasTable('finance_supplier_debt_payments')
            ? (float) DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $id)
                ->sum('amount')
            : 0.0;

        if ($otherRoundsTotal > (float) $data['total_amount']) {
            return back()
                ->withErrors(['total_amount' => 'Tổng công nợ không được nhỏ hơn tổng các đợt thanh toán đã nhập.'])
                ->withInput();
        }

        $payload = [
            'supplier_name' => trim($data['supplier_name']),
            'company_name' => $data['company_name'],
            'document_no' => ! empty($data['document_no']) ? trim($data['document_no']) : null,
            'document_date' => $data['document_date'] ?? null,
            'debt_month' => $data['debt_month'].'-01',
            'total_amount' => (float) $data['total_amount'],
            'note' => $data['note'] ?? null,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('finance_supplier_debts', 'bank_info')) {
            $payload['bank_info'] = $data['bank_info'] ?? null;
        }

        DB::table('finance_supplier_debts')
            ->where('id', $id)
            ->update($payload);

        $this->storeDebtFiles($request, (int) $id);
        $this->supplierDebtService->syncSupplierDebtTotals((int) $id);

        return redirect()
            ->route('finance.supplier-debts.index', ['month' => $data['debt_month']])
            ->with('success', 'Đã cập nhật công nợ nhà cung cấp.');
    }

    /**
     * Xóa công nợ NCC cùng các đợt thanh toán và tệp liên quan.
     */
    public function destroy($id)
    {
        $debt = DB::table('finance_supplier_debts')->where('id', $id)->first();

        if (! $debt) {
            abort(404);
        }

        if (
            Schema::hasTable('finance_supplier_debt_payments') &&
            Schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id') &&
            DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $id)
                ->whereNotNull('payment_request_id')
                ->exists() &&
            ! $this->canEditCompletedFinanceRecord()
        ) {
            return back()->withErrors([
                'error' => 'Công nợ này đã có ĐNTT liên kết, chỉ buibichthao@egosolar.vn được xóa/sửa.',
            ]);
        }

        if (Schema::hasTable('finance_supplier_debt_payments')) {
            $roundIds = DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $id)
                ->pluck('id')
                ->values()
                ->all();

            if (Schema::hasTable('finance_supplier_debt_payment_files') && count($roundIds)) {
                $files = DB::table('finance_supplier_debt_payment_files')
                    ->whereIn('supplier_debt_payment_id', $roundIds)
                    ->get();

                foreach ($files as $file) {
                    if (! empty($file->path)) {
                        Storage::disk('public')->delete($file->path);
                    }
                }

                DB::table('finance_supplier_debt_payment_files')
                    ->whereIn('supplier_debt_payment_id', $roundIds)
                    ->delete();
            }

            DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $id)
                ->delete();
        }

        if (Schema::hasTable('finance_supplier_debt_files')) {
            $debtFiles = DB::table('finance_supplier_debt_files')
                ->where('supplier_debt_id', $id)
                ->get();

            foreach ($debtFiles as $file) {
                if (! empty($file->path)) {
                    Storage::disk('public')->delete($file->path);
                }
            }

            DB::table('finance_supplier_debt_files')
                ->where('supplier_debt_id', $id)
                ->delete();
        }

        DB::table('finance_supplier_debts')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('finance.supplier-debts.index', [
                'month' => $this->supplierDebtService->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã xóa công nợ nhà cung cấp.');
    }

    /**
     * Thêm đợt thanh toán: đơn lẻ hoặc hàng loạt theo phần trăm / số tiền.
     */
    public function storePaymentRound(Request $request, $id)
    {
        $this->normalizeMoneyFields($request, ['amount']);

        if (! Schema::hasTable('finance_supplier_debt_payments')) {
            return back()->withErrors(['error' => 'Chưa có bảng finance_supplier_debt_payments.']);
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $id)->first();

        if (! $debt) {
            abort(404);
        }

        if ($request->has('bulk_rounds')) {
            $rawRows = collect($request->input('bulk_rounds', []))
                ->filter(function ($row) {
                    if (! is_array($row)) {
                        return false;
                    }

                    return trim((string) ($row['percent'] ?? '')) !== ''
                        || trim((string) ($row['amount'] ?? '')) !== ''
                        || trim((string) ($row['payment_date'] ?? '')) !== ''
                        || trim((string) ($row['note'] ?? '')) !== '';
                })
                ->values();

            if ($rawRows->isEmpty()) {
                return back()
                    ->withErrors(['bulk_rounds' => 'Vui lòng nhập ít nhất 1 dòng đợt thanh toán.'])
                    ->withInput();
            }

            $totalAmount = (float) ($debt->total_amount ?? 0);
            $nextRound = ((int) DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $id)
                ->max('payment_round')) + 1;

            $bulkRows = [];
            $bulkTotal = 0.0;
            $errors = [];
            $allowedStatuses = ['planned', 'paid', 'requested'];

            foreach ($rawRows as $index => $row) {
                $roundNo = isset($row['payment_round']) && (int) $row['payment_round'] > 0
                    ? (int) $row['payment_round']
                    : ($nextRound + (int) $index);

                $percentText = $this->supplierDebtService->normalizeMoneyInput($row['percent'] ?? '');
                $amountText = $this->supplierDebtService->normalizeMoneyInput($row['amount'] ?? '');

                $percent = $percentText !== '' ? (float) $percentText : null;
                $amount = $amountText !== '' ? (float) $amountText : null;

                if (($amount === null || $amount <= 0) && $percent !== null && $percent > 0) {
                    $amount = round($totalAmount * $percent / 100, 2);
                }

                if ($percent !== null && ($percent < 0 || $percent > 100)) {
                    $errors[] = 'Dòng '.($index + 1).': phần trăm phải từ 0 đến 100.';
                }

                if ($amount === null || $amount <= 0) {
                    $errors[] = 'Dòng '.($index + 1).': số tiền phải lớn hơn 0.';

                    continue;
                }

                $paymentDate = trim((string) ($row['payment_date'] ?? ''));
                $paymentDate = $paymentDate !== '' ? $paymentDate : null;

                if ($paymentDate !== null && strtotime($paymentDate) === false) {
                    $errors[] = 'Dòng '.($index + 1).': ngày thanh toán không hợp lệ.';

                    continue;
                }

                $status = (string) ($row['status'] ?? 'planned');
                if (! in_array($status, $allowedStatuses, true)) {
                    $status = 'planned';
                }

                $bulkTotal += $amount;

                $bulkRows[] = [
                    'supplier_debt_id' => $id,
                    'payment_request_id' => null,
                    'payment_round' => $roundNo,
                    'amount' => $amount,
                    'payment_date' => $paymentDate,
                    'status' => $status,
                    'note' => trim((string) ($row['note'] ?? '')) ?: null,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (count($errors)) {
                return back()->withErrors(['bulk_rounds' => implode(' ', $errors)])->withInput();
            }

            $existingTotal = (float) DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $id)
                ->sum('amount');

            if ($existingTotal + $bulkTotal > $totalAmount) {
                return back()
                    ->withErrors(['bulk_rounds' => 'Tổng các đợt thanh toán không được vượt quá tổng công nợ.'])
                    ->withInput();
            }

            DB::table('finance_supplier_debt_payments')->insert($bulkRows);
            $this->supplierDebtService->syncSupplierDebtTotals((int) $id);

            return redirect()
                ->route('finance.supplier-debts.index', [
                    'month' => $this->supplierDebtService->supplierDebtMonth($debt),
                ])
                ->with('success', 'Đã thêm '.count($bulkRows).' đợt thanh toán.');
        }

        /* EGO_SINGLE_ROUND_PERCENT_AMOUNT_START */
        if (! $request->has('bulk_rounds')) {
            $percentRaw = trim((string) $request->input('_percent', ''));

            if ($percentRaw !== '' && trim((string) $request->input('amount', '')) === '') {
                $percent = (float) str_replace(',', '.', str_replace('.', '', $percentRaw));
                $totalAmount = (float) ($debt->total_amount ?? 0);

                if ($percent > 0 && $totalAmount > 0) {
                    $request->merge([
                        'amount' => round($totalAmount * $percent / 100, 2),
                    ]);
                }
            }
        }
        /* EGO_SINGLE_ROUND_PERCENT_AMOUNT_END */

        /*
         * CỐ Ý giữ validate inline (không dùng SupplierDebtPaymentRoundRequest):
         * endpoint này nhận 2 dạng payload — nhập 1 đợt (amount ở cấp gốc) hoặc
         * nhập nhiều đợt qua bulk_rounds[] (đã xử lý và return ở nhánh trên).
         * FormRequest chạy trước controller sẽ bắt buộc 'amount' và làm gãy nhánh bulk.
         */
        $data = $request->validate([
            'payment_round' => ['nullable', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ]);

        $amount = (float) $data['amount'];

        $existingTotal = (float) DB::table('finance_supplier_debt_payments')
            ->where('supplier_debt_id', $id)
            ->sum('amount');

        if ($existingTotal + $amount > (float) $debt->total_amount) {
            return back()
                ->withErrors(['amount' => 'Tổng các đợt thanh toán không được vượt quá tổng công nợ.'])
                ->withInput();
        }

        $paymentRound = $data['payment_round'] ?? null;

        if (! $paymentRound) {
            $paymentRound = ((int) DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $id)
                ->max('payment_round')) + 1;
        }

        $roundId = DB::table('finance_supplier_debt_payments')->insertGetId([
            'supplier_debt_id' => $id,
            'payment_request_id' => null,
            'payment_round' => $paymentRound,
            'amount' => $amount,
            'payment_date' => $data['payment_date'] ?? null,
            'status' => $data['status'] ?? 'planned',
            'note' => $data['note'] ?? null,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->storeRoundFiles($request, $roundId);
        $this->supplierDebtService->syncSupplierDebtTotals((int) $id);

        return redirect()
            ->route('finance.supplier-debts.index', [
                'month' => $this->supplierDebtService->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã thêm đợt thanh toán.');
    }

    /**
     * Cập nhật đợt thanh toán và đồng bộ ĐNTT liên kết nếu phiếu chưa chi.
     */
    public function updatePaymentRound(SupplierDebtPaymentRoundRequest $request, $paymentRoundId)
    {
        if (! Schema::hasTable('finance_supplier_debt_payments')) {
            return back()->withErrors(['error' => 'Chưa có bảng finance_supplier_debt_payments.']);
        }

        $round = DB::table('finance_supplier_debt_payments')->where('id', $paymentRoundId)->first();

        if (! $round) {
            abort(404);
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $round->supplier_debt_id)->first();

        if (! $debt) {
            abort(404);
        }

        if ($this->supplierDebtService->paymentRoundIsLocked($round) && ! $this->canEditCompletedFinanceRecord()) {
            return back()->withErrors([
                'error' => 'Đợt thanh toán đã có ĐNTT đang gửi/đã duyệt, không sửa trực tiếp được. Hãy xử lý ĐNTT trước.',
            ]);
        }

        $data = $request->validated();

        $amount = (float) $data['amount'];

        $existingTotal = (float) DB::table('finance_supplier_debt_payments')
            ->where('supplier_debt_id', $round->supplier_debt_id)
            ->where('id', '!=', $paymentRoundId)
            ->sum('amount');

        if ($existingTotal + $amount > (float) $debt->total_amount) {
            return back()
                ->withErrors(['amount' => 'Tổng các đợt thanh toán không được vượt quá tổng công nợ.'])
                ->withInput();
        }

        DB::table('finance_supplier_debt_payments')
            ->where('id', $paymentRoundId)
            ->update([
                'payment_round' => $data['payment_round'],
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? null,
                'status' => $data['status'] ?? 'planned',
                'note' => $data['note'] ?? null,
                'updated_at' => now(),
            ]);

        $this->storeRoundFiles($request, $paymentRoundId);

        if (! empty($round->payment_request_id) && Schema::hasTable('payment_requests')) {
            $linkedPaymentRequest = $this->supplierDebtService->paymentRequestRow((int) $round->payment_request_id);

            // Phiếu đã kế toán chi thì KHÔNG tự nâng/hạ số tiền theo đợt nữa.
            // Nếu phiếu đã chi 458tr trong đợt 658tr, phần còn lại sẽ tạo dòng công nợ mới.
            if ($linkedPaymentRequest && ! $this->supplierDebtService->paymentRequestIsCompleted($linkedPaymentRequest)) {
                $reason = $this->supplierDebtService->supplierDebtPaymentReason(
                    $debt,
                    (object) array_merge((array) $round, $data)
                );

                $paymentRequestUpdate = [];

                if (Schema::hasColumn('payment_requests', 'amount')) {
                    $paymentRequestUpdate['amount'] = (int) round($amount);
                }

                if (Schema::hasColumn('payment_requests', 'company')) {
                    $paymentRequestUpdate['company'] = $debt->company_name;
                }

                if (Schema::hasColumn('payment_requests', 'bank_info') && Schema::hasColumn('finance_supplier_debts', 'bank_info')) {
                    $paymentRequestUpdate['bank_info'] = $debt->bank_info ?? null;
                }

                if (Schema::hasColumn('payment_requests', 'reason')) {
                    $paymentRequestUpdate['reason'] = $reason;
                }

                if (Schema::hasColumn('payment_requests', 'payment_content')) {
                    $paymentRequestUpdate['payment_content'] = $reason;
                }

                if (Schema::hasColumn('payment_requests', 'updated_at')) {
                    $paymentRequestUpdate['updated_at'] = now();
                }

                if (count($paymentRequestUpdate)) {
                    DB::table('payment_requests')
                        ->where('id', $round->payment_request_id)
                        ->update($paymentRequestUpdate);
                }
            }

            $this->copyRoundFilesToPaymentRequest($paymentRoundId, $round->payment_request_id);
        }

        $this->supplierDebtService->syncSupplierDebtTotals((int) $round->supplier_debt_id);

        return redirect()
            ->route('finance.supplier-debts.index', [
                'month' => $this->supplierDebtService->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã cập nhật đợt thanh toán.');
    }

    /**
     * Xóa đợt thanh toán cùng tệp đính kèm.
     */
    public function destroyPaymentRound($paymentRoundId)
    {
        if (! Schema::hasTable('finance_supplier_debt_payments')) {
            return back()->withErrors(['error' => 'Chưa có bảng finance_supplier_debt_payments.']);
        }

        $round = DB::table('finance_supplier_debt_payments')->where('id', $paymentRoundId)->first();

        if (! $round) {
            abort(404);
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $round->supplier_debt_id)->first();

        if (! empty($round->payment_request_id) && $this->supplierDebtService->paymentRequestExistsForSupplierDebt((int) $round->payment_request_id) && ! $this->canEditCompletedFinanceRecord()) {
            return back()->withErrors([
                'error' => 'Đợt này đã liên kết ĐNTT, chỉ buibichthao@egosolar.vn được xóa/sửa.',
            ]);
        }

        if (Schema::hasTable('finance_supplier_debt_payment_files')) {
            $files = DB::table('finance_supplier_debt_payment_files')
                ->where('supplier_debt_payment_id', $paymentRoundId)
                ->get();

            foreach ($files as $file) {
                if (! empty($file->path)) {
                    Storage::disk('public')->delete($file->path);
                }
            }

            DB::table('finance_supplier_debt_payment_files')
                ->where('supplier_debt_payment_id', $paymentRoundId)
                ->delete();
        }

        DB::table('finance_supplier_debt_payments')
            ->where('id', $paymentRoundId)
            ->delete();

        $this->supplierDebtService->syncSupplierDebtTotals((int) $round->supplier_debt_id);

        return redirect()
            ->route('finance.supplier-debts.index', [
                'month' => $this->supplierDebtService->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã xóa đợt thanh toán.');
    }

    /**
     * Tạo ĐNTT nháp từ một đợt thanh toán công nợ NCC.
     */
    public function createPaymentRequestFromRound($paymentRoundId)
    {
        if (! Schema::hasTable('finance_supplier_debt_payments') || ! Schema::hasTable('payment_requests')) {
            return back()->withErrors(['error' => 'Thiếu bảng finance_supplier_debt_payments hoặc payment_requests.']);
        }

        $round = DB::table('finance_supplier_debt_payments')->where('id', $paymentRoundId)->first();

        if (! $round) {
            abort(404);
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $round->supplier_debt_id)->first();

        if (! $debt) {
            abort(404);
        }
        if (! empty($round->payment_request_id)) {
            if ($this->supplierDebtService->paymentRequestExistsForSupplierDebt((int) $round->payment_request_id)) {
                return redirect()
                    ->route('payment_requests.show', $round->payment_request_id)
                    ->with('success', 'Đợt thanh toán này đã có ĐNTT liên kết.');
            }

            DB::table('finance_supplier_debt_payments')
                ->where('id', $paymentRoundId)
                ->update([
                    'payment_request_id' => null,
                    'status' => 'planned',
                    'updated_at' => now(),
                ]);

            $round->payment_request_id = null;
            $round->status = 'planned';

            session()->flash('success', 'ĐNTT liên kết này đã bị xóa nên hệ thống đã mở lại đợt thanh toán.');
        }

        if (in_array((string) ($round->status ?? ''), ['paid', 'accounting_approved'], true) && ! $this->canEditCompletedFinanceRecord()) {
            return redirect()
                ->route('finance.supplier-debts.index', [
                    'month' => $this->supplierDebtService->supplierDebtMonth($debt),
                ])
                ->withErrors(['error' => 'Đợt thanh toán này đã thanh toán nên không tạo ĐNTT nữa.']);
        }

        if (in_array((string) ($round->status ?? ''), $this->supplierDebtService->paidRoundStatuses(), true) && ! $this->canEditCompletedFinanceRecord()) {
            $this->supplierDebtService->syncSupplierDebtTotals((int) $debt->id);

            return redirect()
                ->route('finance.supplier-debts.index', [
                    'month' => $this->supplierDebtService->supplierDebtMonth($debt),
                ])
                ->withErrors(['error' => 'Đợt thanh toán này đã đánh dấu đã thanh toán nên không tạo ĐNTT nữa.']);
        }

        $reason = $this->supplierDebtService->supplierDebtPaymentReason($debt, $round);
        $company = $debt->company_name ?: 'Công ty TNHH Ego Việt Nam';

        $columns = Schema::getColumnListing('payment_requests');
        $data = [];

        $put = function ($column, $value) use (&$data, $columns) {
            if (in_array($column, $columns, true)) {
                $data[$column] = $value;
            }
        };

        $put('code', 'TMP-SUPPLIER-'.(string) Str::uuid());
        $put('doc_type', 'payment_request');
        $put('company', $company);

        if (in_array('company_id', $columns, true) && Schema::hasTable('companies')) {
            $companyId = (int) DB::table('companies')->where('name', $company)->value('id');

            if (! $companyId) {
                if (stripos($company, 'Quốc') !== false || stripos($company, 'Quoc') !== false || stripos($company, 'TMKT') !== false || stripos($company, 'QT') !== false) {
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

            if ($companyId > 0) {
                $put('company_id', $companyId);
            }
        }

        if (in_array('payment_due_date', $columns, true) && ! empty($round->payment_date)) {
            $put('payment_due_date', $round->payment_date);
        }
        $put('receiver_name', $debt->supplier_name);
        $put('department', 'Nhà cung cấp');
        $put('payment_content', $reason);
        $put('reason', $reason);
        $put('amount', (int) round((float) $round->amount));
        $put('bank_info', Schema::hasColumn('finance_supplier_debts', 'bank_info') ? ($debt->bank_info ?? null) : null);
        $put('status', 'draft');
        $put('created_by', auth()->id());
        $put('created_at', now());
        $put('updated_at', now());

        if (in_array('cost_type', $columns, true)) {
            $put('cost_type', 'Nhà cung cấp');
        }

        $paymentRequestId = DB::transaction(function () use ($data, $paymentRoundId) {
            $paymentRequestId = DB::table('payment_requests')->insertGetId($data);

            $code = 'PR-'.now()->format('Y').'-'.str_pad((string) $paymentRequestId, 5, '0', STR_PAD_LEFT);

            DB::table('payment_requests')
                ->where('id', $paymentRequestId)
                ->update([
                    'code' => $code,
                    'updated_at' => now(),
                ]);

            DB::table('finance_supplier_debt_payments')
                ->where('id', $paymentRoundId)
                ->update([
                    'payment_request_id' => $paymentRequestId,
                    'status' => 'requested',
                    'updated_at' => now(),
                ]);

            return $paymentRequestId;
        });

        $this->copyRoundFilesToPaymentRequest($paymentRoundId, $paymentRequestId);
        $this->copyDebtFilesToPaymentRequest((int) $debt->id, $paymentRequestId);
        $this->supplierDebtService->syncSupplierDebtTotals((int) $debt->id);

        return redirect()
            ->route('payment_requests.show', $paymentRequestId)
            ->with('success', 'Đã tạo ĐNTT nháp từ đợt thanh toán công nợ NCC. Bạn kiểm tra rồi bấm Gửi duyệt.');
    }

    /**
     * Tạo đợt mới cho phần còn thiếu khi ĐNTT liên kết chi ít hơn số tiền của đợt.
     */
    public function createRemainingRoundFromLinkedPayment($paymentRoundId)
    {
        abort_unless($this->canEditCompletedFinanceRecord(), 403);

        if (! Schema::hasTable('finance_supplier_debt_payments')) {
            return back()->withErrors(['error' => 'Chưa có bảng finance_supplier_debt_payments.']);
        }

        $round = DB::table('finance_supplier_debt_payments')->where('id', (int) $paymentRoundId)->first();

        if (! $round) {
            abort(404);
        }

        if (empty($round->payment_request_id)) {
            return back()->withErrors(['error' => 'Đợt này chưa có ĐNTT liên kết.']);
        }

        $debt = DB::table('finance_supplier_debts')->where('id', (int) $round->supplier_debt_id)->first();

        if (! $debt) {
            abort(404);
        }

        $paymentRequest = $this->supplierDebtService->paymentRequestRow((int) $round->payment_request_id);

        if (! $paymentRequest || ! $this->supplierDebtService->paymentRequestIsCompleted($paymentRequest)) {
            return back()->withErrors(['error' => 'ĐNTT liên kết chưa ở trạng thái kế toán đã chi.']);
        }

        $meta = $this->supplierDebtService->roundPaymentMeta($round);
        $remainingAmount = (float) $meta['remaining_amount'];

        if ($remainingAmount <= 0) {
            return back()->withErrors(['error' => 'Đợt này không còn số tiền thiếu.']);
        }

        if ($this->supplierDebtService->remainingRoundAlreadyExists($round, $remainingAmount)) {
            return back()->withErrors(['error' => 'Đã có dòng công nợ cho phần còn lại của đợt này.']);
        }

        $nextRound = ((int) DB::table('finance_supplier_debt_payments')
            ->where('supplier_debt_id', (int) $round->supplier_debt_id)
            ->max('payment_round')) + 1;

        DB::table('finance_supplier_debt_payments')->insert([
            'supplier_debt_id' => (int) $round->supplier_debt_id,
            'payment_request_id' => null,
            'payment_round' => $nextRound,
            'amount' => $remainingAmount,
            'payment_date' => null,
            'status' => 'planned',
            'note' => 'Phần còn lại của đợt '.($round->payment_round ?? '').' - ĐNTT #'.$round->payment_request_id.' đã chi '.number_format((float) $meta['paid_amount'], 0, ',', '.').' đ',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->supplierDebtService->syncSupplierDebtTotals((int) $round->supplier_debt_id);

        return redirect()
            ->route('finance.supplier-debts.index', ['month' => $this->supplierDebtService->supplierDebtMonth($debt)])
            ->with('success', 'Đã tạo đợt còn lại '.number_format($remainingAmount, 0, ',', '.').' đ. Bấm Tạo ĐNTT ở dòng mới để lập phiếu tiếp.');
    }

    /**
     * Lưu tệp đính kèm cho đợt thanh toán.
     */
    private function storeRoundFiles(Request $request, int $roundId): void
    {
        if (! Schema::hasTable('finance_supplier_debt_payment_files')) {
            return;
        }

        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store("supplier_debt_payments/{$roundId}", 'public');

            DB::table('finance_supplier_debt_payment_files')->insert([
                'supplier_debt_payment_id' => $roundId,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Sao chép tệp của đợt thanh toán sang tệp đính kèm của ĐNTT.
     */
    private function copyRoundFilesToPaymentRequest(int $roundId, int $paymentRequestId): void
    {
        if (! Schema::hasTable('finance_supplier_debt_payment_files')) {
            return;
        }

        $attachmentTable = $this->paymentRequestAttachmentTable();

        if (! $attachmentTable) {
            return;
        }

        $files = DB::table('finance_supplier_debt_payment_files')
            ->where('supplier_debt_payment_id', $roundId)
            ->get();

        foreach ($files as $file) {
            if (empty($file->path) || ! Storage::disk('public')->exists($file->path)) {
                continue;
            }

            $extension = pathinfo($file->path, PATHINFO_EXTENSION);
            $safeName = Str::slug(pathinfo($file->original_name, PATHINFO_FILENAME));
            $newPath = "payment_requests/{$paymentRequestId}/supplier-debt-{$file->id}-{$safeName}";

            if ($extension) {
                $newPath .= '.'.$extension;
            }

            Storage::disk('public')->copy($file->path, $newPath);

            $columns = Schema::getColumnListing($attachmentTable);
            $payload = [];

            $put = function ($column, $value) use (&$payload, $columns) {
                if (in_array($column, $columns, true)) {
                    $payload[$column] = $value;
                }
            };

            $put('payment_request_id', $paymentRequestId);
            $put('original_name', $file->original_name);
            $put('path', $newPath);
            $put('mime_type', $file->mime_type ?? null);
            $put('size', $file->size ?? null);
            $put('created_at', now());
            $put('updated_at', now());

            $alreadyExists = DB::table($attachmentTable)
                ->where('payment_request_id', $paymentRequestId)
                ->where('original_name', $file->original_name)
                ->exists();

            if (! $alreadyExists) {
                DB::table($attachmentTable)->insert($payload);
            }
        }
    }

    /**
     * Xác định bảng lưu tệp đính kèm ĐNTT đang được dùng.
     */
    private function paymentRequestAttachmentTable(): ?string
    {
        if (Schema::hasTable('payment_request_attachments')) {
            return 'payment_request_attachments';
        }

        if (Schema::hasTable('payment_attachments')) {
            return 'payment_attachments';
        }

        if (Schema::hasTable('attachments')) {
            return 'attachments';
        }

        return null;
    }

    /**
     * Chỉ upload thêm tệp đính kèm cho công nợ NCC.
     */
    public function storeDebtFileOnly(Request $request, $id)
    {
        $this->ensureSupplierDebtTables();

        $debt = DB::table('finance_supplier_debts')->where('id', (int) $id)->first();

        abort_unless($debt, 404);

        $request->validate([
            'attachments' => ['required', 'array'],
            'attachments.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,zip,rar'],
        ]);

        $this->storeDebtFiles($request, (int) $id);

        return redirect()
            ->route('finance.supplier-debts.index', [
                'month' => $this->supplierDebtService->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã thêm tệp công nợ.');
    }

    /**
     * Tải xuống tệp đính kèm của công nợ NCC.
     */
    public function downloadDebtFile($fileId)
    {
        $this->ensureSupplierDebtTables();

        abort_unless(Schema::hasTable('finance_supplier_debt_files'), 404);

        $file = DB::table('finance_supplier_debt_files')->where('id', (int) $fileId)->first();

        abort_unless($file, 404);
        abort_if(empty($file->path) || ! Storage::disk('public')->exists($file->path), 404);

        return Storage::disk('public')->download($file->path, $file->original_name ?: basename($file->path));
    }

    /**
     * Xóa tệp đính kèm của công nợ NCC.
     */
    public function destroyDebtFile($fileId)
    {
        $this->ensureSupplierDebtTables();

        abort_unless(Schema::hasTable('finance_supplier_debt_files'), 404);

        $file = DB::table('finance_supplier_debt_files')->where('id', (int) $fileId)->first();

        abort_unless($file, 404);

        $debt = DB::table('finance_supplier_debts')->where('id', (int) $file->supplier_debt_id)->first();

        if (! empty($file->path)) {
            Storage::disk('public')->delete($file->path);
        }

        DB::table('finance_supplier_debt_files')->where('id', (int) $fileId)->delete();

        return redirect()
            ->route('finance.supplier-debts.index', [
                'month' => $this->supplierDebtService->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã xóa tệp công nợ.');
    }

    /**
     * Lưu các tệp upload đính kèm cho công nợ NCC.
     */
    private function storeDebtFiles(Request $request, int $debtId): void
    {
        if (! Schema::hasTable('finance_supplier_debt_files')) {
            return;
        }

        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store("supplier_debts/{$debtId}", 'public');

            DB::table('finance_supplier_debt_files')->insert([
                'supplier_debt_id' => $debtId,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Sao chép tệp của công nợ sang tệp đính kèm của ĐNTT.
     */
    private function copyDebtFilesToPaymentRequest(int $debtId, int $paymentRequestId): void
    {
        if (! Schema::hasTable('finance_supplier_debt_files')) {
            return;
        }

        $attachmentTable = $this->paymentRequestAttachmentTable();

        if (! $attachmentTable) {
            return;
        }

        $files = DB::table('finance_supplier_debt_files')
            ->where('supplier_debt_id', $debtId)
            ->get();

        foreach ($files as $file) {
            if (empty($file->path) || ! Storage::disk('public')->exists($file->path)) {
                continue;
            }

            $extension = pathinfo($file->path, PATHINFO_EXTENSION);
            $safeName = Str::slug(pathinfo($file->original_name, PATHINFO_FILENAME));
            $newPath = "payment_requests/{$paymentRequestId}/supplier-debt-main-{$file->id}-{$safeName}";

            if ($extension) {
                $newPath .= '.'.$extension;
            }

            Storage::disk('public')->copy($file->path, $newPath);

            $columns = Schema::getColumnListing($attachmentTable);
            $payload = [];

            $put = function ($column, $value) use (&$payload, $columns) {
                if (in_array($column, $columns, true)) {
                    $payload[$column] = $value;
                }
            };

            $put('payment_request_id', $paymentRequestId);
            $put('original_name', $file->original_name);
            $put('path', $newPath);
            $put('mime_type', $file->mime_type ?? null);
            $put('size', $file->size ?? null);
            $put('created_at', now());
            $put('updated_at', now());

            $alreadyExists = DB::table($attachmentTable)
                ->where('payment_request_id', $paymentRequestId)
                ->where('original_name', $file->original_name)
                ->exists();

            if (! $alreadyExists) {
                DB::table($attachmentTable)->insert($payload);
            }
        }
    }

    /**
     * Chỉ tài khoản buibichthao@egosolar.vn được sửa / xóa bản ghi đã hoàn tất.
     */
    private function canEditCompletedFinanceRecord(): bool
    {
        return strtolower((string) optional(auth()->user())->email) === 'buibichthao@egosolar.vn';
    }
}
