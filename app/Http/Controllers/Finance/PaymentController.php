<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->filled('date_from')
            ? $request->date_from
            : now()->startOfMonth()->format('Y-m-d');

        $dateTo = $request->filled('date_to')
            ? $request->date_to
            : now()->endOfMonth()->format('Y-m-d');

        $query = Payment::with('account')
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateTo)
            ->latest('payment_date')
            ->latest('id');

        if ($request->filled('q')) {
            $keyword = trim($request->q);

            $query->where(function ($sub) use ($keyword) {
                $sub->where('code', 'like', '%' . $keyword . '%')
                    ->orWhere('payee_name', 'like', '%' . $keyword . '%')
                    ->orWhere('payee_phone', 'like', '%' . $keyword . '%')
                    ->orWhere('note', 'like', '%' . $keyword . '%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        $payments = $query->paginate(12)->withQueryString();

        $statsQuery = Payment::query()
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateTo);

        $stats = [
            'total_count' => (clone $statsQuery)->count(),
            'total_amount' => (float) (clone $statsQuery)->sum('amount'),
            'today_amount' => (float) Payment::whereDate('payment_date', now()->toDateString())->sum('amount'),
            'this_month_amount' => (float) Payment::whereYear('payment_date', now()->year)
                ->whereMonth('payment_date', now()->month)
                ->sum('amount'),
        ];

        $accounts = Account::where('is_active', true)->orderBy('name')->get();

        return view('finance.payments.index', [
            'payments' => $payments,
            'stats' => $stats,
            'accounts' => $accounts,
            'categories' => method_exists(Payment::class, 'categoryOptions') ? Payment::categoryOptions() : [],
            'paymentMethods' => method_exists(Payment::class, 'paymentMethodOptions') ? Payment::paymentMethodOptions() : [],
            'mode' => 'index',
            'defaultDateFrom' => $dateFrom,
            'defaultDateTo' => $dateTo,
        ]);
    }

    public function create()
{
    $accounts = Account::where('is_active', true)->orderBy('name')->get();

    return view('finance.payments.create', [
        'accounts' => $accounts,
        'categories' => Payment::categoryOptions(),
        'paymentMethods' => Payment::paymentMethodOptions(),
    ]);
}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'payment_date' => ['required', 'date'],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'payee_phone' => ['nullable', 'string', 'max:50'],
            'category' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string'],
        ]);

        $insufficient = false;

        DB::transaction(function () use ($validated, &$insufficient) {
            $account = Account::lockForUpdate()->findOrFail($validated['account_id']);

            if ((float) $account->current_balance < (float) $validated['amount']) {
                $insufficient = true;
                return;
            }

            Payment::create([
                'account_id' => $validated['account_id'],
                'code' => $this->generatePaymentCode(),
                'payment_date' => $validated['payment_date'],
                'payee_name' => $validated['payee_name'] ?? null,
                'payee_phone' => $validated['payee_phone'] ?? null,
                'category' => $validated['category'],
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $account->decrement('current_balance', $validated['amount']);
        });

        if ($insufficient) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Số dư tài khoản không đủ để tạo phiếu chi.');
        }

        return redirect()
            ->route('finance.payments.index')
            ->with('success', 'Tạo phiếu chi thành công.');
    }

    private function generatePaymentCode(): string
    {
        $prefix = 'PC' . now()->format('Ymd');

        $last = Payment::query()
            ->where('code', 'like', $prefix . '-%')
            ->latest('id')
            ->value('code');

        $number = 1;

        if ($last && preg_match('/-(\d+)$/', $last, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        return $prefix . '-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    public function destroy(Payment $payment)
    {
        DB::transaction(function () use ($payment) {
            if ($payment->account_id) {
                $account = Account::lockForUpdate()->findOrFail($payment->account_id);
                $account->increment('current_balance', $payment->amount);
            }

            $payment->delete();
        });

        return back()->with('success', 'Đã xóa phiếu chi.');
    }
}