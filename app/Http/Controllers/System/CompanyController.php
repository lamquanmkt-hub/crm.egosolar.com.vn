<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Core\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Quản lý thông tin công ty: danh sách, chỉnh sửa và cập nhật (kèm tài khoản ngân hàng).
 */
class CompanyController extends Controller
{
    /**
     * Danh sách công ty, phân trang 20 dòng theo tên.
     */
    public function index(): View
    {
        $companies = Company::query()
            ->orderBy('name')
            ->paginate(20);

        return view('companies.index', compact('companies'));
    }

    /**
     * Form chỉnh sửa thông tin một công ty.
     */
    public function edit(Company $company): View
    {
        return view('companies.edit', compact('company'));
    }

    /**
     * Cập nhật thông tin công ty, chuẩn hóa tối đa 2 tài khoản ngân hàng và tài khoản mặc định.
     */
    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:companies,code,'.$company->id],
            'tax_code' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_holder' => ['nullable', 'string', 'max:255'],
            'bank_accounts' => ['nullable', 'array'],
            'bank_accounts.*.bank_account' => ['nullable', 'string', 'max:255'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:255'],
            'bank_accounts.*.bank_holder' => ['nullable', 'string', 'max:255'],
            'bank_default_index' => ['nullable'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $defaultIndex = (int) $request->input('bank_default_index', 0);

        $bankAccounts = collect($request->input('bank_accounts', []))
            ->take(2)
            ->map(function ($item, $index) use ($defaultIndex) {
                return [
                    'bank_account' => trim((string) ($item['bank_account'] ?? '')),
                    'bank_name' => trim((string) ($item['bank_name'] ?? '')),
                    'bank_holder' => trim((string) ($item['bank_holder'] ?? '')),
                    'is_default' => ((int) $index === $defaultIndex) ? 1 : 0,
                ];
            })
            ->filter(function ($item) {
                return $item['bank_account'] !== '' || $item['bank_name'] !== '' || $item['bank_holder'] !== '';
            })
            ->values()
            ->all();

        if (! $bankAccounts && (($data['bank_account'] ?? '') || ($data['bank_name'] ?? '') || ($data['bank_holder'] ?? ''))) {
            $bankAccounts[] = [
                'bank_account' => $data['bank_account'] ?? '',
                'bank_name' => $data['bank_name'] ?? '',
                'bank_holder' => $data['bank_holder'] ?? '',
                'is_default' => 1,
            ];
        }

        if ($bankAccounts) {
            $hasDefault = collect($bankAccounts)->contains(function ($item) {
                return ! empty($item['is_default']);
            });

            if (! $hasDefault) {
                $bankAccounts[0]['is_default'] = 1;
            }

            $defaultBank = collect($bankAccounts)->firstWhere('is_default', 1) ?: $bankAccounts[0];

            $data['bank_account'] = $defaultBank['bank_account'] ?? '';
            $data['bank_name'] = $defaultBank['bank_name'] ?? '';
            $data['bank_holder'] = $defaultBank['bank_holder'] ?? '';
            $data['bank_accounts'] = $bankAccounts;
        } else {
            $data['bank_account'] = '';
            $data['bank_name'] = '';
            $data['bank_holder'] = '';
            $data['bank_accounts'] = [];
        }

        unset($data['bank_default_index']);

        $data['is_active'] = $request->boolean('is_active');

        $company->update($data);

        return redirect()
            ->route('companies.index')
            ->with('success', 'Đã cập nhật thông tin công ty.');
    }
}
