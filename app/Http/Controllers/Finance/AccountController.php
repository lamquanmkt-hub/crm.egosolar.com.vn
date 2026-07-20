<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Quản lý quỹ / tài khoản thu chi (CRUD).
 */
class AccountController extends Controller
{
    /**
     * Danh sách quỹ / tài khoản kèm bộ lọc và thống kê tổng quan.
     */
    public function index(Request $request)
    {
        $query = Account::query();

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%")
                    ->orWhere('note', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $accounts = $query->latest()->paginate(12)->withQueryString();

        $stats = [
            'total_accounts' => Account::count(),
            'active_accounts' => Account::where('is_active', true)->count(),
            'total_balance' => Account::sum('current_balance'),
            'cash_balance' => Account::where('type', 'cash')->sum('current_balance'),
        ];

        return view('finance.accounts.index', compact('accounts', 'stats'));
    }

    /**
     * Hiển thị form tạo quỹ / tài khoản mới.
     */
    public function create()
    {
        return view('finance.accounts.create');
    }

    /**
     * Lưu quỹ / tài khoản mới với số dư ban đầu.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:accounts,code'],
            'type' => ['required', 'in:cash,bank,ewallet'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            Account::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'type' => $validated['type'],
                'opening_balance' => $validated['opening_balance'],
                'current_balance' => $validated['opening_balance'],
                'note' => $validated['note'] ?? null,
                'is_active' => request()->boolean('is_active', true),
            ]);
        });

        return redirect()
            ->route('finance.accounts.index')
            ->with('success', 'Tạo quỹ / tài khoản thành công.');
    }

    /**
     * Hiển thị form chỉnh sửa quỹ / tài khoản.
     */
    public function edit(Account $account)
    {
        return view('finance.accounts.create', compact('account'));
    }

    /**
     * Cập nhật thông tin quỹ / tài khoản.
     */
    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:accounts,code,'.$account->id],
            'type' => ['required', 'in:cash,bank,ewallet'],
            'note' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'type' => $validated['type'],
            'note' => $validated['note'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('finance.accounts.index')
            ->with('success', 'Cập nhật quỹ / tài khoản thành công.');
    }

    /**
     * Xóa quỹ / tài khoản nếu chưa phát sinh giao dịch.
     */
    public function destroy(Account $account)
    {
        if ($account->receipts()->exists() || $account->payments()->exists()) {
            return back()->with('error', 'Không thể xóa tài khoản đã phát sinh giao dịch.');
        }

        $account->delete();

        return back()->with('success', 'Đã xóa quỹ / tài khoản.');
    }
}
