<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->filled('date_from')
            ? $request->date_from
            : now()->startOfMonth()->format('Y-m-d');

        $dateTo = $request->filled('date_to')
            ? $request->date_to
            : now()->endOfMonth()->format('Y-m-d');

        $query = Receipt::with('account')
            ->whereDate('receipt_date', '>=', $dateFrom)
            ->whereDate('receipt_date', '<=', $dateTo)
            ->latest('receipt_date')
            ->latest('id');

        if ($request->filled('q')) {
            $keyword = trim($request->q);

            $query->where(function ($sub) use ($keyword) {
                $sub->where('code', 'like', '%' . $keyword . '%')
                    ->orWhere('payer_name', 'like', '%' . $keyword . '%')
                    ->orWhere('payer_phone', 'like', '%' . $keyword . '%')
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

        $receipts = $query->paginate(12)->withQueryString();

        $statsQuery = Receipt::query()
            ->whereDate('receipt_date', '>=', $dateFrom)
            ->whereDate('receipt_date', '<=', $dateTo);

        $stats = [
            'total_count' => (clone $statsQuery)->count(),
            'total_amount' => (float) (clone $statsQuery)->sum('amount'),
            'today_amount' => (float) Receipt::whereDate('receipt_date', now()->toDateString())->sum('amount'),
            'this_month_amount' => (float) Receipt::whereYear('receipt_date', now()->year)
                ->whereMonth('receipt_date', now()->month)
                ->sum('amount'),
        ];

        $accounts = Account::where('is_active', true)->orderBy('name')->get();

        return view('finance.receipts.index', [
            'receipts' => $receipts,
            'stats' => $stats,
            'accounts' => $accounts,
            'categories' => method_exists(Receipt::class, 'categoryOptions') ? Receipt::categoryOptions() : [],
            'paymentMethods' => method_exists(Receipt::class, 'paymentMethodOptions') ? Receipt::paymentMethodOptions() : [],
            'mode' => 'index',
            'defaultDateFrom' => $dateFrom,
            'defaultDateTo' => $dateTo,
        ]);
    }

    public function create()
    {
        $accounts = Account::where('is_active', true)->orderBy('name')->get();

        return view('finance.receipts.create', [
            'accounts' => $accounts,
            'categories' => method_exists(Receipt::class, 'categoryOptions') ? Receipt::categoryOptions() : [],
            'paymentMethods' => method_exists(Receipt::class, 'paymentMethodOptions') ? Receipt::paymentMethodOptions() : [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'receipt_date' => ['required', 'date'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:50'],
            'category' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated) {
            $account = Account::lockForUpdate()->findOrFail($validated['account_id']);

            Receipt::create([
                'account_id' => $validated['account_id'],
                'code' => $this->generateReceiptCode(),
                'receipt_date' => $validated['receipt_date'],
                'payer_name' => $validated['payer_name'] ?? null,
                'payer_phone' => $validated['payer_phone'] ?? null,
                'category' => $validated['category'],
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $account->increment('current_balance', $validated['amount']);
        });

        return redirect()
            ->route('finance.receipts.index')
            ->with('success', 'Tạo phiếu thu thành công.');
    }

    private function generateReceiptCode(): string
    {
        $prefix = 'PT' . now()->format('Ymd');

        $last = Receipt::query()
            ->where('code', 'like', $prefix . '-%')
            ->latest('id')
            ->value('code');

        $number = 1;

        if ($last && preg_match('/-(\d+)$/', $last, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        return $prefix . '-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    public function destroy(Receipt $receipt)
    {
        DB::transaction(function () use ($receipt) {
            if ($receipt->account_id) {
                $account = Account::lockForUpdate()->findOrFail($receipt->account_id);
                $account->decrement('current_balance', $receipt->amount);
            }

            $receipt->delete();
        });

        return back()->with('success', 'Đã xóa phiếu thu.');
    }
}