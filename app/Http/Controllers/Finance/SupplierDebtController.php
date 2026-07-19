<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupplierDebtController extends Controller
{
    private function companyOptions(): array
    {
        return [
            'Công ty TNHH Ego Việt Nam',
            'Công ty TNHH TMKT Quốc Tế EGO',
        ];
    }

    private function paidRoundStatuses(): array
    {
        // payment_requests.status = accounting_approved nghĩa là kế toán đã chi.
        return ['paid', 'accounting_approved'];
    }

    private function pendingRoundStatuses(): array
    {
        return ['planned', 'requested', 'draft', 'submitted', 'pending', 'admin_approved'];
    }


    private function completedPaymentRequestStatuses(): array
    {
        return ['accounting_approved', 'paid', 'completed', 'complete', 'done', 'closed'];
    }

    private function payablePaymentRequestStatuses(): array
    {
        return ['submitted', 'admin_approved', 'requested', 'draft', 'pending'];
    }

    private function paymentRequestRow($paymentRequestId)
    {
        if (!$paymentRequestId || !Schema::hasTable('payment_requests')) {
            return null;
        }

        $query = DB::table('payment_requests')->where('id', (int) $paymentRequestId);

        if (Schema::hasColumn('payment_requests', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->first();
    }

    private function paymentRequestIsCompleted($paymentRequest): bool
    {
        if (!$paymentRequest) {
            return false;
        }

        return in_array(strtolower((string) ($paymentRequest->status ?? '')), $this->completedPaymentRequestStatuses(), true);
    }

    private function paymentRequestIsOpen($paymentRequest): bool
    {
        if (!$paymentRequest) {
            return false;
        }

        return in_array(strtolower((string) ($paymentRequest->status ?? '')), $this->payablePaymentRequestStatuses(), true);
    }

    private function roundPaymentMeta($round): array
    {
        $roundAmount = (float) ($round->amount ?? 0);
        $paymentRequest = null;
        $paymentRequestAmount = null;
        $paymentRequestStatus = null;
        $paidAmount = 0.0;
        $pendingAmount = 0.0;
        $isLocked = false;
        $isPartial = false;
        $missing = false;

        if (!empty($round->payment_request_id)) {
            $paymentRequest = $this->paymentRequestRow((int) $round->payment_request_id);

            if ($paymentRequest) {
                $paymentRequestAmount = (float) ($paymentRequest->amount ?? 0);
                $paymentRequestStatus = strtolower((string) ($paymentRequest->status ?? ''));
                $isLocked = in_array($paymentRequestStatus, array_merge($this->completedPaymentRequestStatuses(), ['submitted', 'admin_approved']), true);

                if ($this->paymentRequestIsCompleted($paymentRequest)) {
                    $paidAmount = $paymentRequestAmount > 0
                        ? min($roundAmount, $paymentRequestAmount)
                        : $roundAmount;
                } else {
                    $pendingAmount = $roundAmount;
                }
            } else {
                $missing = true;
            }
        } else {
            $roundStatus = strtolower((string) ($round->status ?? ''));

            if (in_array($roundStatus, $this->paidRoundStatuses(), true)) {
                $paidAmount = $roundAmount;
            } elseif (in_array($roundStatus, $this->pendingRoundStatuses(), true)) {
                $pendingAmount = $roundAmount;
            }
        }

        $remainingAmount = max($roundAmount - $paidAmount, 0);
        $isPartial = $paidAmount > 0 && $remainingAmount > 0;

        return [
            'payment_request' => $paymentRequest,
            'payment_request_amount' => $paymentRequestAmount,
            'payment_request_status' => $paymentRequestStatus,
            'payment_request_missing' => $missing,
            'payment_request_is_locked' => $isLocked,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'remaining_amount' => $remainingAmount,
            'is_partial_paid' => $isPartial,
        ];
    }

    private function remainingRoundAlreadyExists($round, float $remainingAmount): bool
    {
        if ($remainingAmount <= 0 || !Schema::hasTable('finance_supplier_debt_payments')) {
            return false;
        }

        return DB::table('finance_supplier_debt_payments')
            ->where('supplier_debt_id', (int) ($round->supplier_debt_id ?? 0))
            ->whereNull('payment_request_id')
            ->where(function ($q) use ($round, $remainingAmount) {
                $q->where('note', 'like', '%Phần còn lại của đợt ' . ($round->payment_round ?? '') . '%')
                    ->orWhere(function ($x) use ($remainingAmount) {
                        $x->whereBetween('amount', [$remainingAmount - 1, $remainingAmount + 1])
                            ->whereIn('status', ['planned', 'draft', 'requested', 'pending']);
                    });
            })
            ->exists();
    }

    private function normalizeMoneyInput($value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[\s\x{00A0}]+/u', '', $value) ?? '';
        $value = preg_replace('/[^0-9,.-]/u', '', $value) ?? '';

        if ($value === '' || $value === '-' || $value === ',' || $value === '.') {
            return '';
        }

        $isNegative = substr($value, 0, 1) === '-';
        $value = ltrim($value, '-');

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        } elseif ($lastComma !== false) {
            $after = strlen($value) - $lastComma - 1;
            if (substr_count($value, ',') > 1 || $after > 2) {
                $value = str_replace(',', '', $value);
            } else {
                $value = str_replace(',', '.', $value);
            }
        } elseif ($lastDot !== false) {
            $after = strlen($value) - $lastDot - 1;
            if (substr_count($value, '.') > 1 || $after === 3) {
                $value = str_replace('.', '', $value);
            }
        }

        $value = preg_replace('/[^0-9.]/', '', $value) ?? '';

        if (substr_count($value, '.') > 1) {
            $parts = explode('.', $value);
            $decimal = array_pop($parts);
            $value = implode('', $parts) . '.' . $decimal;
        }

        $value = trim($value, '.');

        if ($value === '') {
            return '';
        }

        return ($isNegative ? '-' : '') . $value;
    }

    private function normalizeMoneyFields(Request $request, array $fields): void
    {
        $payload = [];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $payload[$field] = $this->normalizeMoneyInput($request->input($field));
            }
        }

        if (count($payload)) {
            $request->merge($payload);
        }
    }

    private function paymentRoundIsLocked($round): bool
    {
        if (empty($round->payment_request_id)) {
            return false;
        }

        if (!Schema::hasTable('payment_requests') || !Schema::hasColumn('payment_requests', 'status')) {
            return true;
        }

        $status = DB::table('payment_requests')
            ->where('id', $round->payment_request_id)
            ->value('status');

        return in_array((string) $status, ['submitted', 'admin_approved', 'accounting_approved', 'paid'], true);
    }

    private function syncSupplierDebtTotals(int $debtId): void
    {
        if (!Schema::hasTable('finance_supplier_debts')) {
            return;
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $debtId)->first();

        if (!$debt) {
            return;
        }

        $rounds = collect();

        if (Schema::hasTable('finance_supplier_debt_payments')) {
            $rounds = DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $debtId)
                ->get();
        }

        $paidAmount = 0.0;
        $pendingAmount = 0.0;

        foreach ($rounds as $round) {
            $meta = $this->roundPaymentMeta($round);
            $paidAmount += (float) $meta['paid_amount'];
            $pendingAmount += (float) $meta['pending_amount'];
        }

        $totalAmount = (float) ($debt->total_amount ?? 0);
        $remainAmount = max($totalAmount - $paidAmount, 0);

        $status = 'unpaid';

        if ($remainAmount <= 0 && $totalAmount > 0) {
            $status = 'paid';
        } elseif ($paidAmount > 0 || $pendingAmount > 0) {
            $status = 'partial';
        }

        $payload = [
            'paid_amount' => $paidAmount,
            'status' => $status,
            'updated_at' => now(),
        ];

        DB::table('finance_supplier_debts')
            ->where('id', $debtId)
            ->update($payload);
    }


    private function ensureSupplierDebtTables(): void
    {
        DB::statement("\n            CREATE TABLE IF NOT EXISTS finance_supplier_debts (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n                supplier_name VARCHAR(255) NOT NULL,\n                company_name VARCHAR(255) NULL,\n                document_no VARCHAR(100) NULL,\n                document_date DATE NULL,\n                debt_month DATE NULL,\n                total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,\n                paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,\n                note TEXT NULL,\n                bank_info TEXT NULL,\n                status VARCHAR(50) NOT NULL DEFAULT 'unpaid',\n                created_by BIGINT UNSIGNED NULL,\n                created_at TIMESTAMP NULL DEFAULT NULL,\n                updated_at TIMESTAMP NULL DEFAULT NULL,\n                INDEX finance_supplier_debts_supplier_name_index (supplier_name),\n                INDEX finance_supplier_debts_debt_month_index (debt_month),\n                INDEX finance_supplier_debts_status_index (status)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        DB::statement("\n            CREATE TABLE IF NOT EXISTS finance_supplier_debt_files (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n                supplier_debt_id BIGINT UNSIGNED NOT NULL,\n                original_name VARCHAR(255) NOT NULL,\n                path VARCHAR(500) NOT NULL,\n                mime_type VARCHAR(150) NULL,\n                size BIGINT UNSIGNED NULL,\n                created_at TIMESTAMP NULL DEFAULT NULL,\n                updated_at TIMESTAMP NULL DEFAULT NULL,\n                INDEX fsdf_supplier_debt_id_index (supplier_debt_id)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        DB::statement("\n            CREATE TABLE IF NOT EXISTS finance_supplier_debt_payments (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n                supplier_debt_id BIGINT UNSIGNED NOT NULL,\n                payment_request_id BIGINT UNSIGNED NULL,\n                payment_round INT UNSIGNED NOT NULL DEFAULT 1,\n                amount DECIMAL(15,2) NOT NULL DEFAULT 0,\n                payment_date DATE NULL,\n                status VARCHAR(50) NOT NULL DEFAULT 'planned',\n                note TEXT NULL,\n                created_by BIGINT UNSIGNED NULL,\n                created_at TIMESTAMP NULL DEFAULT NULL,\n                updated_at TIMESTAMP NULL DEFAULT NULL,\n                INDEX fsdp_supplier_debt_id_index (supplier_debt_id),\n                INDEX fsdp_payment_request_id_index (payment_request_id),\n                INDEX fsdp_status_index (status)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        DB::statement("\n            CREATE TABLE IF NOT EXISTS finance_supplier_debt_payment_files (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n                supplier_debt_payment_id BIGINT UNSIGNED NOT NULL,\n                original_name VARCHAR(255) NOT NULL,\n                path VARCHAR(500) NOT NULL,\n                mime_type VARCHAR(150) NULL,\n                size BIGINT UNSIGNED NULL,\n                created_at TIMESTAMP NULL DEFAULT NULL,\n                updated_at TIMESTAMP NULL DEFAULT NULL,\n                INDEX fsdpf_payment_id_index (supplier_debt_payment_id)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        if (!Schema::hasColumn('finance_supplier_debts', 'bank_info')) {
            DB::statement('ALTER TABLE finance_supplier_debts ADD COLUMN bank_info TEXT NULL AFTER note');
        }

        if (!Schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id')) {
            DB::statement('ALTER TABLE finance_supplier_debt_payments ADD COLUMN payment_request_id BIGINT UNSIGNED NULL AFTER supplier_debt_id');
        }
    }
    public function index(Request $request)
    {
        $this->ensureSupplierDebtTables();
        $keyword = trim((string) $request->input('keyword'));
        $status = $request->input('status');
        $period = $request->input('period', 'all');

        if (!in_array($period, ['all', 'month'], true)) {
            $period = 'all';
        }

        $month = $request->input('month', now()->format('Y-m'));

        $monthStart = $month . '-01';
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
                    $q->where('supplier_name', 'like', '%' . $keyword . '%')
                        ->orWhere('company_name', 'like', '%' . $keyword . '%')
                        ->orWhere('document_no', 'like', '%' . $keyword . '%')
                        ->orWhere('note', 'like', '%' . $keyword . '%');

                    if (Schema::hasColumn('finance_supplier_debts', 'bank_info')) {
                        $q->orWhere('bank_info', 'like', '%' . $keyword . '%');
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

            $cleanedMissingPaymentRequests = $this->clearMissingSupplierDebtPaymentRequests($rounds->pluck('id')->values()->all());

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

                    if (!empty($round->payment_request_id) && $requestMap->has($round->payment_request_id)) {
                        $paymentRequest = $requestMap[$round->payment_request_id];
                        $meta = $this->roundPaymentMeta($round);

                        $round->payment_request_status = $meta['payment_request_status'];
                        $round->payment_request_amount = $meta['payment_request_amount'];
                        $round->payment_request_is_locked = $meta['payment_request_is_locked'];
                        $round->paid_amount_by_request = $meta['paid_amount'];
                        $round->remaining_amount_by_request = $meta['remaining_amount'];
                        $round->is_partial_paid = $meta['is_partial_paid'];
                        $round->remaining_round_exists = $this->remainingRoundAlreadyExists($round, (float) $meta['remaining_amount']);

                        if ($this->paymentRequestIsCompleted($paymentRequest)) {
                            $round->status = 'accounting_approved';
                        } elseif (!empty($round->payment_request_status)) {
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

        $paidStatuses = $this->paidRoundStatuses();
        $pendingStatuses = $this->pendingRoundStatuses();

        $debts = $debts->map(function ($item) use ($paymentRoundsByDebt, $paidStatuses, $pendingStatuses) {
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
                $meta = $this->roundPaymentMeta($round);
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

        $companyOptions = $this->companyOptions();

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

    public function store(Request $request)
    {
        $this->normalizeMoneyFields($request, ['total_amount']);

        $data = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', Rule::in($this->companyOptions())],
            'document_no' => ['nullable', 'string', 'max:100'],
            'document_date' => ['nullable', 'date'],
            'debt_month' => ['required', 'date_format:Y-m'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'bank_info' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $payload = [
            'supplier_name' => trim($data['supplier_name']),
            'company_name' => $data['company_name'],
            'document_no' => !empty($data['document_no']) ? trim($data['document_no']) : null,
            'document_date' => $data['document_date'] ?? null,
            'debt_month' => $data['debt_month'] . '-01',
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
        $this->syncSupplierDebtTotals((int) $debtId);

        return redirect()
            ->route('finance.supplier-debts.index', ['month' => $data['debt_month']])
            ->with('success', 'Đã thêm công nợ nhà cung cấp.');
    }

    public function update(Request $request, $id)
    {
        $this->normalizeMoneyFields($request, ['total_amount']);

        $data = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', Rule::in($this->companyOptions())],
            'document_no' => ['nullable', 'string', 'max:100'],
            'document_date' => ['nullable', 'date'],
            'debt_month' => ['required', 'date_format:Y-m'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'bank_info' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

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
            'document_no' => !empty($data['document_no']) ? trim($data['document_no']) : null,
            'document_date' => $data['document_date'] ?? null,
            'debt_month' => $data['debt_month'] . '-01',
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
        $this->syncSupplierDebtTotals((int) $id);

        return redirect()
            ->route('finance.supplier-debts.index', ['month' => $data['debt_month']])
            ->with('success', 'Đã cập nhật công nợ nhà cung cấp.');
    }

    public function destroy($id)
    {
        $debt = DB::table('finance_supplier_debts')->where('id', $id)->first();

        if (!$debt) {
            abort(404);
        }

        if (
            Schema::hasTable('finance_supplier_debt_payments') &&
            Schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id') &&
            DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $id)
                ->whereNotNull('payment_request_id')
                ->exists() &&
            !$this->canEditCompletedFinanceRecord()
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
                    if (!empty($file->path)) {
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
                if (!empty($file->path)) {
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
                'month' => $this->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã xóa công nợ nhà cung cấp.');
    }

    public function storePaymentRound(Request $request, $id)
    {
        $this->normalizeMoneyFields($request, ['amount']);

        if (!Schema::hasTable('finance_supplier_debt_payments')) {
            return back()->withErrors(['error' => 'Chưa có bảng finance_supplier_debt_payments.']);
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $id)->first();

        if (!$debt) {
            abort(404);
        }

        if ($request->has('bulk_rounds')) {
            $rawRows = collect($request->input('bulk_rounds', []))
                ->filter(function ($row) {
                    if (!is_array($row)) {
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

                $percentText = $this->normalizeMoneyInput($row['percent'] ?? '');
                $amountText = $this->normalizeMoneyInput($row['amount'] ?? '');

                $percent = $percentText !== '' ? (float) $percentText : null;
                $amount = $amountText !== '' ? (float) $amountText : null;

                if (($amount === null || $amount <= 0) && $percent !== null && $percent > 0) {
                    $amount = round($totalAmount * $percent / 100, 2);
                }

                if ($percent !== null && ($percent < 0 || $percent > 100)) {
                    $errors[] = 'Dòng ' . ($index + 1) . ': phần trăm phải từ 0 đến 100.';
                }

                if ($amount === null || $amount <= 0) {
                    $errors[] = 'Dòng ' . ($index + 1) . ': số tiền phải lớn hơn 0.';
                    continue;
                }

                $paymentDate = trim((string) ($row['payment_date'] ?? ''));
                $paymentDate = $paymentDate !== '' ? $paymentDate : null;

                if ($paymentDate !== null && strtotime($paymentDate) === false) {
                    $errors[] = 'Dòng ' . ($index + 1) . ': ngày thanh toán không hợp lệ.';
                    continue;
                }

                $status = (string) ($row['status'] ?? 'planned');
                if (!in_array($status, $allowedStatuses, true)) {
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
            $this->syncSupplierDebtTotals((int) $id);

            return redirect()
                ->route('finance.supplier-debts.index', [
                    'month' => $this->supplierDebtMonth($debt),
                ])
                ->with('success', 'Đã thêm ' . count($bulkRows) . ' đợt thanh toán.');
        }

        /* EGO_SINGLE_ROUND_PERCENT_AMOUNT_START */
        if (!$request->has('bulk_rounds')) {
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

        if (!$paymentRound) {
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
        $this->syncSupplierDebtTotals((int) $id);

        return redirect()
            ->route('finance.supplier-debts.index', [
                'month' => $this->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã thêm đợt thanh toán.');
    }

    public function updatePaymentRound(Request $request, $paymentRoundId)
    {
        $this->normalizeMoneyFields($request, ['amount']);

        if (!Schema::hasTable('finance_supplier_debt_payments')) {
            return back()->withErrors(['error' => 'Chưa có bảng finance_supplier_debt_payments.']);
        }

        $round = DB::table('finance_supplier_debt_payments')->where('id', $paymentRoundId)->first();

        if (!$round) {
            abort(404);
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $round->supplier_debt_id)->first();

        if (!$debt) {
            abort(404);
        }

        if ($this->paymentRoundIsLocked($round) && !$this->canEditCompletedFinanceRecord()) {
            return back()->withErrors([
                'error' => 'Đợt thanh toán đã có ĐNTT đang gửi/đã duyệt, không sửa trực tiếp được. Hãy xử lý ĐNTT trước.',
            ]);
        }

        $data = $request->validate([
            'payment_round' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ]);

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

        if (!empty($round->payment_request_id) && Schema::hasTable('payment_requests')) {
            $linkedPaymentRequest = $this->paymentRequestRow((int) $round->payment_request_id);

            // Phiếu đã kế toán chi thì KHÔNG tự nâng/hạ số tiền theo đợt nữa.
            // Nếu phiếu đã chi 458tr trong đợt 658tr, phần còn lại sẽ tạo dòng công nợ mới.
            if ($linkedPaymentRequest && !$this->paymentRequestIsCompleted($linkedPaymentRequest)) {
                $reason = $this->supplierDebtPaymentReason(
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

        $this->syncSupplierDebtTotals((int) $round->supplier_debt_id);

        return redirect()
            ->route('finance.supplier-debts.index', [
                'month' => $this->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã cập nhật đợt thanh toán.');
    }

    public function destroyPaymentRound($paymentRoundId)
    {
        if (!Schema::hasTable('finance_supplier_debt_payments')) {
            return back()->withErrors(['error' => 'Chưa có bảng finance_supplier_debt_payments.']);
        }

        $round = DB::table('finance_supplier_debt_payments')->where('id', $paymentRoundId)->first();

        if (!$round) {
            abort(404);
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $round->supplier_debt_id)->first();

        if (!empty($round->payment_request_id) && $this->paymentRequestExistsForSupplierDebt((int) $round->payment_request_id) && !$this->canEditCompletedFinanceRecord()) {
            return back()->withErrors([
                'error' => 'Đợt này đã liên kết ĐNTT, chỉ buibichthao@egosolar.vn được xóa/sửa.',
            ]);
        }

        if (Schema::hasTable('finance_supplier_debt_payment_files')) {
            $files = DB::table('finance_supplier_debt_payment_files')
                ->where('supplier_debt_payment_id', $paymentRoundId)
                ->get();

            foreach ($files as $file) {
                if (!empty($file->path)) {
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

        $this->syncSupplierDebtTotals((int) $round->supplier_debt_id);

        return redirect()
            ->route('finance.supplier-debts.index', [
                'month' => $this->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã xóa đợt thanh toán.');
    }

    public function createPaymentRequestFromRound($paymentRoundId)
    {
        if (!Schema::hasTable('finance_supplier_debt_payments') || !Schema::hasTable('payment_requests')) {
            return back()->withErrors(['error' => 'Thiếu bảng finance_supplier_debt_payments hoặc payment_requests.']);
        }

        $round = DB::table('finance_supplier_debt_payments')->where('id', $paymentRoundId)->first();

        if (!$round) {
            abort(404);
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $round->supplier_debt_id)->first();

        if (!$debt) {
            abort(404);
        }
        if (!empty($round->payment_request_id)) {
            if ($this->paymentRequestExistsForSupplierDebt((int) $round->payment_request_id)) {
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

        if (in_array((string) ($round->status ?? ''), ['paid', 'accounting_approved'], true) && !$this->canEditCompletedFinanceRecord()) {
            return redirect()
                ->route('finance.supplier-debts.index', [
                    'month' => $this->supplierDebtMonth($debt),
                ])
                ->withErrors(['error' => 'Đợt thanh toán này đã thanh toán nên không tạo ĐNTT nữa.']);
        }

if (in_array((string) ($round->status ?? ''), $this->paidRoundStatuses(), true) && !$this->canEditCompletedFinanceRecord()) {
            $this->syncSupplierDebtTotals((int) $debt->id);

            return redirect()
                ->route('finance.supplier-debts.index', [
                    'month' => $this->supplierDebtMonth($debt),
                ])
                ->withErrors(['error' => 'Đợt thanh toán này đã đánh dấu đã thanh toán nên không tạo ĐNTT nữa.']);
        }

        $reason = $this->supplierDebtPaymentReason($debt, $round);
        $company = $debt->company_name ?: 'Công ty TNHH Ego Việt Nam';

        $columns = Schema::getColumnListing('payment_requests');
        $data = [];

        $put = function ($column, $value) use (&$data, $columns) {
            if (in_array($column, $columns, true)) {
                $data[$column] = $value;
            }
        };

        $put('code', 'TMP-SUPPLIER-' . (string) Str::uuid());
        $put('doc_type', 'payment_request');
        $put('company', $company);

        if (in_array('company_id', $columns, true) && Schema::hasTable('companies')) {
            $companyId = (int) DB::table('companies')->where('name', $company)->value('id');

            if (!$companyId) {
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

        if (in_array('payment_due_date', $columns, true) && !empty($round->payment_date)) {
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

            $code = 'PR-' . now()->format('Y') . '-' . str_pad((string) $paymentRequestId, 5, '0', STR_PAD_LEFT);

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
        $this->syncSupplierDebtTotals((int) $debt->id);

        return redirect()
            ->route('payment_requests.show', $paymentRequestId)
            ->with('success', 'Đã tạo ĐNTT nháp từ đợt thanh toán công nợ NCC. Bạn kiểm tra rồi bấm Gửi duyệt.');
    }



    public function createRemainingRoundFromLinkedPayment($paymentRoundId)
    {
        abort_unless($this->canEditCompletedFinanceRecord(), 403);

        if (!Schema::hasTable('finance_supplier_debt_payments')) {
            return back()->withErrors(['error' => 'Chưa có bảng finance_supplier_debt_payments.']);
        }

        $round = DB::table('finance_supplier_debt_payments')->where('id', (int) $paymentRoundId)->first();

        if (!$round) {
            abort(404);
        }

        if (empty($round->payment_request_id)) {
            return back()->withErrors(['error' => 'Đợt này chưa có ĐNTT liên kết.']);
        }

        $debt = DB::table('finance_supplier_debts')->where('id', (int) $round->supplier_debt_id)->first();

        if (!$debt) {
            abort(404);
        }

        $paymentRequest = $this->paymentRequestRow((int) $round->payment_request_id);

        if (!$paymentRequest || !$this->paymentRequestIsCompleted($paymentRequest)) {
            return back()->withErrors(['error' => 'ĐNTT liên kết chưa ở trạng thái kế toán đã chi.']);
        }

        $meta = $this->roundPaymentMeta($round);
        $remainingAmount = (float) $meta['remaining_amount'];

        if ($remainingAmount <= 0) {
            return back()->withErrors(['error' => 'Đợt này không còn số tiền thiếu.']);
        }

        if ($this->remainingRoundAlreadyExists($round, $remainingAmount)) {
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
            'note' => 'Phần còn lại của đợt ' . ($round->payment_round ?? '') . ' - ĐNTT #' . $round->payment_request_id . ' đã chi ' . number_format((float) $meta['paid_amount'], 0, ',', '.') . ' đ',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->syncSupplierDebtTotals((int) $round->supplier_debt_id);

        return redirect()
            ->route('finance.supplier-debts.index', ['month' => $this->supplierDebtMonth($debt)])
            ->with('success', 'Đã tạo đợt còn lại ' . number_format($remainingAmount, 0, ',', '.') . ' đ. Bấm Tạo ĐNTT ở dòng mới để lập phiếu tiếp.');
    }

    private function paymentRequestExistsForSupplierDebt($paymentRequestId): bool
    {
        if (!$paymentRequestId || !Schema::hasTable('payment_requests')) {
            return false;
        }

        $query = DB::table('payment_requests')->where('id', (int) $paymentRequestId);

        if (Schema::hasColumn('payment_requests', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    private function clearMissingSupplierDebtPaymentRequests(array $roundIds = []): int
    {
        if (
            !Schema::hasTable('finance_supplier_debt_payments') ||
            !Schema::hasTable('payment_requests') ||
            !Schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id')
        ) {
            return 0;
        }

        $query = DB::table('finance_supplier_debt_payments as p')
            ->leftJoin('payment_requests as pr', 'pr.id', '=', 'p.payment_request_id')
            ->whereNotNull('p.payment_request_id');

        if (Schema::hasColumn('payment_requests', 'deleted_at')) {
            $query->where(function ($q) {
                $q->whereNull('pr.id')->orWhereNotNull('pr.deleted_at');
            });
        } else {
            $query->whereNull('pr.id');
        }

        if (count($roundIds)) {
            $query->whereIn('p.id', $roundIds);
        }

        $ids = $query->pluck('p.id')->values()->all();

        if (!count($ids)) {
            return 0;
        }

        DB::table('finance_supplier_debt_payments')
            ->whereIn('id', $ids)
            ->update([
                'payment_request_id' => null,
                'status' => 'planned',
                'updated_at' => now(),
            ]);

        return count($ids);
    }

    private function supplierDebtMonth($debt)
    {
        return $debt && !empty($debt->debt_month)
            ? date('Y-m', strtotime($debt->debt_month))
            : now()->format('Y-m');
    }

    private function supplierDebtPaymentReason($debt, $round)
    {
        $roundNo = (int) ($round->payment_round ?? 1);

        $reason = 'Thanh toán đợt ' . $roundNo . ' công nợ nhà cung cấp ' . ($debt->supplier_name ?? '');

        if (!empty($debt->document_no)) {
            $reason .= ' - chứng từ ' . $debt->document_no;
        }

        if (!empty($debt->document_date)) {
            $reason .= ' ngày ' . date('d/m/Y', strtotime($debt->document_date));
        }

        return $reason;
    }

    private function storeRoundFiles(Request $request, int $roundId): void
    {
        if (!Schema::hasTable('finance_supplier_debt_payment_files')) {
            return;
        }

        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            if (!$file || !$file->isValid()) {
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

    private function copyRoundFilesToPaymentRequest(int $roundId, int $paymentRequestId): void
    {
        if (!Schema::hasTable('finance_supplier_debt_payment_files')) {
            return;
        }

        $attachmentTable = $this->paymentRequestAttachmentTable();

        if (!$attachmentTable) {
            return;
        }

        $files = DB::table('finance_supplier_debt_payment_files')
            ->where('supplier_debt_payment_id', $roundId)
            ->get();

        foreach ($files as $file) {
            if (empty($file->path) || !Storage::disk('public')->exists($file->path)) {
                continue;
            }

            $extension = pathinfo($file->path, PATHINFO_EXTENSION);
            $safeName = Str::slug(pathinfo($file->original_name, PATHINFO_FILENAME));
            $newPath = "payment_requests/{$paymentRequestId}/supplier-debt-{$file->id}-{$safeName}";

            if ($extension) {
                $newPath .= '.' . $extension;
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

            if (!$alreadyExists) {
                DB::table($attachmentTable)->insert($payload);
            }
        }
    }

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
                'month' => $this->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã thêm tệp công nợ.');
    }

    public function downloadDebtFile($fileId)
    {
        $this->ensureSupplierDebtTables();

        abort_unless(Schema::hasTable('finance_supplier_debt_files'), 404);

        $file = DB::table('finance_supplier_debt_files')->where('id', (int) $fileId)->first();

        abort_unless($file, 404);
        abort_if(empty($file->path) || !Storage::disk('public')->exists($file->path), 404);

        return Storage::disk('public')->download($file->path, $file->original_name ?: basename($file->path));
    }

    public function destroyDebtFile($fileId)
    {
        $this->ensureSupplierDebtTables();

        abort_unless(Schema::hasTable('finance_supplier_debt_files'), 404);

        $file = DB::table('finance_supplier_debt_files')->where('id', (int) $fileId)->first();

        abort_unless($file, 404);

        $debt = DB::table('finance_supplier_debts')->where('id', (int) $file->supplier_debt_id)->first();

        if (!empty($file->path)) {
            Storage::disk('public')->delete($file->path);
        }

        DB::table('finance_supplier_debt_files')->where('id', (int) $fileId)->delete();

        return redirect()
            ->route('finance.supplier-debts.index', [
                'month' => $this->supplierDebtMonth($debt),
            ])
            ->with('success', 'Đã xóa tệp công nợ.');
    }


    private function storeDebtFiles(Request $request, int $debtId): void
    {
        if (!Schema::hasTable('finance_supplier_debt_files')) {
            return;
        }

        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            if (!$file || !$file->isValid()) {
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

    private function copyDebtFilesToPaymentRequest(int $debtId, int $paymentRequestId): void
    {
        if (!Schema::hasTable('finance_supplier_debt_files')) {
            return;
        }

        $attachmentTable = $this->paymentRequestAttachmentTable();

        if (!$attachmentTable) {
            return;
        }

        $files = DB::table('finance_supplier_debt_files')
            ->where('supplier_debt_id', $debtId)
            ->get();

        foreach ($files as $file) {
            if (empty($file->path) || !Storage::disk('public')->exists($file->path)) {
                continue;
            }

            $extension = pathinfo($file->path, PATHINFO_EXTENSION);
            $safeName = Str::slug(pathinfo($file->original_name, PATHINFO_FILENAME));
            $newPath = "payment_requests/{$paymentRequestId}/supplier-debt-main-{$file->id}-{$safeName}";

            if ($extension) {
                $newPath .= '.' . $extension;
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

            if (!$alreadyExists) {
                DB::table($attachmentTable)->insert($payload);
            }
        }
    }

    private function canEditCompletedFinanceRecord(): bool
    {
        return strtolower((string) optional(auth()->user())->email) === 'buibichthao@egosolar.vn';
    }


}
