<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EgoSupplierDebtSync
{
    public static function syncAll(): array
    {
        if (
            !Schema::hasTable('finance_supplier_debts') ||
            !Schema::hasTable('finance_supplier_debt_payments')
        ) {
            return [
                'ok' => false,
                'rows' => [],
                'message' => 'Missing finance_supplier_debts or finance_supplier_debt_payments.',
            ];
        }

        $rows = [];

        foreach (DB::table('finance_supplier_debts')->orderBy('id')->get() as $debt) {
            $rows[] = self::syncOne((int) $debt->id);
        }

        return [
            'ok' => true,
            'rows' => $rows,
            'message' => 'Synced supplier debts.',
        ];
    }

    public static function syncOne(int $debtId): array
    {
        $debt = DB::table('finance_supplier_debts')->where('id', $debtId)->first();

        if (!$debt) {
            return [
                'id' => $debtId,
                'found' => false,
            ];
        }

        $rounds = DB::table('finance_supplier_debt_payments')
            ->where('supplier_debt_id', $debtId)
            ->orderBy('payment_round')
            ->orderBy('id')
            ->get();

        $paid = 0.0;
        $waiting = 0.0;

        foreach ($rounds as $round) {
            $roundId = (int) $round->id;
            $amount = (float) ($round->amount ?? 0);
            $roundStatus = self::norm($round->status ?? '');

            $isPaid = self::isPaidRoundStatus($roundStatus);
            $isWaiting = self::isWaitingRoundStatus($roundStatus);

            if (!empty($round->payment_request_id) && Schema::hasTable('payment_requests')) {
                $paymentRequest = DB::table('payment_requests')
                    ->where('id', (int) $round->payment_request_id)
                    ->first();

                if ($paymentRequest) {
                    $prStatus = self::norm($paymentRequest->status ?? '');

                    if (self::isPaidPaymentRequestStatus($prStatus)) {
                        $isPaid = true;
                        $isWaiting = false;

                        if ($roundStatus !== 'accounting_approved') {
                            DB::table('finance_supplier_debt_payments')
                                ->where('id', $roundId)
                                ->update([
                                    'status' => 'accounting_approved',
                                    'updated_at' => now(),
                                ]);
                        }
                    } elseif (self::isWaitingPaymentRequestStatus($prStatus)) {
                        $isWaiting = true;

                        if (!in_array($roundStatus, ['requested', 'submitted', 'admin_approved'], true)) {
                            DB::table('finance_supplier_debt_payments')
                                ->where('id', $roundId)
                                ->update([
                                    'status' => 'requested',
                                    'updated_at' => now(),
                                ]);
                        }
                    }
                }
            }

            if ($isPaid) {
                $paid += $amount;
            } elseif ($isWaiting) {
                $waiting += $amount;
            }
        }

        $total = (float) ($debt->total_amount ?? 0);

        if ($total > 0 && $paid + 0.5 >= $total) {
            $paid = $total;
            $status = 'paid';
        } elseif ($paid > 0 || $waiting > 0) {
            $status = 'partial';
        } else {
            $status = 'unpaid';
        }

        DB::table('finance_supplier_debts')
            ->where('id', $debtId)
            ->update([
                'paid_amount' => $paid,
                'status' => $status,
                'updated_at' => now(),
            ]);

        return [
            'id' => $debtId,
            'found' => true,
            'supplier_name' => (string) ($debt->supplier_name ?? ''),
            'document_no' => (string) ($debt->document_no ?? ''),
            'total_amount' => $total,
            'paid_amount' => $paid,
            'waiting_amount' => $waiting,
            'remaining_amount' => max(0, $total - $paid),
            'status' => $status,
        ];
    }

    private static function norm($value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');

        $map = [
            'đ' => 'd',
            'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        ];

        $value = strtr($value, $map);
        $value = preg_replace('/\s+/', '_', $value);

        return $value;
    }

    private static function isPaidRoundStatus(string $status): bool
    {
        return in_array($status, [
            'paid',
            'accounting_approved',
            'accounting_paid',
            'completed',
            'complete',
            'done',
            'closed',
            'da_thanh_toan',
            'da_chi',
            'ke_toan_da_chi',
        ], true);
    }

    private static function isWaitingRoundStatus(string $status): bool
    {
        return in_array($status, [
            'requested',
            'submitted',
            'admin_approved',
            'pending',
            'sent',
            'da_gui',
            'cho_duyet',
        ], true);
    }

    private static function isPaidPaymentRequestStatus(string $status): bool
    {
        return in_array($status, [
            'accounting_approved',
            'accounting_paid',
            'paid',
            'completed',
            'complete',
            'done',
            'closed',
            'ke_toan_da_chi',
            'da_chi',
        ], true);
    }

    private static function isWaitingPaymentRequestStatus(string $status): bool
    {
        return in_array($status, [
            'submitted',
            'admin_approved',
            'pending',
            'sent',
            'da_gui',
            'cho_ke_toan',
        ], true);
    }
}